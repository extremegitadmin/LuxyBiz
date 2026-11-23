<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CustomerDocument;
use App\Services\Documents\DocumentStorage;
use App\Support\SafeAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** Upload and remove customer documents (G3, Day 22). Viewing and downloading come on Day 23. */
class CustomerDocumentController extends Controller
{
    public function store(Request $request, Booking $booking, DocumentStorage $storage)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CustomerDocument::TYPES))],
            'traveler_id' => ['nullable', 'integer'],
            'label' => ['nullable', 'string', 'max:120'],
            'file' => ['required', File::types(DocumentStorage::EXTENSIONS)->max(DocumentStorage::MAX_KB)],
        ], [
            'type.required' => 'Choose what kind of document this is.',
            'file.required' => 'Choose a file to upload.',
            'file.uploaded' => 'The file could not be uploaded. It may be larger than the server allows.',
            'file.max' => 'The file is too large. The limit is ' . (DocumentStorage::MAX_KB / 1024) . ' MB.',
            'file.mimes' => 'Only PDF, JPG and PNG files are accepted.',
        ], ['file' => 'file']);

        try {
            $doc = $storage->store($booking, $request->file('file'), $data['type'], isset($data['traveler_id']) ? (int) $data['traveler_id'] : null, $data['label'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        // The audit entry names the document by id and type only. The file name can contain the customer's name.
        SafeAudit::log('document.uploaded', 'Document uploaded to booking ' . $booking->reference, [
            'subject_id' => $doc->id, 'subject_type' => 'CustomerDocument',
            'booking_id' => $booking->id, 'booking_reference' => $booking->reference,
            'type' => $doc->type, 'traveler_id' => $doc->booking_traveler_id, 'mime' => $doc->mime, 'size' => $doc->size, 'sha256' => $doc->sha256,
        ]);

        return redirect()->to(route('admin.bookings.show', $booking) . '#documents')->with('success', $doc->typeLabel() . ' uploaded.');
    }

    public function destroy(CustomerDocument $document)
    {
        $booking = $document->booking;

        SafeAudit::log('document.deleted', 'Document removed from booking ' . $booking?->reference, [
            'subject_id' => $document->id, 'subject_type' => 'CustomerDocument',
            'booking_id' => $document->booking_id, 'booking_reference' => $booking?->reference,
            'type' => $document->type, 'size' => $document->size, 'sha256' => $document->sha256,
        ]);

        $document->delete();   // the file goes with it

        return redirect()->to(route('admin.bookings.show', $document->booking_id) . '#documents')->with('success', 'Document deleted.');
    }
}
