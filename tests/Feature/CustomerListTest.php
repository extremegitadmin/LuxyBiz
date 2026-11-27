<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CustomerDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Customers menu: list of customers, one customer page with their bookings and documents. */
class CustomerListTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private function customer(string $email, string $name, ?string $phone = null): User
    {
        return User::forceCreate(['name' => $name, 'email' => $email, 'password' => bcrypt('x'), 'phone' => $phone]);
    }

    private function withDocument(User $c, string $name = 'passport-olivia.pdf'): CustomerDocument
    {
        $b = Booking::factory()->create(['user_id' => $c->id]);

        return CustomerDocument::factory()->create(['booking_id' => $b->id, 'original_name' => $name]);
    }

    public function test_list_shows_customers_only_never_staff(): void
    {
        $this->customer('olivia@example.com', 'Olivia Bennett', '+1 555 0100');
        $this->userWith(['admin.customers.view'], 'staffer@example.test', 'Staff Person');
        $this->signInAsSuperAdmin();

        $r = $this->get(route('admin.customers.index'))->assertOk();
        $r->assertSee('Olivia Bennett')->assertSee('olivia@example.com')->assertSee('+1 555 0100');
        $r->assertDontSee('Staff Person')->assertDontSee('staffer@example.test');
        $this->assertSame(1, $r->viewData('customers')->total());
    }

    public function test_booking_and_document_counts(): void
    {
        $a = $this->customer('a@example.com', 'Anna A');
        $b = $this->customer('b@example.com', 'Ben B');
        $this->withDocument($a);
        $this->withDocument($a);
        Booking::factory()->create(['user_id' => $b->id]);
        $this->signInAsSuperAdmin();

        $r = $this->get(route('admin.customers.index'));
        $this->assertSame(2, (int) $r->viewData('documents')[$a->id]);
        $this->assertArrayNotHasKey($b->id, $r->viewData('documents')->all());
        $this->assertSame(1, (int) $r->viewData('bookings')[$b->id]->n);
    }

    public function test_search_and_only_with_documents(): void
    {
        $a = $this->customer('anna@example.com', 'Anna Rao', '98765');
        $this->customer('ben@example.com', 'Ben Cole');
        $this->withDocument($a);
        $this->signInAsSuperAdmin();

        $this->get(route('admin.customers.index', ['q' => 'anna']))->assertSee('Anna Rao')->assertDontSee('Ben Cole');
        $this->get(route('admin.customers.index', ['q' => '98765']))->assertSee('Anna Rao')->assertDontSee('Ben Cole');
        $this->get(route('admin.customers.index', ['q' => 'ben@']))->assertSee('Ben Cole')->assertDontSee('Anna Rao');
        $this->get(route('admin.customers.index', ['with_documents' => 1]))->assertSee('Anna Rao')->assertDontSee('Ben Cole');
        $this->get(route('admin.customers.index', ['q' => '%']))->assertOk();
        $this->get(route('admin.customers.index', ['q' => str_repeat('x', 101)]))->assertSessionHasErrors('q');
    }

    public function test_customer_page_lists_documents_from_every_booking_with_view_and_download(): void
    {
        $c = $this->customer('olivia@example.com', 'Olivia Bennett');
        $d1 = $this->withDocument($c, 'passport-olivia.pdf');
        $d2 = $this->withDocument($c, 'visa-olivia.pdf');
        $other = $this->withDocument($this->customer('x@example.com', 'Someone Else'), 'other-person.pdf');
        $this->signInAsSuperAdmin();

        $r = $this->get(route('admin.customers.show', $c))->assertOk();
        $r->assertSee('passport-olivia.pdf')->assertSee('visa-olivia.pdf')->assertDontSee('other-person.pdf');
        $r->assertSee(route('admin.documents.view', $d1))->assertSee(route('admin.documents.download', $d2))->assertDontSee(route('admin.documents.view', $other));
        $this->assertSame(2, $r->viewData('documentTotal'));
    }

    public function test_the_page_opens_no_file_and_writes_no_access_log(): void
    {
        $c = $this->customer('olivia@example.com', 'Olivia Bennett');
        $this->withDocument($c);
        $this->signInAsSuperAdmin();
        $this->get(route('admin.customers.show', $c))->assertOk();
        $this->assertSame(0, \DB::table('document_access_logs')->count());   // only opening or downloading a file is logged
    }

    public function test_staff_and_super_admin_accounts_have_no_customer_page(): void
    {
        $staff = $this->userWith(['admin.customers.view'], 'staffer@example.test', 'Staff Person');
        $this->signInAsSuperAdmin();
        $this->get(route('admin.customers.show', $staff))->assertNotFound();
        $this->get(route('admin.customers.show', auth()->user()))->assertNotFound();
        $this->get(route('admin.customers.show', 99999))->assertNotFound();
    }

    public function test_without_the_documents_permission_no_document_information_is_shown(): void
    {
        $c = $this->customer('olivia@example.com', 'Olivia Bennett');
        $d = $this->withDocument($c, 'passport-olivia.pdf');
        $this->actingAs($this->userWith(['admin.customers.view'], 'agent@example.test', 'Agent'));

        $list = $this->get(route('admin.customers.index'))->assertOk();
        $list->assertDontSee('Documents')->assertDontSee('with_documents');
        $this->assertCount(0, $list->viewData('documents'));

        $show = $this->get(route('admin.customers.show', $c))->assertOk();
        $show->assertDontSee('passport-olivia.pdf')->assertDontSee(route('admin.documents.view', $d))->assertDontSee('id="documents"', false);
        // the filter is ignored rather than leaking which customers have documents
        $this->get(route('admin.customers.index', ['with_documents' => 1]))->assertSee('Olivia Bennett');
    }

    public function test_download_button_needs_the_download_permission(): void
    {
        $c = $this->customer('olivia@example.com', 'Olivia Bennett');
        $d = $this->withDocument($c);
        $this->actingAs($this->userWith(['admin.customers.view', 'admin.documents.view'], 'v@example.test', 'Viewer'));
        $this->get(route('admin.customers.show', $c))->assertSee(route('admin.documents.view', $d))->assertDontSee(route('admin.documents.download', $d));
    }

    public function test_menu_item_and_access(): void
    {
        $this->actingAs($this->userWith(['admin.bookings.view'], 'no@example.test', 'No'));
        $this->get(route('admin.customers.index'))->assertForbidden();
        $this->get(route('admin.bookings.index'))->assertOk()->assertDontSee(route('admin.customers.index'));

        $this->actingAs($this->userWith(['admin.customers.view'], 'yes@example.test', 'Yes'));
        $this->get(route('admin.customers.index'))->assertOk()->assertSee(route('admin.customers.index'));

        auth()->logout();
        $this->get(route('admin.customers.index'))->assertRedirect();
    }

    public function test_customer_page_lists_bookings_and_handles_none(): void
    {
        $c = $this->customer('olivia@example.com', 'Olivia Bennett');
        $this->signInAsSuperAdmin();
        $this->get(route('admin.customers.show', $c))->assertOk()->assertSee('no bookings yet')->assertSee('No documents have been uploaded');

        $b = Booking::factory()->create(['user_id' => $c->id]);
        $this->get(route('admin.customers.show', $c))->assertSee($b->reference);
    }
}
