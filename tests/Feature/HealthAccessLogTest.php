<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChange;
use App\Models\BookingTraveler;
use App\Models\HealthAccessLog;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Every view and change of health information is logged first, and only the right roles can reach it (G4, Day 25). */
class HealthAccessLogTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private const NOTE = 'Diabetic, carries insulin. SECRETHEALTHTEXT';

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function ($t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('action');
                $t->text('description')->nullable();
                $t->text('properties')->nullable();
                $t->timestamps();
            });
        }
    }

    /** A booking with two travelers; the first has a health note. */
    private function booking(): Booking
    {
        $b = Booking::factory()->create(['user_id' => User::factory()->create()->id]);
        BookingTraveler::factory()->lead()->create(['booking_id' => $b->id, 'full_name' => 'Mariam Said', 'health_notes' => self::NOTE]);
        BookingTraveler::factory()->create(['booking_id' => $b->id, 'full_name' => 'Omar Said', 'health_notes' => null]);

        return $b->fresh(['travelers']);
    }

    private function staff(array $perms, string $tag): User
    {
        return $this->userWith($perms, $tag . '@example.test', 'Role ' . $tag);
    }

    // ------------------------------------------------------------------ the log

    public function test_opening_the_page_logs_one_row_per_traveler(): void
    {
        $b = $this->booking();
        $staff = $this->staff(['admin.health.view'], 'viewer');

        $this->actingAs($staff)->withHeader('User-Agent', 'TestBrowser/1.0')->get(route('admin.health.show', $b))->assertOk();

        $rows = HealthAccessLog::orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing($b->travelers->pluck('id')->all(), $rows->pluck('booking_traveler_id')->all());
        foreach ($rows as $row) {
            $this->assertSame($b->id, $row->booking_id);
            $this->assertSame($staff->id, $row->user_id);
            $this->assertSame('Staff', $row->user_name);
            $this->assertSame('view', $row->action);
            $this->assertSame('127.0.0.1', $row->ip);
            $this->assertSame('TestBrowser/1.0', $row->user_agent);
            $this->assertNotNull($row->created_at);
        }
    }

    public function test_a_booking_without_travelers_still_logs_the_view(): void
    {
        $b = Booking::factory()->create();
        $this->actingAs($this->staff(['admin.health.view'], 'viewer'))->get(route('admin.health.show', $b))->assertOk();

        $row = HealthAccessLog::sole();
        $this->assertSame($b->id, $row->booking_id);
        $this->assertNull($row->booking_traveler_id);
    }

    public function test_every_opening_is_logged_again(): void
    {
        $b = $this->booking();
        $this->actingAs($this->staff(['admin.health.view'], 'viewer'));
        $this->get(route('admin.health.show', $b));
        $this->get(route('admin.health.show', $b));

        $this->assertSame(4, HealthAccessLog::count());
    }

    public function test_if_the_view_cannot_be_logged_nothing_is_shown(): void
    {
        $b = $this->booking();
        HealthAccessLog::creating(function () {
            throw new \RuntimeException('log table is down');
        });

        $r = $this->actingAs($this->staff(['admin.health.view'], 'viewer'))->get(route('admin.health.show', $b));

        $r->assertRedirect(route('admin.bookings.show', $b))->assertSessionHas('error');
        $this->assertStringContainsString('not shown', session('error'));
        $this->assertStringNotContainsString('insulin', (string) $r->getContent());
        $this->assertSame(0, HealthAccessLog::count());
    }

    public function test_a_change_is_logged_with_the_booking_history_in_one_step(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $staff = $this->staff(['admin.health.view', 'admin.health.edit'], 'editor');

        $this->actingAs($staff)->put(route('admin.health.update', [$b, $t]), ['health_notes' => 'New text: now vegetarian and a knee problem'])->assertSessionHas('success');

        $row = HealthAccessLog::where('action', 'edit')->sole();
        $this->assertSame($t->id, $row->booking_traveler_id);
        $this->assertSame($staff->id, $row->user_id);
        $this->assertSame(1, BookingChange::count());
    }

    public function test_an_unchanged_save_logs_nothing(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $this->actingAs($this->staff(['admin.health.edit'], 'editor'))->put(route('admin.health.update', [$b, $t]), ['health_notes' => self::NOTE]);

        $this->assertSame(0, HealthAccessLog::count());
        $this->assertSame(0, BookingChange::count());
    }

    public function test_if_the_change_cannot_be_logged_it_is_not_saved_either(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        HealthAccessLog::creating(function () {
            throw new \RuntimeException('log table is down');
        });

        $this->actingAs($this->staff(['admin.health.edit'], 'editor'))->put(route('admin.health.update', [$b, $t]), ['health_notes' => 'Sneaky change'])
            ->assertRedirect(route('admin.health.show', $b));

        $this->assertSame(self::NOTE, $t->fresh()->health_notes);   // unchanged
        $this->assertSame(0, BookingChange::count());                // no history line either
    }

    public function test_the_log_can_be_added_to_but_not_changed_or_deleted(): void
    {
        $b = $this->booking();
        $this->actingAs($this->staff(['admin.health.view'], 'viewer'))->get(route('admin.health.show', $b));
        $row = HealthAccessLog::first();

        $this->assertFalse($row->update(['user_name' => 'Someone else']));
        $this->assertFalse($row->delete());
        $this->assertSame('Staff', $row->fresh()->user_name);
        $this->assertSame(2, HealthAccessLog::count());
    }

    public function test_no_health_text_ever_reaches_the_logs_or_the_audit_log(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $this->actingAs($this->staff(['admin.health.view', 'admin.health.edit'], 'editor'));
        $this->get(route('admin.health.show', $b));
        $this->put(route('admin.health.update', [$b, $t]), ['health_notes' => 'Replacement NEWSECRETTEXT']);

        $dump = json_encode(DB::table('health_access_logs')->get()) . json_encode(DB::table('activity_logs')->get()) . json_encode(DB::table('booking_changes')->get());
        $this->assertStringNotContainsString('SECRETHEALTHTEXT', $dump);
        $this->assertStringNotContainsString('NEWSECRETTEXT', $dump);
        $this->assertStringNotContainsString('insulin', $dump);

        // The health log and the audit log do not even name the traveler. (The booking history shows the traveler's name
        // with "Health notes: [changed, value not stored]", as it does for every other edit.)
        $logs = json_encode(DB::table('health_access_logs')->get()) . json_encode(DB::table('activity_logs')->get());
        $this->assertStringNotContainsString('Mariam', $logs);

        $this->assertSame(['health.viewed', 'health.updated'], DB::table('activity_logs')->orderBy('id')->pluck('action')->all());
    }

    // ------------------------------------------------------------------ who may see the history

    public function test_the_history_is_only_for_people_with_the_log_permission(): void
    {
        $b = $this->booking();
        $opener = $this->staff(['admin.health.view'], 'opener');
        $opener->forceFill(['name' => 'Priya Nair'])->save();
        $this->actingAs($opener)->get(route('admin.health.show', $b))->assertOk()->assertDontSee('Who has looked at this')->assertDontSee('Priya Nair');

        $this->actingAs($this->staff(['admin.health.view', 'admin.health.access_log'], 'auditor'));
        $this->get(route('admin.health.show', $b))->assertOk()->assertSee('Who has looked at this')->assertSee('Priya Nair')->assertSee('Viewed')->assertSee('Mariam Said')->assertSee('Omar Said');
    }

    public function test_the_history_only_lists_this_booking(): void
    {
        $other = $this->booking();
        $mine = $this->booking();
        $someone = $this->staff(['admin.health.view'], 'opener');
        $someone->forceFill(['name' => 'Priya Nair'])->save();
        $this->actingAs($someone)->get(route('admin.health.show', $other));

        $this->actingAs($this->staff(['admin.health.view', 'admin.health.access_log'], 'auditor'));
        $this->get(route('admin.health.show', $mine))->assertOk()->assertDontSee('Priya Nair');
    }

    // ------------------------------------------------------------------ roles

    private function routes(Booking $b): array
    {
        $t = $b->travelers->first();

        return [
            'view' => ['get', route('admin.health.show', $b), 'admin.health.view'],
            'edit' => ['put', route('admin.health.update', [$b, $t]), 'admin.health.edit'],
        ];
    }

    public function test_guests_are_sent_to_login_and_nothing_is_logged(): void
    {
        $b = $this->booking();
        foreach ($this->routes($b) as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('admin.login'));
        }
        $this->assertSame(0, HealthAccessLog::count());
    }

    public function test_a_user_with_no_health_permission_gets_403_and_leaves_no_log(): void
    {
        $b = $this->booking();
        $this->actingAs($this->staff(['admin.bookings.view', 'admin.bookings.edit', 'admin.documents.view', 'admin.cancellations.view'], 'other'));

        foreach ($this->routes($b) as [$method, $url]) {
            $this->{$method}($url, $method === 'put' ? ['health_notes' => 'x'] : [])->assertForbidden();
        }
        $this->assertSame(0, HealthAccessLog::count());
        $this->assertSame(self::NOTE, $b->travelers->first()->fresh()->health_notes);
    }

    public function test_view_and_edit_are_separate_permissions(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();

        $this->actingAs($this->staff(['admin.health.view'], 'v'));
        $this->get(route('admin.health.show', $b))->assertOk();
        $this->put(route('admin.health.update', [$b, $t]), ['health_notes' => 'x'])->assertForbidden();

        $this->actingAs($this->staff(['admin.health.edit'], 'e'));
        $this->put(route('admin.health.update', [$b, $t]), ['health_notes' => 'x'])->assertRedirect();
        $this->get(route('admin.health.show', $b))->assertForbidden();
    }

    public function test_the_access_log_permission_alone_opens_nothing(): void
    {
        $b = $this->booking();
        $this->actingAs($this->staff(['admin.health.access_log'], 'logonly'));

        foreach ($this->routes($b) as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_bookings_edit_does_not_give_access_to_health(): void
    {
        $b = $this->booking();
        $this->actingAs($this->staff(['admin.bookings.view', 'admin.bookings.edit', 'admin.bookings.export'], 'bookings'));

        $this->get(route('admin.health.show', $b))->assertForbidden();
        $this->get(route('admin.bookings.edit', $b))->assertOk()->assertDontSee('insulin');
    }

    public function test_a_super_admin_can_reach_everything(): void
    {
        $b = $this->booking();
        $this->signInAsSuperAdmin();

        $this->get(route('admin.health.show', $b))->assertOk()->assertSee('SECRETHEALTHTEXT');
        $this->put(route('admin.health.update', [$b, $b->travelers->first()]), ['health_notes' => 'x'])->assertRedirect();
    }

    public function test_a_deactivated_user_is_signed_out(): void
    {
        $b = $this->booking();
        $u = $this->staff(['admin.health.view'], 'off');
        $u->forceFill(['is_active' => false])->save();

        $this->actingAs($u)->get(route('admin.health.show', $b))->assertRedirect(route('admin.login'));
        $this->assertSame(0, HealthAccessLog::count());
    }

    public function test_a_head_request_is_refused_and_not_counted_as_an_access(): void
    {
        $b = $this->booking();
        $this->actingAs($this->staff(['admin.health.view'], 'viewer'));

        $this->call('HEAD', route('admin.health.show', $b))->assertStatus(405);
        $this->assertSame(0, HealthAccessLog::count());
    }

    public function test_opening_is_limited_to_60_a_minute_per_person(): void
    {
        $b = Booking::factory()->create();
        $this->actingAs($this->staff(['admin.health.view'], 'viewer'));

        for ($i = 0; $i < 60; $i++) {
            $this->get(route('admin.health.show', $b))->assertOk();
        }
        $this->get(route('admin.health.show', $b))->assertStatus(429);
        $this->assertSame(60, HealthAccessLog::count());
    }

    public function test_permissions_and_routes_are_defined(): void
    {
        foreach (['admin.health.view', 'admin.health.edit', 'admin.health.access_log'] as $slug) {
            $this->assertContains($slug, Permission::allSlugs());
        }
        foreach (['admin.health.show', 'admin.health.update'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertTrue(collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:')), $name);
        }
    }
}
