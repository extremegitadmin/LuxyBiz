<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsSiteSetting;
use App\Models\CmsMedia;
use Illuminate\Http\Request;

class CmsSiteSettingController extends Controller
{
    public function edit()
    {
        $storedSettings = CmsSiteSetting::pluck('value', 'key')->toArray();

        $defaults = [
            'site_name' => '',
            'site_tagline' => '',
            'logo_media_id' => null,
            'footer_logo_media_id' => null,
            'favicon_media_id' => null,
            'contact_email' => '',
            'contact_phone' => '',
            'contact_address' => '',
            'footer_description' => '',
            'copyright_text' => '',
            'social_links' => [],
            'footer_settings' => [],
        ];

        // Decode JSON values saved in the database
        $settings = [];

        foreach ($storedSettings as $key => $value) {
            $decoded = json_decode($value, true);

            $settings[$key] = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : $value;
        }

        $settings = array_merge($defaults, $settings);

        $settings = (object) $settings;

        // Available media from Media Library
        $media = CmsMedia::orderBy('name')->get();

        return view(
            'admin.cms.site-settings.edit',
            compact('settings', 'media')
        );
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'nullable|string|max:255',
            'site_tagline' => 'nullable|string|max:255',

            'logo_media_id' => 'nullable|integer|exists:cms_media,id',
            'footer_logo_media_id' => 'nullable|integer|exists:cms_media,id',
            'favicon_media_id' => 'nullable|integer|exists:cms_media,id',

            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_address' => 'nullable|string',

            'footer_description' => 'nullable|string',
            'copyright_text' => 'nullable|string|max:255',

            'social_links' => 'nullable|array',
            'social_links.*' => 'nullable|url|max:255',

            'footer_settings' => 'nullable|array',
            'footer_settings.*' => 'nullable|string|max:255',
        ]);

        foreach ($validated as $key => $value) {

            // Store all values as valid JSON
            $encodedValue = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            CmsSiteSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $encodedValue]
            );
        }

        return redirect()
            ->route('admin.cms.site-settings.edit')
            ->with('success', 'Website settings updated successfully.');
    }
}
