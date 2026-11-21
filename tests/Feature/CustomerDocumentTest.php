<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\CustomerDocument;
use App\Models\User;
use App\Services\Documents\DocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Secure document storage (G3, Day 22): upload to a private disk, list, delete. */
class CustomerDocumentTest extends TestCase
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

    private function booking(): Booking
    {
        return Booking::factory()->create(['user_id' => User::factory()->create()->id]);
    }

    private function pdf(string $name = 'passport.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::PDF);
    }

    private function upload(Booking $b, array $o = [])
    {
        return $this->post(route('admin.documents.store', $b), $o + ['type' => 'passport', 'file' => $this->pdf()]);
    }

    public function test_a_pdf_is_stored_on_the_private_disk_under_a_random_name(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();
        $t = BookingTraveler::factory()->create(['booking_id' => $b->id, 'full_name' => 'Mariam Said']);

        $this->upload($b, ['traveler_id' => $t->id, 'label' => 'Photo page', 'file' => $this->pdf('Mariam Passport.pdf')])
            ->assertRedirect(route('admin.bookings.show', $b) . '#documents')->assertSessionHas('success');

        $d = CustomerDocument::firstOrFail();
        $this->assertSame($b->id, $d->booking_id);
        $this->assertSame($t->id, $d->booking_traveler_id);
        $this->assertSame('passport', $d->type);
        $this->assertSame('Photo page', $d->label);
        $this->assertSame('application/pdf', $d->mime);
        $this->assertSame('Mariam Passport.pdf', $d->original_name);
        $this->assertSame(strlen(self::PDF), $d->size);
        $this->assertSame(hash('sha256', self::PDF), $d->sha256);
        $this->assertNotNull($d->uploaded_by);

        // The stored name is random and does not contain the customer's file name.
        $this->assertMatchesRegularExpression('#^bookings/' . $b->id . '/[0-9a-z]{26}\.pdf$#', $d->stored_path);
        Storage::disk('documents')->assertExists($d->stored_path);
        $this->assertSame(self::PDF, Storage::disk('documents')->get($d->stored_path));
    }

    public function test_jpg_and_png_images_are_accepted(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();

        $this->upload($b, ['type' => 'visa', 'file' => UploadedFile::fake()->image('visa.jpg', 200, 200)])->assertSessionHasNoErrors();
        $this->upload($b, ['type' => 'id_card', 'file' => UploadedFile::fake()->image('id.png', 200, 200)])->assertSessionHasNoErrors();

        $this->assertSame(['image/jpeg', 'image/png'], CustomerDocument::orderBy('id')->pluck('mime')->all());
        $this->assertSame(['jpg', 'png'], CustomerDocument::orderBy('id')->get()->map(fn ($d) => pathinfo($d->stored_path, PATHINFO_EXTENSION))->all());
    }

    public function test_the_file_name_is_cleaned_and_never_decides_where_the_file_goes(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();

        $this->upload($b, ['file' => $this->pdf('../../public/<script>alert(1)</script>evil.pdf')])->assertSessionHasNoErrors();

        $d = CustomerDocument::firstOrFail();
        $this->assertStringNotContainsString('..', $d->original_name);
        $this->assertStringNotContainsString('<', $d->original_name);
        $this->assertStringNotContainsString('/', $d->original_name);
        $this->assertStringStartsWith('bookings/' . $b->id . '/', $d->stored_path);
        $this->assertCount(1, Storage::disk('documents')->allFiles());
    }

    /** @dataProvider badFiles */
    #[\PHPUnit\Framework\Attributes\DataProvider('badFiles')]
    public function test_wrong_files_are_refused_and_nothing_is_stored(string $name, string $content): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();

        $this->upload($b, ['file' => UploadedFile::fake()->createWithContent($name, $content)])->assertSessionHasErrors('file');

        $this->assertSame(0, CustomerDocument::count());
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public static function badFiles(): array
    {
        return [
            'text file renamed to pdf' => ['passport.pdf', "just some text, not a pdf\n"],
            'php script renamed to jpg' => ['photo.jpg', "<?php echo 'hi';\n"],
            'php script' => ['shell.php', "<?php echo 'hi';\n"],
            'html page' => ['page.html', '<html><body>hi</body></html>'],
            'windows program' => ['setup.exe', "MZ\x90\x00\x03\x00\x00\x00"],
            'svg with script' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'empty pdf' => ['empty.pdf', ''],
        ];
    }

    public function test_a_file_over_the_limit_is_refused(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();

        $big = UploadedFile::fake()->createWithContent('big.pdf', self::PDF . str_repeat('0', (DocumentStorage::MAX_KB + 10) * 1024));
        $this->upload($b, ['file' => $big])->assertSessionHasErrors('file');
        $this->assertSame(0, CustomerDocument::count());
    }

    public function test_type_and_file_are_required_and_the_type_must_be_known(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();

        $this->post(route('admin.documents.store', $b), [])->assertSessionHasErrors(['type', 'file']);
        $this->upload($b, ['type' => 'bank_statement'])->assertSessionHasErrors('type');
        $this->assertSame(0, CustomerDocument::count());
    }

    public function test_a_traveler_from_another_booking_is_refused(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();
        $other = BookingTraveler::factory()->create(['booking_id' => $this->booking()->id]);

        $this->upload($b, ['traveler_id' => $other->id])->assertSessionHasErrors('file');
        $this->assertSame(0, CustomerDocument::count());
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_a_booking_cannot_hold_more_than_the_limit(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();
        CustomerDocument::factory()->count(DocumentStorage::MAX_PER_BOOKING)->create(['booking_id' => $b->id]);

        $this->upload($b)->assertSessionHasErrors('file');
        $this->assertSame(DocumentStorage::MAX_PER_BOOKING, CustomerDocument::count());
    }

    public function test_the_file_is_removed_again_if_the_database_row_cannot_be_saved(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();
        // The file is written first; then saving the row fails.
        CustomerDocument::creating(function () {
            throw new \RuntimeException('database went away');
        });

        try {
            app(DocumentStorage::class)->store($b, $this->pdf(), 'passport', null, null, null);
            $this->fail('Saving should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('database went away', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('documents')->allFiles());
        $this->assertSame(0, CustomerDocument::count());
    }

    public function test_deleting_removes_the_file_and_the_row(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();
        $this->upload($b)->assertSessionHasNoErrors();
        $d = CustomerDocument::firstOrFail();
        $path = $d->stored_path;

        $this->delete(route('admin.documents.destroy', $d))->assertRedirect(route('admin.bookings.show', $b) . '#documents')->assertSessionHas('success');

        $this->assertSame(0, CustomerDocument::count());
        Storage::disk('documents')->assertMissing($path);
    }

    public function test_upload_and_delete_are_audited_without_the_file_name(): void
    {
        $staff = $this->signInAsSuperAdmin();
        $b = $this->booking();
        $this->upload($b, ['file' => $this->pdf('Mariam Said passport.pdf')])->assertSessionHasNoErrors();
        $d = CustomerDocument::firstOrFail();
        $this->delete(route('admin.documents.destroy', $d));

        $rows = DB::table('activity_logs')->orderBy('id')->get();
        $this->assertSame(['document.uploaded', 'document.deleted'], $rows->pluck('action')->all());
        $this->assertSame([$staff->id, $staff->id], $rows->pluck('user_id')->all());

        $up = json_decode($rows[0]->properties, true);
        $this->assertSame($d->id, $up['subject_id']);
        $this->assertSame($b->reference, $up['booking_reference']);
        $this->assertSame('passport', $up['type']);
        $this->assertSame(hash('sha256', self::PDF), $up['sha256']);

        $all = $rows->map(fn ($r) => $r->description . ' ' . $r->properties)->implode(' ');
        $this->assertStringNotContainsString('Mariam', $all);
        $this->assertStringNotContainsString('bookings/', $all);   // no storage path either
    }

    public function test_refused_uploads_are_not_audited(): void
    {
        $this->signInAsSuperAdmin();
        $this->upload($this->booking(), ['file' => UploadedFile::fake()->createWithContent('x.pdf', 'not a pdf')])->assertSessionHasErrors('file');
        $this->assertSame(0, DB::table('activity_logs')->count());
    }

    public function test_the_private_disk_is_outside_the_public_folder(): void
    {
        // Without Storage::fake, the disk the app builds for itself when nothing is configured.
        config(['filesystems.disks.documents' => null]);
        $disk = DocumentStorage::disk();
        $config = config('filesystems.disks.documents');

        $this->assertSame('local', $config['driver']);
        $this->assertSame('private', $config['visibility']);
        $this->assertArrayNotHasKey('url', $config);
        $this->assertStringStartsWith(str_replace('\\', '/', storage_path('app/private')), str_replace('\\', '/', $config['root']));
        $this->assertStringNotContainsString('public', str_replace('\\', '/', $config['root']));
        $this->assertStringStartsNotWith(str_replace('\\', '/', public_path()), str_replace('\\', '/', $config['root']));
        $this->assertNotNull($disk);
    }

    public function test_the_path_and_fingerprint_never_appear_in_json(): void
    {
        $d = CustomerDocument::factory()->create();
        $json = $d->toJson();

        $this->assertStringNotContainsString($d->stored_path, $json);
        $this->assertStringNotContainsString($d->sha256, $json);
        $this->assertArrayNotHasKey('disk', $d->toArray());
    }

    // ------------------------------------------------------------------ permissions and page

    public function test_guests_are_sent_to_login(): void
    {
        $b = $this->booking();
        $d = CustomerDocument::factory()->create(['booking_id' => $b->id]);

        $this->post(route('admin.documents.store', $b), [])->assertRedirect(route('admin.login'));
        $this->delete(route('admin.documents.destroy', $d))->assertRedirect(route('admin.login'));
    }

    public function test_upload_and_delete_need_their_own_permission(): void
    {
        $b = $this->booking();
        $d = CustomerDocument::factory()->create(['booking_id' => $b->id]);

        $this->actingAs($this->userWith(['admin.documents.view', 'admin.documents.delete', 'admin.bookings.view'], 'a@example.test', 'No upload'));
        $this->upload($b)->assertForbidden();

        $this->actingAs($this->userWith(['admin.documents.view', 'admin.documents.upload', 'admin.bookings.view'], 'b@example.test', 'No delete'));
        $this->delete(route('admin.documents.destroy', $d))->assertForbidden();
        $this->assertSame(1, CustomerDocument::count());
        $this->upload($b)->assertSessionHasNoErrors();

        $this->actingAs($this->userWith([], 'c@example.test', 'Nobody'));
        $this->upload($b)->assertForbidden();
        $this->delete(route('admin.documents.destroy', $d))->assertForbidden();
    }

    public function test_the_list_is_only_on_the_booking_page_for_people_who_may_view_documents(): void
    {
        $b = $this->booking();
        CustomerDocument::factory()->create(['booking_id' => $b->id, 'original_name' => 'mariam-passport.pdf', 'type' => 'passport']);

        $this->actingAs($this->userWith(['admin.bookings.view'], 'a@example.test', 'Bookings only'));
        $this->get(route('admin.bookings.show', $b))->assertOk()->assertDontSee('Customer documents')->assertDontSee('mariam-passport.pdf');

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.documents.view'], 'b@example.test', 'Can view'));
        $page = $this->get(route('admin.bookings.show', $b))->assertOk()->assertSee('Customer documents')->assertSee('mariam-passport.pdf')->assertSee('Passport');
        $page->assertDontSee(route('admin.documents.store', $b), false)->assertDontSee('Upload</button>', false);
        $this->assertStringNotContainsString('bookings/' . $b->id . '/', $page->getContent());   // the stored path is never in the page
    }

    public function test_upload_and_delete_buttons_follow_the_permissions(): void
    {
        $b = $this->booking();
        $d = CustomerDocument::factory()->create(['booking_id' => $b->id]);

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.documents.view', 'admin.documents.upload'], 'a@example.test', 'Upload only'));
        $this->get(route('admin.bookings.show', $b))->assertOk()->assertSee(route('admin.documents.store', $b), false)->assertDontSee('action="' . route('admin.documents.destroy', $d) . '"', false);

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.documents.view', 'admin.documents.delete'], 'b@example.test', 'Delete only'));
        $this->get(route('admin.bookings.show', $b))->assertOk()->assertSee('action="' . route('admin.documents.destroy', $d) . '"', false)->assertDontSee(route('admin.documents.store', $b), false);
    }

    public function test_the_page_lists_the_travelers_and_whole_booking_documents(): void
    {
        $this->signInAsSuperAdmin();
        $b = $this->booking();
        $t = BookingTraveler::factory()->create(['booking_id' => $b->id, 'full_name' => 'Mariam Said']);
        CustomerDocument::factory()->create(['booking_id' => $b->id, 'booking_traveler_id' => $t->id, 'type' => 'visa']);
        CustomerDocument::factory()->create(['booking_id' => $b->id, 'type' => 'insurance', 'label' => 'Group policy']);

        $this->get(route('admin.bookings.show', $b))->assertOk()->assertSee('Visa')->assertSee('Mariam Said')->assertSee('Travel insurance')->assertSee('Whole booking')->assertSee('Group policy');
    }

    public function test_the_new_permissions_are_defined_and_every_document_route_has_one(): void
    {
        foreach (['admin.documents.view', 'admin.documents.upload', 'admin.documents.delete'] as $slug) {
            $this->assertContains($slug, \App\Models\Permission::allSlugs());
        }
        foreach (['admin.documents.store', 'admin.documents.destroy'] as $name) {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertTrue(collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:')), $name);
        }
    }
}
