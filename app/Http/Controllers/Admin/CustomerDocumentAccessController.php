<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerDocument;
use App\Services\Documents\DocumentAccess;
use Illuminate\Http\Request;

/** Open or download a customer document (G3, Day 23). Every successful opening is logged before the file is sent. */
class CustomerDocumentAccessController extends Controller
{
    public function view(Request $request, CustomerDocument $document, DocumentAccess $access)
    {
        return $this->serve($request, $document, $access, 'view');
    }

    public function download(Request $request, CustomerDocument $document, DocumentAccess $access)
    {
        return $this->serve($request, $document, $access, 'download');
    }

    private function serve(Request $request, CustomerDocument $document, DocumentAccess $access, string $action)
    {
        // A HEAD request would send no file but would still look like an access, so only a real GET is accepted.
        abort_unless($request->isMethod('GET'), 405);

        try {
            return $access->open($document, $request->user(), $action, $request);
        } catch (\DomainException $e) {
            return redirect()->to(route('admin.bookings.show', $document->booking_id) . '#documents')->with('error', $e->getMessage());
        }
    }
}
