<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsTestimonial;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\CmsMedia;
class CmsTestimonialController extends Controller
{
    public function index()
    {
        $testimonials = CmsTestimonial::with('media')
            ->orderBy('sort_order')
            ->latest()
            ->paginate(15);

        return view('admin.cms.testimonials.index', compact('testimonials'));
    }

    public function create()
    {
        $media = CmsMedia::latest()->get();

        return view('admin.cms.testimonials.create', compact('media'));
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'testimonial' => ['required', 'string'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'media_id' => ['nullable', 'exists:cms_media,id'],
            'source' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
        ]);

        if ($validated['status'] === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        CmsTestimonial::create($validated);

        return redirect()
            ->route('admin.cms.testimonials.index')
            ->with('success', 'Testimonial added successfully.');
    }

    public function edit(CmsTestimonial $testimonial)
    {
        $media = CmsMedia::latest()->get();

        return view('admin.cms.testimonials.edit', compact('testimonial', 'media'));
    }

    public function update(Request $request, CmsTestimonial $testimonial)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'testimonial' => ['required', 'string'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'media_id' => ['nullable', 'exists:cms_media,id'],
            'source' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
        ]);

        if ($validated['status'] === 'published' && empty($testimonial->published_at)) {
            $validated['published_at'] = now();
        }

        if ($validated['status'] === 'draft') {
            $validated['published_at'] = null;
        }

        $testimonial->update($validated);

        return redirect()
            ->route('admin.cms.testimonials.index')
            ->with('success', 'Testimonial updated successfully.');
    }

    public function destroy(CmsTestimonial $testimonial)
    {
        $testimonial->delete();

        return back()->with('success', 'Testimonial deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['required', 'integer', 'distinct', 'exists:cms_testimonials,id'],
        ]);

        foreach ($validated['items'] as $position => $id) {
            CmsTestimonial::whereKey($id)->update([
                'sort_order' => $position + 1,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Testimonials reordered successfully.',
        ]);
    }
}
