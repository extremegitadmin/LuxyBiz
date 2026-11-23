<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsHomepageSetting;
use App\Models\CmsMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CmsHomepageController extends Controller
{
    public function edit()
    {
        $settings = CmsHomepageSetting::firstOrCreate([]);

        $media = CmsMedia::latest()->paginate(12);

        $selectedTourIds = $settings->featured_tour_ids ?? [];

        $tours = DB::table('tours')
            ->whereIn('id', $selectedTourIds ?: [0])
            ->get();

        return view('admin.cms.homepage.edit', compact(
            'settings',
            'media',
            'tours',
            'selectedTourIds'
        ));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'hero_eyebrow' => ['nullable', 'string', 'max:255'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string'],

            'hero_media_id' => ['nullable', 'exists:cms_media,id'],

            'hero_cta_text' => ['nullable', 'string', 'max:255'],
            'hero_cta_url' => ['nullable', 'string', 'max:255'],

            'hero_secondary_cta_text' => ['nullable', 'string', 'max:255'],
            'hero_secondary_cta_url' => ['nullable', 'string', 'max:255'],

            'featured_tour_ids' => ['nullable', 'array'],
            'featured_tour_ids.*' => ['integer', 'distinct', 'exists:tours,id'],

            'featured_section_title' => ['nullable', 'string', 'max:255'],
            'featured_section_description' => ['nullable', 'string'],

            'trust_section_title' => ['nullable', 'string', 'max:255'],
            'trust_section_description' => ['nullable', 'string'],

            'cta_title' => ['nullable', 'string', 'max:255'],
            'cta_description' => ['nullable', 'string'],
            'cta_button_text' => ['nullable', 'string', 'max:255'],
            'cta_button_url' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['featured_tour_ids'] = $validated['featured_tour_ids'] ?? [];

        $settings = CmsHomepageSetting::firstOrCreate([]);

        $settings->update($validated);

        return back()->with('success', 'Homepage settings updated successfully.');
    }
}
