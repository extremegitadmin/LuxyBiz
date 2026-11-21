<?php

namespace App\Services\Documents;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\CustomerDocument;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps customer documents (Day 22). Rules:
 *  - files go on the private "documents" disk, never the public one, under a random name;
 *  - the type of the file is detected from its content, the name the customer gave it is only shown to staff;
 *  - a file and its row are created together: if the row cannot be saved the file is removed again.
 * Rule failures throw a DomainException whose message is safe to show to staff.
 */
class DocumentStorage
{
    public const MAX_KB = 5120;            // 5 MB. Stays under the usual PHP post_max_size of 8 MB.
    public const MAX_PER_BOOKING = 30;
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /**
     * The private disk. If the project has no "documents" disk configured, a private one is defined here,
     * under storage/app/private/documents, so uploads work without editing config/filesystems.php.
     */
    public static function disk(string $name = 'documents'): Filesystem
    {
        if (config("filesystems.disks.{$name}") === null) {
            config(["filesystems.disks.{$name}" => [
                'driver' => 'local',
                'root' => storage_path('app/private/documents'),
                'visibility' => 'private',
                'throw' => false,
                'report' => false,
            ]]);
        }

        return Storage::disk($name);
    }

    public function store(Booking $booking, UploadedFile $file, string $type, ?int $travelerId, ?string $label, ?User $by): CustomerDocument
    {
        if (! array_key_exists($type, CustomerDocument::TYPES)) {
            throw new \DomainException('Choose what kind of document this is.');
        }
        if ($travelerId !== null && ! BookingTraveler::query()->whereKey($travelerId)->where('booking_id', $booking->id)->exists()) {
            throw new \DomainException('That traveler is not on this booking.');
        }
        if (CustomerDocument::query()->where('booking_id', $booking->id)->count() >= self::MAX_PER_BOOKING) {
            throw new \DomainException('This booking already has ' . self::MAX_PER_BOOKING . ' documents. Delete one that is not needed before adding another.');
        }

        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw new \DomainException('The file could not be read. Try uploading it again.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if ($extension === null) {
            throw new \DomainException('Only PDF, JPG and PNG files are accepted. This file looks like something else.');
        }

        $hash = hash_file('sha256', $path);
        $stored = 'bookings/' . $booking->id . '/' . Str::lower((string) Str::ulid()) . '.' . $extension;
        $disk = self::disk();

        if (! $disk->put($stored, fopen($path, 'rb'), 'private')) {
            throw new \DomainException('The file could not be saved on the server. Nothing was uploaded.');
        }

        try {
            return DB::transaction(fn () => CustomerDocument::create([
                'booking_id' => $booking->id,
                'booking_traveler_id' => $travelerId,
                'type' => $type,
                'label' => $this->clean($label, 120),
                'original_name' => $this->safeName($file->getClientOriginalName(), $extension),
                'stored_path' => $stored,
                'disk' => 'documents',
                'mime' => $mime,
                'size' => (int) filesize($path),
                'sha256' => $hash,
                'uploaded_by' => $by?->id,
            ]));
        } catch (\Throwable $e) {
            $disk->delete($stored);   // no file without a row
            throw $e;
        }
    }

    /** Remove the file of a document. The row is deleted by the caller. */
    public static function forget(CustomerDocument $doc): void
    {
        try {
            self::disk($doc->disk ?: 'documents')->delete($doc->stored_path);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** A plain file name: no folders, no markup, no control characters, and an extension that matches the content. */
    private function safeName(?string $name, string $extension): string
    {
        $base = pathinfo(str_replace('\\', '/', (string) $name), PATHINFO_FILENAME);
        $base = trim(preg_replace('/[^\p{L}\p{N} ._()-]+/u', '', strip_tags($base)) ?? '');
        $base = mb_substr($base === '' ? 'document' : $base, 0, 150);

        return $base . '.' . $extension;
    }

    private function clean(?string $text, int $max): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
