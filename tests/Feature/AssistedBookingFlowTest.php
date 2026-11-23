<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\User;
use App\Services\Bookings\CustomerDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Days 26-30: customer search, the booking form, taking payment, the list, and who may do what. */
class AssistedBookingFlowTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private Tour $tour;
    private TourDeparture $dep;

    protected function setUp(): void
    {
        parent::setUp();
        if (! \Schema::hasTable('activity_logs')) {   // Anakha's table; the test makes a copy when the project has no migration for it
            \Schema::create('activity_logs', function ($t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('action');
                $t->text('description')->nullable();
                $t->text('properties')->nullable();
                $t->timestamps();
            });
        }
        $this->tour = Tour::create(['title' => 'Kerala Wellness', 'slug' => 'kerala', 'base_price' => 1000, 'currency' => 'USD', 'status' => 'published']);
        $this->dep = TourDeparture::create(['tour_id' => $this->tour->id, 'departs_on' => now()->addDays(60)->toDateString(), 'capacity' => 6, 'status' => 'open']);
    }

    private function customer(string $email = 'olivia@example.com', string $name = 'Olivia Bennett', ?string $phone = '+1 555 010 4242'): User
    {
        return User::forceCreate(['name' => $name, 'email' => $email, 'password' => bcrypt('x'), 'phone' => $phone]);
    }

    private function stripe(): void
    {
        PaymentSetting::putField('stripe', 'enabled', '1');
        PaymentSetting::putField('stripe', 'mode', 'sandbox');
        PaymentSetting::putField('stripe', 'public_key', 'pk_test_PUBLIC');
        PaymentSetting::putField('stripe', 'secret_key', 'sk_test_VERYSECRET1234567890');
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_abc123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_abc123'], 200)]);
    }

    private function form(array $over = []): array
    {
        return $over + [
            'customer_mode' => 'new', 'customer_name' => 'Maya Rao', 'customer_email' => 'maya@example.com', 'customer_phone' => '+91 98765 43210',
            'tour_id' => $this->tour->id, 'tour_departure_id' => $this->dep->id,
            'travelers' => [['full_name' => 'Maya Rao'], ['full_name' => 'Arun Rao']], 'insurance_selected' => 1,
        ];
    }

    private function csrBooking(User $staff, array $a = []): Booking
    {
        return Booking::factory()->csr($staff)->create($a + [
            'user_id' => $this->customer('b' . uniqid() . '@example.com')->id, 'tour_id' => $this->tour->id, 'tour_departure_id' => $this->dep->id,
            'total_amount' => 1000, 'subtotal' => 1000, 'insurance_amount' => 0, 'currency' => 'USD', 'status' => 'pending',
        ]);
    }

    // ------------------------------------------------------------------ customer search (Day 26)

    public function test_search_needs_three_characters_and_masks_contact_details(): void
    {
        $this->customer();
        $this->signInAsSuperAdmin();

        $this->assertSame([], $this->getJson(route('admin.assisted.customers', ['q' => 'ol']))->json('results'));

        $r = $this->getJson(route('admin.assisted.customers', ['q' => 'olivia']))->assertOk();
        $row = $r->json('results.0');
        $this->assertSame('Olivia Bennett', $row['name']);
        $this->assertStringNotContainsString('olivia@', $row['email']);
        $this->assertStringContainsString('@example.com', $row['email']);
        $this->assertSame('••••4242', $row['phone']);
        $this->assertStringNotContainsString('555', json_encode($row));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    public function test_search_never_returns_staff_or_super_admins(): void
    {
        $this->userWith(['admin.assisted.create'], 'olivia.staff@example.test');
        $this->signInAsSuperAdmin();
        $this->assertSame([], $this->getJson(route('admin.assisted.customers', ['q' => 'olivia']))->json('results'));
        $this->assertSame([], $this->getJson(route('admin.assisted.customers', ['q' => 'super']))->json('results'));
    }

    public function test_search_treats_wildcards_as_plain_text_and_limits_results(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->customer("guest$i@example.com", "Guest $i", null);
        }
        $this->signInAsSuperAdmin();
        $this->assertSame([], $this->getJson(route('admin.assisted.customers', ['q' => '%%%']))->json('results'));
        $this->assertCount(8, $this->getJson(route('admin.assisted.customers', ['q' => 'guest']))->json('results'));
    }

    public function test_search_shows_how_many_bookings_a_customer_has(): void
    {
        $c = $this->customer();
        Booking::factory()->count(2)->create(['user_id' => $c->id, 'tour_id' => $this->tour->id]);
        $this->signInAsSuperAdmin();
        $this->assertSame(2, $this->getJson(route('admin.assisted.customers', ['q' => 'olivia']))->json('results.0.bookings'));
    }

    public function test_directory_find_or_create_rules(): void
    {
        $staff = $this->userWith([], 'agent@example.test');
        $dir = app(CustomerDirectory::class);

        [$new] = $dir->findOrCreate('Maya Rao', 'maya@example.com', '+91 1', $staff);
        $this->assertTrue(CustomerDirectory::isCustomer($new));
        $this->assertNull($new->role_id);
        $this->assertFalse((bool) $new->is_super);
        $this->assertFalse((bool) $new->is_active);   // cannot sign in until the website sets a password

        $existing = $this->customer();
        [$same] = $dir->findOrCreate('Someone Else', 'OLIVIA@example.com', '999', $staff);
        $this->assertSame($existing->id, $same->id);
        $this->assertSame('Olivia Bennett', $same->fresh()->name);   // not overwritten

        try {
            $dir->findOrCreate('Takeover', 'agent@example.test', null, $staff);
            $this->fail('A staff email was accepted as a customer');
        } catch (\DomainException $e) {
            $this->assertStringNotContainsString('agent@example.test', $e->getMessage());
            $this->assertStringNotContainsString('staff', strtolower($e->getMessage()));   // does not reveal that it is a staff account
        }
    }

    // ------------------------------------------------------------------ the form (Days 26-27)

    public function test_form_lists_only_bookable_tours(): void
    {
        $this->signInAsSuperAdmin();
        Tour::create(['title' => 'Hidden Draft', 'slug' => 'd', 'base_price' => 5, 'status' => 'draft']);
        $r = $this->get(route('admin.assisted.create'))->assertOk();
        $r->assertSee('Kerala Wellness')->assertDontSee('Hidden Draft');
    }

    public function test_quote_endpoint_matches_the_booking_total(): void
    {
        $this->signInAsSuperAdmin();
        $q = $this->getJson(route('admin.assisted.quote', ['tour_id' => $this->tour->id, 'tour_departure_id' => $this->dep->id, 'travelers' => 2, 'insurance' => 1]))->json();
        $this->assertTrue($q['ok']);
        $this->assertEquals(2170.0, $q['quote']['total']);

        $this->post(route('admin.assisted.store'), $this->form());
        $this->assertEquals(2170.0, (float) Booking::firstOrFail()->total_amount);

        $bad = $this->getJson(route('admin.assisted.quote', ['tour_id' => $this->tour->id, 'tour_departure_id' => $this->dep->id, 'travelers' => 9]))->json();
        $this->assertFalse($bad['ok']);
    }

    public function test_staff_can_book_for_a_new_customer(): void
    {
        $staff = $this->signInAsSuperAdmin();
        $this->post(route('admin.assisted.store'), $this->form())->assertRedirect();

        $b = Booking::firstOrFail();
        $this->assertSame('csr', $b->source);
        $this->assertSame($staff->id, $b->created_by);
        $this->assertSame('maya@example.com', $b->user->email);
        $this->assertSame(2, $b->travelers()->count());
    }

    public function test_staff_can_book_for_an_existing_customer(): void
    {
        $c = $this->customer();
        $this->signInAsSuperAdmin();
        $this->post(route('admin.assisted.store'), $this->form(['customer_mode' => 'existing', 'customer_id' => $c->id]))->assertRedirect();
        $this->assertSame($c->id, Booking::firstOrFail()->user_id);
        $this->assertSame(1, User::where('email', 'olivia@example.com')->count());
    }

    public function test_a_staff_account_cannot_be_chosen_as_the_customer(): void
    {
        $other = $this->userWith([], 'other@example.test');
        $this->signInAsSuperAdmin();
        $this->post(route('admin.assisted.store'), $this->form(['customer_mode' => 'existing', 'customer_id' => $other->id]))->assertSessionHasErrors('booking');
        $this->assertSame(0, Booking::count());
    }

    public function test_form_validation_messages(): void
    {
        $this->signInAsSuperAdmin();
        $this->post(route('admin.assisted.store'), $this->form(['customer_mode' => 'existing', 'customer_id' => null]))->assertSessionHasErrors('customer_id');
        $this->post(route('admin.assisted.store'), $this->form(['customer_email' => 'not-an-email']))->assertSessionHasErrors('customer_email');
        $this->post(route('admin.assisted.store'), $this->form(['travelers' => []]))->assertSessionHasErrors('travelers');
        $this->post(route('admin.assisted.store'), $this->form(['travelers' => [['full_name' => '']]]))->assertSessionHasErrors('travelers.0.full_name');
        $this->post(route('admin.assisted.store'), $this->form(['tour_departure_id' => 999]))->assertSessionHasErrors();
        $this->assertSame(0, Booking::count());
    }

    public function test_a_full_departure_shows_an_error_and_keeps_the_input(): void
    {
        $this->dep->update(['capacity' => 1]);
        $this->signInAsSuperAdmin();
        $this->post(route('admin.assisted.store'), $this->form())->assertSessionHasErrors('booking');
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, User::where('email', 'maya@example.com')->count());   // the new customer is rolled back too
    }

    public function test_after_booking_staff_with_payment_permission_go_to_the_payment_page(): void
    {
        $this->actingAs($this->userWith(['admin.assisted.create', 'admin.payments.link.create']));
        $r = $this->post(route('admin.assisted.store'), $this->form());
        $r->assertRedirect(route('admin.assisted.payment', Booking::firstOrFail()));
    }

    public function test_booking_creation_is_written_to_the_audit_log_without_contact_details(): void
    {
        $this->signInAsSuperAdmin();
        $this->post(route('admin.assisted.store'), $this->form());
        $rows = \DB::table('activity_logs')->whereIn('action', ['booking.created', 'customer.created'])->get();
        $this->assertGreaterThanOrEqual(2, $rows->count());
        $this->assertStringNotContainsString('maya@example.com', $rows->toJson());
    }

    // ------------------------------------------------------------------ taking payment (Days 28-29)

    public function test_payment_page_shows_balance_and_refuses_a_cancelled_booking(): void
    {
        $staff = $this->signInAsSuperAdmin();
        $b = $this->csrBooking($staff);
        $this->get(route('admin.assisted.payment', $b))->assertOk()->assertSee('Open payment page');

        $b->update(['status' => 'cancelled']);
        $this->get(route('admin.assisted.payment', $b))->assertOk()->assertDontSee('Open payment page')->assertSee('cannot take payment');
    }

    public function test_hosted_payment_redirects_to_the_provider_and_records_a_pending_payment(): void
    {
        $this->stripe();
        $staff = $this->signInAsSuperAdmin();
        $b = $this->csrBooking($staff);

        $this->post(route('admin.assisted.hosted', $b), ['choice' => 'custom', 'amount' => 250])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_abc123');

        $p = Payment::firstOrFail();
        $this->assertEquals(250.0, (float) $p->amount);
        $this->assertSame('csr_hosted', $p->channel);
        $this->assertSame('pending', $p->status);
        $this->assertTrue(\DB::table('activity_logs')->where('action', 'payment.session_created')->exists());
        $this->assertStringNotContainsString('checkout.stripe.com', \DB::table('activity_logs')->get()->toJson());
    }

    public function test_payment_amount_rules(): void
    {
        $this->stripe();
        $staff = $this->signInAsSuperAdmin();
        $b = $this->csrBooking($staff);

        $this->post(route('admin.assisted.hosted', $b), ['choice' => 'custom', 'amount' => 1001])->assertSessionHas('error');
        $this->post(route('admin.assisted.hosted', $b), ['choice' => 'custom', 'amount' => 0])->assertSessionHasErrors('amount');
        $this->post(route('admin.assisted.hosted', $b), ['choice' => 'custom', 'amount' => -5])->assertSessionHasErrors('amount');
        $this->post(route('admin.assisted.hosted', $b), ['choice' => 'custom', 'amount' => 'abc'])->assertSessionHasErrors('amount');
        $this->post(route('admin.assisted.hosted', $b), ['choice' => 'custom'])->assertSessionHasErrors('amount');
        $this->post(route('admin.assisted.hosted', $b), ['choice' => 'bogus'])->assertSessionHasErrors('choice');
        $this->assertSame(0, Payment::count());

        $this->post(route('admin.assisted.hosted', $b), ['choice' => 'balance'])->assertRedirect();
        $this->assertEquals(1000.0, (float) Payment::firstOrFail()->amount);
    }

    public function test_send_link_emails_the_customer_and_shows_the_link_once(): void
    {
        $this->stripe();
        $staff = $this->signInAsSuperAdmin();
        $c = $this->customer();
        $b = $this->csrBooking($staff, ['user_id' => $c->id]);

        $r = $this->post(route('admin.assisted.send-link', $b), ['choice' => 'balance']);
        $r->assertRedirect(route('admin.assisted.payment', $b))->assertSessionHas('success')->assertSessionHas('customer_link');
        $url = session('customer_link');
        $this->assertMatchesRegularExpression('#/pay/[A-Za-z0-9_-]{43}$#', $url);

        // the link is not stored anywhere in plain text
        $token = basename($url);
        $this->assertStringNotContainsString($token, json_encode(\DB::table('payment_requests')->get()));

        // it is shown on the page that follows, and not on any later visit
        $this->get(route('admin.assisted.payment', $b))->assertSee($token);
        $this->get(route('admin.assisted.payment', $b))->assertDontSee($token);
    }

    public function test_cancel_and_resend_belong_to_the_booking_in_the_url(): void
    {
        $this->stripe();
        $staff = $this->signInAsSuperAdmin();
        $a = $this->csrBooking($staff);
        $other = $this->csrBooking($staff);
        $this->post(route('admin.assisted.send-link', $a), ['choice' => 'balance']);
        $req = \App\Models\PaymentRequest::firstOrFail();

        $this->post(route('admin.assisted.cancel-link', [$other, $req]))->assertNotFound();
        $this->post(route('admin.assisted.resend-link', [$other, $req]))->assertNotFound();
        $this->assertNull($req->fresh()->cancelled_at);

        $this->post(route('admin.assisted.cancel-link', [$a, $req]))->assertRedirect();
        $this->assertNotNull($req->fresh()->cancelled_at);
    }

    // ------------------------------------------------------------------ the list (Day 30)

    public function test_list_shows_only_staff_made_bookings_with_balances(): void
    {
        $staff = $this->signInAsSuperAdmin();
        $mine = $this->csrBooking($staff);
        $web = Booking::factory()->create(['tour_id' => $this->tour->id, 'source' => 'web']);
        Payment::factory()->create(['booking_id' => $mine->id, 'type' => 'charge', 'status' => 'succeeded', 'amount' => 400, 'currency' => 'USD']);
        $mine->syncPaymentStatus();

        $r = $this->get(route('admin.assisted.index'))->assertOk();
        $r->assertSee($mine->reference)->assertDontSee($web->reference);
        $r->assertSee('600.00');   // balance
        $r->assertSee('USD 600.00');   // outstanding tile
    }

    public function test_list_filters(): void
    {
        $a = $this->signInAsSuperAdmin();
        $b = $this->userWith(['admin.assisted.view'], 'agent2@example.test');
        $one = $this->csrBooking($a);
        $two = $this->csrBooking($b, ['payment_status' => 'paid']);

        $this->get(route('admin.assisted.index', ['payment_status' => 'paid']))->assertSee($two->reference)->assertDontSee($one->reference);
        $this->get(route('admin.assisted.index', ['mine' => 1]))->assertSee($one->reference)->assertDontSee($two->reference);
        $this->get(route('admin.assisted.index', ['staff' => $b->id]))->assertSee($two->reference)->assertDontSee($one->reference);
        $this->get(route('admin.assisted.index', ['q' => $one->reference]))->assertSee($one->reference)->assertDontSee($two->reference);
        $this->get(route('admin.assisted.index', ['from' => now()->addDay()->toDateString()]))->assertDontSee($one->reference);
        $this->get(route('admin.assisted.index', ['q' => '%']))->assertOk();
        $this->get(route('admin.assisted.index', ['payment_status' => 'nonsense']))->assertSessionHasErrors('payment_status');
    }

    public function test_tiles_ignore_the_payment_tab_but_follow_the_search(): void
    {
        $a = $this->signInAsSuperAdmin();
        $this->csrBooking($a);
        $this->csrBooking($a, ['payment_status' => 'paid']);
        $r = $this->get(route('admin.assisted.index', ['payment_status' => 'paid']));
        $this->assertSame(1, $r->viewData('counts')['unpaid']);
        $this->assertSame(1, $r->viewData('counts')['paid']);
    }

    public function test_cancelled_bookings_are_not_counted_as_money_to_collect(): void
    {
        $a = $this->signInAsSuperAdmin();
        $this->csrBooking($a, ['status' => 'cancelled']);
        $this->assertTrue($this->get(route('admin.assisted.index'))->viewData('owing')->isEmpty());
    }

    // ------------------------------------------------------------------ who may do what

    public function test_role_matrix(): void
    {
        $staff = $this->csrBooking($this->superAdmin('boss@example.test'));
        $routes = [
            ['get', route('admin.assisted.index'), 'admin.assisted.view'],
            ['get', route('admin.assisted.create'), 'admin.assisted.create'],
            ['post', route('admin.assisted.store'), 'admin.assisted.create'],
            ['get', route('admin.assisted.customers', ['q' => 'abc']), 'admin.assisted.create'],
            ['get', route('admin.assisted.quote'), 'admin.assisted.create'],
            ['get', route('admin.assisted.payment', $staff), 'admin.payments.link.create'],
            ['post', route('admin.assisted.hosted', $staff), 'admin.payments.link.create'],
            ['post', route('admin.assisted.send-link', $staff), 'admin.payments.link.create'],
        ];
        foreach ($routes as $i => [$method, $url, $needs]) {
            $nobody = $this->userWith(['admin.bookings.view'], "nobody$i@example.test", "Nobody $i");
            $this->actingAs($nobody)->{$method}($url)->assertForbidden();

            $allowed = $this->userWith([$needs], "ok$i@example.test", "Ok $i");
            $this->actingAs($allowed);
            $this->assertNotSame(403, $this->{$method}($url)->getStatusCode(), "$needs should open $url");
        }
    }

    public function test_guests_are_sent_to_login_but_the_pay_page_is_public(): void
    {
        $this->get(route('admin.assisted.index'))->assertRedirect();
        $this->get(route('admin.assisted.create'))->assertRedirect();
        $this->getJson(route('admin.assisted.customers', ['q' => 'olivia']))->assertUnauthorized();
        $this->get(route('pay.show', str_repeat('a', 43)))->assertOk();
    }

    public function test_viewing_the_list_does_not_allow_taking_payment(): void
    {
        $viewer = $this->userWith(['admin.assisted.view'], 'viewer@example.test');
        $b = $this->csrBooking($viewer);
        $this->actingAs($viewer)->get(route('admin.assisted.index'))->assertOk()->assertDontSee('Take payment');
        $this->get(route('admin.assisted.payment', $b))->assertForbidden();
    }
}
