<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CmsPageController extends Controller
{
    public function index(Request $request)
    {
        $pages = CmsPage::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', $request->status)
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.cms.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.cms.pages.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validatePage($request);

        $validated['slug'] = $this->uniqueSlug(
            $validated['slug'] ?: $validated['title']
        );

        $validated['created_by'] = auth()->id();

        if ($validated['status'] === 'published') {
            $validated['published_at'] = $validated['published_at'] ?? now();
        } else {
            $validated['published_at'] = null;
        }

        CmsPage::create($validated);

        return redirect()
            ->route('admin.cms.pages.index')
            ->with('success', 'Page created successfully.');
    }

    public function edit(CmsPage $page)
    {
        return view('admin.cms.pages.edit', compact('page'));
    }

    public function update(Request $request, CmsPage $page)
    {
        $validated = $this->validatePage($request, $page);

        $validated['slug'] = $this->uniqueSlug(
            $validated['slug'] ?: $validated['title'],
            $page->id
        );

        if ($validated['status'] === 'published') {
            $validated['published_at'] =
                $page->published_at ?? $validated['published_at'] ?? now();
        } else {
            $validated['published_at'] = null;
        }

        $page->update($validated);

        return redirect()
            ->route('admin.cms.pages.index')
            ->with('success', 'Page updated successfully.');
    }

    public function publish(CmsPage $page)
    {
        if ($page->status === 'published') {
            $page->update([
                'status' => 'draft',
                'published_at' => null,
            ]);

            return back()->with('success', 'Page unpublished successfully.');
        }

        $page->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return back()->with('success', 'Page published successfully.');
    }

    public function destroy(CmsPage $page)
    {
        if ($page->is_homepage) {
            return back()->with('error', 'The homepage cannot be deleted.');
        }

        $page->delete();

        return redirect()
            ->route('admin.cms.pages.index')
            ->with('success', 'Page deleted successfully.');
    }

    private function validatePage(Request $request, ?CmsPage $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],

            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('cms_pages', 'slug')->ignore($page?->id),
            ],

            'body' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],

            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_homepage' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],

            'featured_media_id' => ['nullable', 'exists:cms_media,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value) ?: 'page';

        $original = $slug;
        $counter = 1;

        while (
            CmsPage::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $original . '-' . $counter++;
        }

        return $slug;
    }
}
