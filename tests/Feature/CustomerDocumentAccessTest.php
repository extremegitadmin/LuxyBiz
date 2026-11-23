<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\CustomerDocument;
use App\Models\DocumentAccessLog;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Secure view and download of customer documents, with an access log (G3, Day 23). */
class CustomerDocumentAccessTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
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

    /** A document whose file really exists on the fake private disk. */
    private function doc(array $o = [], string $content = self::PDF): CustomerDocument
    {
        $booking = Booking::factory()->create(['user_id' => User::factory()->create()->id]);
        $path = 'bookings/' . $booking->id . '/' . strtolower((string) \Illuminate\Support\Str::ulid()) . '.pdf';
        Storage::disk('documents')->put($path, $content);

        return CustomerDocument::factory()->create($o + [
            'booking_id' => $booking->id, 'stored_path' => $path, 'mime' => 'application/pdf', 'size' => strlen($content),
            'sha256' => hash('sha256', $content), 'original_name' => 'Mariam Said passport.pdf', 'type' => 'passport',
        ]);
    }

    private function allDocPermissions(): array
    {
        return ['admin.documents.view', 'admin.documents.download', 'admin.documents.access_log', 'admin.bookings.view'];
    }

    // ------------------------------------------------------------------ opening

    public function test_viewing_streams_the_file_inline_and_logs_who_opened_it(): void
    {
        $d = $this->doc();
        $staff = $this->userWith(['admin.documents.view'], 'v@example.test', 'Viewer');

        $r = $this->actingAs($staff)->withHeader('User-Agent', 'TestBrowser/1.0')->get(route('admin.documents.view', $d));

        $r->assertOk();
        $this->assertSame(self::PDF, $r->streamedContent());
        $r->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $r->headers->get('Content-Disposition'));

        $log = DocumentAccessLog::sole();
        $this->assertSame($d->id, $log->document_id);
        $this->assertSame($d->booking_id, $log->booking_id);
        $this->assertSame('passport', $log->document_type);
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame('Staff', $log->user_name);
        $this->assertSame('view', $log->action);
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertSame('TestBrowser/1.0', $log->user_agent);
        $this->assertNotNull($log->created_at);
    }

    public function test_downloading_sends_an_attachment_and_logs_a_download(): void
    {
        $d = $this->doc();
        $staff = $this->userWith(['admin.documents.download'], 'd@example.test', 'Downloader');

        $r = $this->actingAs($staff)->get(route('admin.documents.download', $d));

        $r->assertOk();
        $this->assertSame(self::PDF, $r->streamedContent());
        $this->assertStringStartsWith('attachment;', $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Mariam Said passport.pdf', $r->headers->get('Content-Disposition'));
        $this->assertSame(['download'], DocumentAccessLog::pluck('action')->all());
    }

    public function test_images_are_served_with_their_own_type(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $d = $this->doc(['mime' => 'image/png', 'original_name' => 'id.png'], $png);

        $this->actingAs($this->superAdmin())->get(route('admin.documents.view', $d))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_the_file_is_sent_with_headers_that_stop_caching_and_guessing(): void
    {
        $d = $this->doc();
        $r = $this->actingAs($this->superAdmin())->get(route('admin.documents.view', $d));

        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $r->assertHeader('Referrer-Policy', 'no-referrer');
        $cache = $r->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);
    }

    public function test_a_type_that_is_not_pdf_or_image_is_never_shown_in_the_browser(): void
    {
        // Even if a database row were changed by hand, an unknown type is only ever offered as a plain download.
        $d = $this->doc(['mime' => 'text/html'], '<script>alert(1)</script>');
        $r = $this->actingAs($this->superAdmin())->get(route('admin.documents.view', $d));

        $r->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringStartsWith('attachment;', $r->headers->get('Content-Disposition'));
    }

    public function test_an_unusual_file_name_cannot_break_the_header(): void
    {
        $d = $this->doc(['original_name' => "Ünï\"cödé\r\nX-Evil: 1 passport.pdf"]);
        $r = $this->actingAs($this->superAdmin())->get(route('admin.documents.download', $d));

        $r->assertOk();
        $this->assertNull($r->headers->get('X-Evil'));
        $this->assertStringNotContainsString("\n", $r->headers->get('Content-Disposition'));
    }

    // ------------------------------------------------------------------ the log

    public function test_the_central_audit_log_gets_a_copy_without_the_file_name(): void
    {
        $d = $this->doc();
        $staff = $this->actingAs($this->superAdmin())->get(route('admin.documents.view', $d));
        $this->get(route('admin.documents.download', $d));

        $rows = DB::table('activity_logs')->orderBy('id')->get();
        $this->assertSame(['document.viewed', 'document.downloaded'], $rows->pluck('action')->all());
        $p = json_decode($rows[0]->properties, true);
        $this->assertSame($d->id, $p['subject_id']);
        $this->assertSame($d->booking_id, $p['booking_id']);

        $all = $rows->map(fn ($r) => $r->description . ' ' . $r->properties)->implode(' ');
        $this->assertStringNotContainsString('Mariam', $all);
        $this->assertStringNotContainsString('bookings/', $all);
    }

    public function test_each_opening_is_one_log_row(): void
    {
        $d = $this->doc();
        $this->actingAs($this->superAdmin());
        $this->get(route('admin.documents.view', $d));
        $this->get(route('admin.documents.view', $d));
        $this->get(route('admin.documents.download', $d));

        $this->assertSame(['view', 'view', 'download'], DocumentAccessLog::orderBy('id')->pluck('action')->all());
    }

    public function test_if_the_access_cannot_be_logged_the_file_is_not_sent(): void
    {
        $d = $this->doc();
        DocumentAccessLog::creating(function () {
            throw new \RuntimeException('log table is down');
        });

        $r = $this->actingAs($this->superAdmin())->get(route('admin.documents.view', $d));

        $r->assertRedirect(route('admin.bookings.show', $d->booking_id) . '#documents');
        $r->assertSessionHas('error');
        $this->assertStringContainsString('not opened', session('error'));
        $this->assertSame(0, DocumentAccessLog::count());
    }

    public function test_the_log_can_be_added_to_but_not_changed_or_deleted(): void
    {
        $d = $this->doc();
        $this->actingAs($this->superAdmin())->get(route('admin.documents.view', $d));
        $log = DocumentAccessLog::sole();

        $this->assertFalse($log->update(['user_name' => 'Someone else']));
        $this->assertFalse($log->delete());
        $this->assertSame('Super Admin', $log->fresh()->user_name);
        $this->assertSame(1, DocumentAccessLog::count());
    }

    public function test_the_history_stays_after_the_document_is_deleted(): void
    {
        $d = $this->doc();
        $this->actingAs($this->superAdmin())->get(route('admin.documents.view', $d));
        $d->delete();

        $this->assertSame(1, DocumentAccessLog::where('document_id', $d->id)->count());
    }

    // ------------------------------------------------------------------ refusals

    public function test_a_missing_file_is_recorded_and_nothing_is_sent(): void
    {
        $d = $this->doc();
        Storage::disk('documents')->delete($d->stored_path);

        $r = $this->actingAs($this->superAdmin())->get(route('admin.documents.view', $d));

        $r->assertRedirect(route('admin.bookings.show', $d->booking_id) . '#documents')->assertSessionHas('error');
        $this->assertSame(['missing'], DocumentAccessLog::pluck('action')->all());
    }

    public function test_a_file_that_no_longer_matches_its_fingerprint_is_not_sent(): void
    {
        $d = $this->doc();
        Storage::disk('documents')->put($d->stored_path, self::PDF . 'tampered');

        $r = $this->actingAs($this->superAdmin())->get(route('admin.documents.download', $d));

        $r->assertRedirect()->assertSessionHas('error');
        $this->assertStringContainsString('does not match', session('error'));
        $this->assertSame(['integrity_failed'], DocumentAccessLog::pluck('action')->all());
        $this->assertSame(['document.integrity_failed'], DB::table('activity_logs')->pluck('action')->all());
    }

    public function test_a_document_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.documents.view', 99999))->assertNotFound();
        $this->assertSame(0, DocumentAccessLog::count());
    }

    public function test_a_head_request_is_refused_and_not_counted_as_an_access(): void
    {
        $d = $this->doc();
        $this->actingAs($this->superAdmin());

        $this->call('HEAD', route('admin.documents.view', $d))->assertStatus(405);
        $this->assertSame(0, DocumentAccessLog::count());
    }

    public function test_opening_is_limited_to_30_a_minute_per_person(): void
    {
        $d = $this->doc();
        $this->actingAs($this->superAdmin());

        for ($i = 0; $i < 30; $i++) {
            $this->get(route('admin.documents.view', $d))->assertOk();
        }
        $this->get(route('admin.documents.view', $d))->assertStatus(429);
        $this->assertSame(30, DocumentAccessLog::count());
    }

    // ------------------------------------------------------------------ permissions

    public function test_guests_are_sent_to_login_and_get_no_file(): void
    {
        $d = $this->doc();

        $this->get(route('admin.documents.view', $d))->assertRedirect(route('admin.login'));
        $this->get(route('admin.documents.download', $d))->assertRedirect(route('admin.login'));
        $this->assertSame(0, DocumentAccessLog::count());
    }

    public function test_view_and_download_are_separate_permissions(): void
    {
        $d = $this->doc();

        $this->actingAs($this->userWith(['admin.documents.view'], 'a@example.test', 'View only'));
        $this->get(route('admin.documents.view', $d))->assertOk();
        $this->get(route('admin.documents.download', $d))->assertForbidden();

        $this->actingAs($this->userWith(['admin.documents.download'], 'b@example.test', 'Download only'));
        $this->get(route('admin.documents.download', $d))->assertOk();
        $this->get(route('admin.documents.view', $d))->assertForbidden();
    }

    public function test_people_without_the_permissions_are_refused_and_leave_no_log(): void
    {
        $d = $this->doc();
        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.documents.upload', 'admin.documents.delete', 'admin.documents.access_log'], 'n@example.test', 'No open'));

        $this->get(route('admin.documents.view', $d))->assertForbidden();
        $this->get(route('admin.documents.download', $d))->assertForbidden();
        $this->assertSame(0, DocumentAccessLog::count());
    }

    public function test_the_new_permissions_and_routes_are_defined(): void
    {
        foreach (['admin.documents.view', 'admin.documents.download', 'admin.documents.access_log'] as $slug) {
            $this->assertContains($slug, Permission::allSlugs());
        }
        foreach (['admin.documents.view', 'admin.documents.download'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertTrue(collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:')), $name);
            $this->assertContains('throttle:30,1', $route->gatherMiddleware());
        }
    }

    // ------------------------------------------------------------------ the booking page

    public function test_the_buttons_follow_the_permissions(): void
    {
        $d = $this->doc();
        $b = $d->booking_id;

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.documents.view'], 'a@example.test', 'View'));
        $this->get(route('admin.bookings.show', $b))->assertOk()
            ->assertSee(route('admin.documents.view', $d), false)->assertDontSee(route('admin.documents.download', $d), false);

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.documents.view', 'admin.documents.download'], 'b@example.test', 'View and download'));
        $this->get(route('admin.bookings.show', $b))->assertOk()
            ->assertSee(route('admin.documents.view', $d), false)->assertSee(route('admin.documents.download', $d), false);
    }

    public function test_the_view_link_opens_in_a_new_tab_without_passing_the_page_on(): void
    {
        $d = $this->doc();
        $this->actingAs($this->superAdmin());

        $html = $this->get(route('admin.bookings.show', $d->booking_id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('admin.documents.view', $d), '#') . '" target="_blank" rel="noopener noreferrer"#', $html);
    }

    public function test_the_access_history_is_only_for_people_with_the_log_permission(): void
    {
        $d = $this->doc();
        $opener = $this->userWith(['admin.documents.view'], 'o@example.test', 'Opener');
        $opener->forceFill(['name' => 'Priya Nair'])->save();
        $this->actingAs($opener)->get(route('admin.documents.view', $d));
        $this->get(route('admin.documents.download', $d))->assertForbidden();

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.documents.view'], 'a@example.test', 'No log'));
        $this->get(route('admin.bookings.show', $d->booking_id))->assertOk()->assertDontSee('Access history')->assertDontSee('Priya Nair');

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.documents.view', 'admin.documents.access_log'], 'b@example.test', 'With log'));
        $this->get(route('admin.bookings.show', $d->booking_id))->assertOk()->assertSee('Access history')->assertSee('Priya Nair')->assertSee('Opened')->assertSee('Passport #' . $d->id);
    }

    public function test_the_history_marks_deleted_documents_and_never_shows_file_names_or_paths(): void
    {
        $d = $this->doc();
        $this->actingAs($this->superAdmin())->get(route('admin.documents.view', $d));
        $path = $d->stored_path;
        $d->delete();

        $html = $this->get(route('admin.bookings.show', $d->booking_id))->assertOk()->assertSee('(deleted)')->getContent();
        $this->assertStringNotContainsString('Mariam Said passport.pdf', $html);
        $this->assertStringNotContainsString($path, $html);
    }

    public function test_the_history_only_shows_this_bookings_openings(): void
    {
        $mine = $this->doc();
        $other = $this->doc();
        $this->actingAs($this->superAdmin());
        $this->get(route('admin.documents.view', $other));

        $this->get(route('admin.bookings.show', $mine->booking_id))->assertOk()->assertSee('Nobody has opened a document of this booking yet.');
    }

    public function test_the_page_still_works_without_the_log_table_being_filled(): void
    {
        $d = $this->doc();
        $this->actingAs($this->superAdmin())->get(route('admin.bookings.show', $d->booking_id))->assertOk()->assertSee('Access history (0)');
    }
}
