<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Permission;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Bookings, payments and gateway settings follow the team's roles and permissions (auth + permission middleware). */
class PaymentPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private function booking(): Booking
    {
        return Booking::factory()->create(['currency' => 'USD', 'total_amount' => 500, 'status' => 'confirmed']);
    }

    /** GET routes with what they need. */
    private function pages(Booking $b, Payment $p): array
    {
        return [
            'admin.bookings.index' => [route('admin.bookings.index'), 'admin.bookings.view'],
            'admin.bookings.show' => [route('admin.bookings.show', $b), 'admin.bookings.view'],
            'admin.bookings.export' => [route('admin.bookings.export'), 'admin.bookings.export'],
            'admin.bookings.edit' => [route('admin.bookings.edit', $b), 'admin.bookings.edit'],
            'admin.payments.index' => [route('admin.payments.index'), 'admin.payments.view'],
            'admin.payments.show' => [route('admin.payments.show', $p), 'admin.payments.view'],
            'admin.payments.events' => [route('admin.payments.events'), 'admin.payments.events.view'],
            'admin.settings.gateway' => [route('admin.settings.gateway'), 'admin.settings.gateway.view'],
        ];
    }

    public function test_every_permission_used_by_a_route_is_defined_in_config_permissions(): void
    {
        $used = collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn ($r) => $r->gatherMiddleware())
            ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'))
            ->flatMap(fn ($m) => explode(',', substr($m, strlen('permission:'))))
            ->unique();

        $this->assertNotEmpty($used);
        $this->assertSame([], $used->diff(Permission::allSlugs())->values()->all(), 'a route uses a permission that is not in config/permissions.php');
    }

    public function test_the_new_permissions_are_in_the_config_and_sync_to_the_database(): void
    {
        $slugs = ['admin.bookings.view', 'admin.bookings.edit', 'admin.bookings.export', 'admin.payments.view',
            'admin.payments.events.view', 'admin.payments.link.create', 'admin.settings.gateway.view', 'admin.settings.gateway.edit'];
        Permission::syncFromConfig();

        foreach ($slugs as $slug) {
            $this->assertContains($slug, Permission::allSlugs());
            $this->assertDatabaseHas('permissions', ['slug' => $slug]);
        }
    }

    public function test_guests_are_sent_to_the_login_page_from_every_screen(): void
    {
        $b = $this->booking();
        $p = Payment::factory()->create(['booking_id' => $b->id]);

        foreach ($this->pages($b, $p) as [$url]) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
        $this->post(route('admin.bookings.payment-link', $b), ['amount' => 10])->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.gateway.update', 'stripe'), [])->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.gateway.routing'), [])->assertRedirect(route('admin.login'));
        $this->post(route('admin.settings.gateway.test', 'razorpay'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_with_no_permissions_gets_403_everywhere(): void
    {
        $b = $this->booking();
        $p = Payment::factory()->create(['booking_id' => $b->id]);
        $this->actingAs($this->userWith([]));

        foreach ($this->pages($b, $p) as [$url]) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('admin.bookings.payment-link', $b), ['amount' => 10])->assertForbidden();
        $this->put(route('admin.bookings.update', $b), [])->assertForbidden();
        $this->put(route('admin.settings.gateway.update', 'stripe'), [])->assertForbidden();
        $this->put(route('admin.settings.gateway.routing'), [])->assertForbidden();
        $this->post(route('admin.settings.gateway.test', 'razorpay'))->assertForbidden();
    }

    public function test_each_permission_opens_only_its_own_screen(): void
    {
        $b = $this->booking();
        $p = Payment::factory()->create(['booking_id' => $b->id]);

        foreach ($this->pages($b, $p) as $name => [$url, $needs]) {
            $user = $this->userWith([$needs], 'only-' . md5($name) . '@example.test', 'Role ' . $name);
            $this->actingAs($user);

            foreach ($this->pages($b, $p) as $otherName => [$otherUrl]) {
                $response = $this->get($otherUrl);
                $name === $otherName || $this->samePermission($needs, $otherName, $b, $p)
                    ? $this->assertNotSame(403, $response->getStatusCode(), "$needs should open $otherName")
                    : $response->assertForbidden();
            }
        }
    }

    /** bookings.index/show share a permission, payments.index/show share one. */
    private function samePermission(string $needs, string $other, Booking $b, Payment $p): bool
    {
        return ($this->pages($b, $p)[$other][1] ?? null) === $needs;
    }

    public function test_gateway_settings_can_be_viewed_without_being_changed(): void
    {
        $this->actingAs($this->userWith(['admin.settings.gateway.view']));

        $this->get(route('admin.settings.gateway'))->assertOk();
        $this->put(route('admin.settings.gateway.update', 'stripe'), ['mode' => 'sandbox'])->assertForbidden();
        $this->put(route('admin.settings.gateway.routing'), ['route_inr' => 'razorpay', 'route_other' => 'stripe'])->assertForbidden();
        $this->post(route('admin.settings.gateway.test', 'stripe'))->assertForbidden();
    }

    public function test_changing_gateway_settings_needs_the_edit_permission(): void
    {
        $this->actingAs($this->userWith(['admin.settings.gateway.view', 'admin.settings.gateway.edit']));

        $this->put(route('admin.settings.gateway.routing'), ['route_inr' => 'razorpay', 'route_other' => 'stripe'])->assertSessionHas('success');
    }

    public function test_creating_a_payment_link_needs_its_own_permission(): void
    {
        $b = $this->booking();
        $this->actingAs($this->userWith(['admin.bookings.view']));
        $this->post(route('admin.bookings.payment-link', $b), ['amount' => 10])->assertForbidden();

        $this->actingAs($this->userWith(['admin.payments.link.create'], 'link@example.test', 'Link role'));
        // allowed through (no gateway is set up here, so the screen answers with its own message)
        $this->post(route('admin.bookings.payment-link', $b), ['amount' => 10])->assertRedirect(route('admin.bookings.show', $b))->assertSessionHas('error');
    }

    public function test_buttons_follow_permissions(): void
    {
        $b = $this->booking();

        $this->actingAs($this->userWith(['admin.bookings.view']));
        $this->get(route('admin.bookings.index'))->assertOk()
            ->assertDontSee(route('admin.bookings.export'), false)
            ->assertDontSee(route('admin.bookings.edit', $b), false);
        $this->get(route('admin.bookings.show', $b))->assertOk()
            ->assertDontSee(route('admin.bookings.edit', $b), false)
            ->assertDontSee('Request online payment');

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.bookings.edit', 'admin.bookings.export', 'admin.payments.link.create'], 'all@example.test', 'All'));
        $this->get(route('admin.bookings.index'))->assertOk()
            ->assertSee(route('admin.bookings.export'), false)
            ->assertSee(route('admin.bookings.edit', $b), false);
        $this->get(route('admin.bookings.show', $b))->assertOk()
            ->assertSee(route('admin.bookings.edit', $b), false)
            ->assertSee('Request online payment');
    }

    public function test_the_sidebar_shows_only_the_links_the_role_may_open(): void
    {
        $this->actingAs($this->userWith(['admin.bookings.view']));
        $page = $this->get(route('admin.bookings.index'))->assertOk();
        $page->assertSee('href="' . route('admin.bookings.index') . '"', false)
            ->assertDontSee('href="' . route('admin.payments.index') . '"', false)
            ->assertDontSee('href="' . route('admin.payments.events') . '"', false)
            ->assertDontSee('href="' . route('admin.settings.gateway') . '"', false);

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.payments.view', 'admin.payments.events.view', 'admin.settings.gateway.view'], 'side@example.test', 'Side'));
        $this->get(route('admin.bookings.index'))->assertOk()
            ->assertSee('href="' . route('admin.payments.index') . '"', false)
            ->assertSee('href="' . route('admin.payments.events') . '"', false)
            ->assertSee('href="' . route('admin.settings.gateway') . '"', false);
    }

    public function test_the_super_admin_opens_everything(): void
    {
        $b = $this->booking();
        $p = Payment::factory()->create(['booking_id' => $b->id]);
        $this->actingAs($this->superAdmin());

        foreach ($this->pages($b, $p) as $name => [$url]) {
            $this->assertNotSame(403, $this->get($url)->getStatusCode(), $name);
        }
    }

    public function test_a_deactivated_user_is_signed_out_and_sent_to_login(): void
    {
        $user = $this->userWith(['admin.bookings.view']);
        $user->forceFill(['is_active' => false])->save();
        $this->actingAs($user);

        $this->get(route('admin.bookings.index'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_the_customer_pages_and_the_webhooks_stay_open_without_login(): void
    {
        $this->get(route('payment.return'))->assertOk();
        $this->get(route('payment.cancelled'))->assertOk();

        // The webhook is protected by its signature, not by a login: an unsigned call is refused with 400/503, never sent to the login page.
        $status = $this->call('POST', '/webhooks/payments/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->getStatusCode();
        $this->assertContains($status, [400, 503]);
        $status = $this->call('POST', '/webhooks/payments/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->getStatusCode();
        $this->assertContains($status, [400, 503]);
    }
}
