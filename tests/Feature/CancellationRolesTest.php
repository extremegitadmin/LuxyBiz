<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\Permission;
use App\Models\Payment;
use App\Models\User;
use App\Services\Cancellations\CancellationRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Who may do what on cancellations (Day 21): every route, every role, and what each role sees on the page. */
class CancellationRolesTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private const GROUP = [
        'admin.cancellations.view', 'admin.cancellations.override', 'admin.cancellations.decide', 'admin.cancellations.complete',
        'admin.settings.cancellation.view', 'admin.settings.cancellation.edit',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function request(string $status = 'pending'): CancellationRequest
    {
        $b = Booking::factory()->create([
            'user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'payment_status' => 'paid',
            'subtotal' => 5000, 'insurance_amount' => 0, 'total_amount' => 5000, 'currency' => 'USD',
        ]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 5000, 'currency' => 'USD', 'status' => 'succeeded', 'gateway' => 'stripe']);
        $r = app(CancellationRequestService::class)->open($b);
        $r->update(['calculated_refund' => 1000, 'calculation' => ['warnings' => []]]);
        if ($status === 'approved') {
            $r->update(['status' => 'approved', 'final_refund' => 1000, 'decided_at' => now()->subHour()]);
        }

        return $r->fresh();
    }

    /** One entry per route: [method, url, permission needed]. POSTs send nothing, so a pass means "not 403", not "worked". */
    private function routes(CancellationRequest $r): array
    {
        return [
            'list' => ['get', route('admin.cancellations.index'), 'admin.cancellations.view'],
            'detail' => ['get', route('admin.cancellations.show', $r), 'admin.cancellations.view'],
            'override' => ['put', route('admin.cancellations.override', $r), 'admin.cancellations.override'],
            'override reset' => ['delete', route('admin.cancellations.override.reset', $r), 'admin.cancellations.override'],
            'approve' => ['post', route('admin.cancellations.approve', $r), 'admin.cancellations.decide'],
            'reject' => ['post', route('admin.cancellations.reject', $r), 'admin.cancellations.decide'],
            'resend email' => ['post', route('admin.cancellations.resend', $r), 'admin.cancellations.decide'],
            'mark refunded' => ['post', route('admin.cancellations.refunded', $r), 'admin.cancellations.complete'],
            'issue credit' => ['post', route('admin.cancellations.credit', $r), 'admin.cancellations.complete'],
            'rules page' => ['get', route('admin.settings.cancellation'), 'admin.settings.cancellation.view'],
            'rules save' => ['put', route('admin.settings.cancellation.update'), 'admin.settings.cancellation.edit'],
        ];
    }

    public function test_the_cancellation_permissions_are_defined_and_every_route_has_one(): void
    {
        $this->assertSame([], array_values(array_diff(self::GROUP, Permission::allSlugs())));

        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with((string) $r->getName(), 'admin.cancellations.') || str_starts_with((string) $r->getName(), 'admin.settings.cancellation'));
        $this->assertCount(11, $routes);
        foreach ($routes as $route) {
            $perm = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));
            $this->assertNotNull($perm, $route->getName() . ' has no permission check');
            $this->assertContains('auth', $route->gatherMiddleware(), $route->getName() . ' is not behind login');
        }
    }

    public function test_guests_are_sent_to_login_from_every_route(): void
    {
        $r = $this->request();
        foreach ($this->routes($r) as $name => [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('admin.login'), $name);
        }
    }

    public function test_a_user_with_no_permissions_gets_403_on_every_route(): void
    {
        $r = $this->request();
        $this->actingAs($this->userWith([], 'none@example.test', 'Nobody'));
        foreach ($this->routes($r) as $name => [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    /** Each permission opens its own routes and nothing else. */
    public function test_each_permission_opens_exactly_its_own_routes(): void
    {
        $r = $this->request();
        foreach (self::GROUP as $i => $slug) {
            $this->actingAs($this->userWith([$slug], "only{$i}@example.test", "Only {$slug}"));
            foreach ($this->routes($r) as $name => [$method, $url, $needs]) {
                $response = $this->{$method}($url);
                if ($needs === $slug) {
                    $this->assertNotSame(403, $response->getStatusCode(), "{$slug} should open {$name}");
                } else {
                    $response->assertForbidden();
                }
            }
        }
    }

    public function test_a_super_admin_can_reach_every_route(): void
    {
        $r = $this->request();
        $this->signInAsSuperAdmin();
        foreach ($this->routes($r) as $name => [$method, $url]) {
            $this->assertNotSame(403, $this->{$method}($url)->getStatusCode(), $name);
        }
    }

    public function test_a_deactivated_user_is_signed_out(): void
    {
        $r = $this->request();
        $u = $this->userWith(self::GROUP, 'off@example.test', 'Was admin');
        $u->forceFill(['is_active' => false])->save();

        $this->actingAs($u)->get(route('admin.cancellations.show', $r))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_a_denied_action_changes_nothing(): void
    {
        $r = $this->request();
        $this->actingAs($this->userWith(['admin.cancellations.view'], 'viewer@example.test', 'Viewer'));

        $this->post(route('admin.cancellations.approve', $r))->assertForbidden();
        $this->put(route('admin.cancellations.override', $r), ['amount' => '500', 'reason' => 'I would like to change this'])->assertForbidden();
        $this->post(route('admin.cancellations.reject', $r), ['reason' => 'I would like to reject this one'])->assertForbidden();

        $r = $r->fresh();
        $this->assertSame('pending', $r->status);
        $this->assertNull($r->final_refund);
        $this->assertNull($r->decided_by);
    }

    public function test_a_view_only_user_sees_the_request_but_no_action_forms(): void
    {
        $r = $this->request();
        $this->actingAs($this->userWith(['admin.cancellations.view'], 'viewer@example.test', 'Viewer'));

        $this->get(route('admin.cancellations.show', $r))->assertOk()->assertSee($r->reference)
            ->assertDontSee(route('admin.cancellations.approve', $r), false)
            ->assertDontSee(route('admin.cancellations.reject', $r), false)
            ->assertDontSee(route('admin.cancellations.override', $r), false);
    }

    public function test_each_role_sees_only_the_forms_it_can_use(): void
    {
        $pending = $this->request();
        $approved = $this->request('approved');

        $cases = [
            'decide' => [['admin.cancellations.decide'], ['approve', 'reject'], ['override', 'refunded', 'credit']],
            'override' => [['admin.cancellations.override'], ['override'], ['approve', 'reject', 'refunded', 'credit']],
            'complete' => [['admin.cancellations.complete'], ['refunded', 'credit'], ['approve', 'reject', 'override']],
        ];

        foreach ($cases as $role => [$perms, $sees, $hides]) {
            $this->actingAs($this->userWith(array_merge(['admin.cancellations.view'], $perms), "{$role}@example.test", "Role {$role}"));

            // Deciding and overriding happen while pending; refunding happens once approved.
            $pendingPage = $this->get(route('admin.cancellations.show', $pending))->assertOk();
            $approvedPage = $this->get(route('admin.cancellations.show', $approved))->assertOk();

            foreach (['approve', 'reject', 'override'] as $form) {
                $this->assertSame(in_array($form, $sees, true), str_contains($pendingPage->getContent(), route("admin.cancellations.{$form}", $pending)), "{$role}: {$form} on a pending request");
            }
            foreach (['refunded', 'credit'] as $form) {
                $this->assertSame(in_array($form, $sees, true), str_contains($approvedPage->getContent(), route("admin.cancellations.{$form}", $approved)), "{$role}: {$form} on an approved request");
            }
            $this->assertNotEmpty($hides);
        }
    }

    public function test_booking_and_payment_links_need_their_own_permissions(): void
    {
        $r = $this->request();
        $bookingUrl = route('admin.bookings.show', $r->booking_id);

        $this->actingAs($this->userWith(['admin.cancellations.view'], 'a@example.test', 'Only cancellations'));
        $this->get(route('admin.cancellations.show', $r))->assertOk()->assertDontSee($bookingUrl, false);

        $this->actingAs($this->userWith(['admin.cancellations.view', 'admin.bookings.view'], 'b@example.test', 'Cancellations and bookings'));
        $this->get(route('admin.cancellations.show', $r))->assertOk()->assertSee($bookingUrl, false);
    }

    public function test_rules_page_is_read_only_without_the_edit_permission(): void
    {
        $this->actingAs($this->userWith(['admin.settings.cancellation.view'], 'rules@example.test', 'Rules viewer'));
        $this->get(route('admin.settings.cancellation'))->assertOk()->assertDontSee('Save rules');
        $this->put(route('admin.settings.cancellation.update'), [])->assertForbidden();
    }

    public function test_the_decider_cannot_complete_and_the_completer_cannot_decide(): void
    {
        $pending = $this->request();
        $approved = $this->request('approved');

        $this->actingAs($this->userWith(['admin.cancellations.view', 'admin.cancellations.decide'], 'd@example.test', 'Decider'));
        $this->post(route('admin.cancellations.refunded', $approved), ['method' => 'cash', 'refunded_on' => now()->toDateString()])->assertForbidden();
        $this->post(route('admin.cancellations.credit', $approved))->assertForbidden();
        $this->assertSame('approved', $approved->fresh()->status);

        $this->actingAs($this->userWith(['admin.cancellations.view', 'admin.cancellations.complete'], 'c@example.test', 'Completer'));
        $this->post(route('admin.cancellations.approve', $pending))->assertForbidden();
        $this->assertSame('pending', $pending->fresh()->status);
    }
}
