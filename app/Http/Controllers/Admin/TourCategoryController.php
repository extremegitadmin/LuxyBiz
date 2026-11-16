<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TourCategoryController extends Controller
{
    // Category list and add form
    public function index()
    {
        $categories = TourCategory::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.tour-categories.index', compact('categories'));
    }

    // Store new category
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:tour_categories,name',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['slug'] = $this->generateSlug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        TourCategory::create($data);

        return redirect()
            ->route('admin.tour-categories.index')
            ->with('success', 'Tour category added successfully!');
    }

    // Edit category page
    public function edit($id)
    {
        $category = TourCategory::findOrFail($id);

        return view('admin.tour-categories.edit', compact('category'));
    }

    // Update category
    public function update(Request $request, $id)
    {
        $category = TourCategory::findOrFail($id);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tour_categories', 'name')->ignore($category->id),
            ],
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['slug'] = $this->generateSlug(
            $data['name'],
            $category->id
        );

        $data['sort_order'] = $data['sort_order'] ?? 0;

        $category->update($data);

        return redirect()
            ->route('admin.tour-categories.index')
            ->with('success', 'Tour category updated successfully!');
    }

    // Delete category
    public function destroy($id)
    {
        $category = TourCategory::findOrFail($id);

        $category->delete();

        return redirect()
            ->route('admin.tour-categories.index')
            ->with('success', 'Tour category deleted successfully!');
    }

    // Generate unique slug
    private function generateSlug($value, $ignoreId = null)
    {
        $slug = Str::slug($value);

        if ($slug === '') {
            $slug = 'category';
        }

        $originalSlug = $slug;
        $count = 1;

        while (
            TourCategory::where('slug', $slug)
                ->when($ignoreId, function ($query) use ($ignoreId) {
                    $query->where('id', '!=', $ignoreId);
                })
                ->exists()
        ) {
            $count++;
            $slug = $originalSlug . '-' . $count;
        }

        return $slug;
    }
}
