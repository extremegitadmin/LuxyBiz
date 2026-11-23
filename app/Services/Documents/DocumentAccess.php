<?php

namespace App\Services\Documents;

use App\Models\CustomerDocument;
use App\Models\DocumentAccessLog;
use App\Models\User;
use App\Support\SafeAudit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Opens a customer document for staff (Day 23). Order of work, and why:
 *  1. the file must exist and still match its fingerprint from the upload, otherwise nothing is sent;
 *  2. the access is written to document_access_logs BEFORE the file is sent. If that cannot be written, the file is not sent;
 *  3. the file is streamed with headers that keep browsers and proxies from storing it or guessing its type.
 * Rule failures throw a DomainException whose message is safe to show to staff.
 */
class DocumentAccess
{
    private const SAFE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    /** @param 'view'|'download' $action */
    public function open(CustomerDocument $doc, User $by, string $action, Request $request): StreamedResponse
    {
        $disk = DocumentStorage::disk($doc->disk ?: 'documents');

        if (! $disk->exists($doc->stored_path)) {
            $this->record($doc, $by, 'missing', $request, strict: false);
            throw new \DomainException('This file is missing on the server. It was recorded. Ask the person who uploaded it to upload it again.');
        }

        $stream = $disk->readStream($doc->stored_path);
        if (! is_resource($stream) || ! $this->matchesFingerprint($stream, $doc->sha256)) {
            $this->closeQuietly($stream ?? null);
            $this->record($doc, $by, 'integrity_failed', $request, strict: false);
            throw new \DomainException('This file does not match what was uploaded, so it was not opened. This was recorded. Tell the administrator.');
        }
        rewind($stream);

        $this->record($doc, $by, $action, $request, strict: true);   // fail closed: no log, no file

        $mime = in_array($doc->mime, self::SAFE_TYPES, true) ? $doc->mime : 'application/octet-stream';
        $inline = $action === 'view' && $mime !== 'application/octet-stream';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            $this->closeQuietly($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $doc->size,
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT, $this->asciiName($doc), $this->asciiName($doc)),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /** @param resource $stream */
    private function matchesFingerprint($stream, ?string $expected): bool
    {
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);

        return $expected !== null && hash_equals($expected, hash_final($context));
    }

    private function record(CustomerDocument $doc, User $by, string $action, Request $request, bool $strict): void
    {
        try {
            DocumentAccessLog::create([
                'document_id' => $doc->id,
                'booking_id' => $doc->booking_id,
                'document_type' => $doc->type,
                'user_id' => $by->id,
                'user_name' => mb_substr((string) $by->name, 0, 140),
                'action' => $action,
                'ip' => mb_substr((string) $request->ip(), 0, 45),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            report($e);
            if ($strict) {
                throw new \DomainException('The access could not be recorded, so the file was not opened. Try again, and tell the administrator if it keeps happening.');
            }

            return;
        }

        // The central audit log gets a copy. It names the document by id and type, never the file name.
        SafeAudit::log('document.' . ($action === 'view' ? 'viewed' : ($action === 'download' ? 'downloaded' : $action)),
            'Customer document ' . $doc->id . ' ' . $action . ' (booking ' . $doc->booking_id . ')', [
                'subject_id' => $doc->id, 'subject_type' => 'CustomerDocument',
                'booking_id' => $doc->booking_id, 'type' => $doc->type, 'action' => $action,
            ]);
    }

    /** Name shown in the browser's save box: letters, numbers and a few signs only. */
    private function asciiName(CustomerDocument $doc): string
    {
        $name = preg_replace('/[^A-Za-z0-9 ._()-]+/', '_', \Illuminate\Support\Str::ascii($doc->original_name)) ?? '';

        return trim($name, ' .') === '' ? 'document.' . pathinfo($doc->stored_path, PATHINFO_EXTENSION) : $name;
    }

    private function closeQuietly($stream): void
    {
        if (is_resource($stream)) {
            @fclose($stream);
        }
    }
}
