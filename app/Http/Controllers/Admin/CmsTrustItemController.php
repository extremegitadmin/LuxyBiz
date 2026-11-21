<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsTrustItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\CmsMedia;
class CmsTrustItemController extends Controller
{
    public function index()
    {
        $trustItems = CmsTrustItem::with('media')
            ->orderBy('sort_order')
            ->latest()
            ->paginate(15);

        return view('admin.cms.trust-items.index', compact('trustItems'));
    }

    public function create()
    {
        $media = CmsMedia::latest()->get();

        return view('admin.cms.trust-items.create', compact('media'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'exists:cms_media,id'],
            'link_text' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
        ]);

        if ($validated['status'] === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        CmsTrustItem::create($validated);

        return redirect()
            ->route('admin.cms.trust-items.index')
            ->with('success', 'Trust item created successfully.');
    }

    public function edit(CmsTrustItem $trust_item)
    {
        $media = CmsMedia::latest()->get();

        return view('admin.cms.trust-items.edit', compact('trust_item', 'media'));
    }

    public function update(Request $request, CmsTrustItem $trust_item)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'exists:cms_media,id'],
            'link_text' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
        ]);

        if ($validated['status'] === 'published' && empty($trust_item->published_at)) {
            $validated['published_at'] = now();
        }

        if ($validated['status'] === 'draft') {
            $validated['published_at'] = null;
        }

        $trust_item->update($validated);

        return redirect()
            ->route('admin.cms.trust-items.index')
            ->with('success', 'Trust item updated successfully.');
    }

    public function destroy(CmsTrustItem $trust_item)
    {
        $trust_item->delete();

        return back()->with('success', 'Trust item deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['required', 'integer', 'distinct', 'exists:cms_trust_items,id'],
        ]);

        foreach ($validated['items'] as $position => $id) {
            CmsTrustItem::whereKey($id)->update([
                'sort_order' => $position + 1,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trust items reordered successfully.',
        ]);
    }
}
