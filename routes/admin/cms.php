
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\CmsHomepageController;
use App\Http\Controllers\Admin\CmsTestimonialController;
use App\Http\Controllers\Admin\CmsTrustItemController;
use App\Http\Controllers\Admin\CmsMenuController;
use App\Http\Controllers\Admin\CmsSiteSettingController;
use App\Http\Controllers\Admin\CmsMediaController;

/*
|--------------------------------------------------------------------------
| CMS and Media Library Routes
|--------------------------------------------------------------------------
|
| URL prefix: /admin
| Route name prefix: admin.
| Middleware: auth, permission
|
*/

Route::middleware(['auth', 'permission'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | CMS Pages (K1, K3)
        |--------------------------------------------------------------------------
        */

        Route::resource('cms/pages', CmsPageController::class)
            ->names('cms.pages')
            ->except(['show']);

        Route::patch(
            'cms/pages/{page}/publish',
            [CmsPageController::class, 'publish']
        )->name('cms.pages.publish');


        /*
        |--------------------------------------------------------------------------
        | Homepage Management (K2)
        |--------------------------------------------------------------------------
        */

        Route::get(
            'cms/homepage',
            [CmsHomepageController::class, 'edit']
        )->name('cms.homepage.edit');

        Route::put(
            'cms/homepage',
            [CmsHomepageController::class, 'update']
        )->name('cms.homepage.update');


        /*
        |--------------------------------------------------------------------------
        | Testimonials (K4)
        |--------------------------------------------------------------------------
        */

        Route::patch(
            'cms/testimonials/reorder',
            [CmsTestimonialController::class, 'reorder']
        )->name('cms.testimonials.reorder');

        Route::resource(
            'cms/testimonials',
            CmsTestimonialController::class
        )->names('cms.testimonials')
            ->except(['show']);


        /*
        |--------------------------------------------------------------------------
        | Trust Items (K4)
        |--------------------------------------------------------------------------
        */

        Route::patch(
            'cms/trust-items/reorder',
            [CmsTrustItemController::class, 'reorder']
        )->name('cms.trust-items.reorder');

        Route::resource(
            'cms/trust-items',
            CmsTrustItemController::class
        )->names('cms.trust-items')
            ->except(['show']);


        /*
        |--------------------------------------------------------------------------
        | Menus (K5)
        |--------------------------------------------------------------------------
        */

        Route::get(
            'cms/menus',
            [CmsMenuController::class, 'index']
        )->name('cms.menus.index');

        Route::get(
            'cms/menus/{menu}/edit',
            [CmsMenuController::class, 'edit']
        )->name('cms.menus.edit');

        Route::put(
            'cms/menus/{menu}',
            [CmsMenuController::class, 'update']
        )->name('cms.menus.update');


        /*
        |--------------------------------------------------------------------------
        | Footer and Site Settings (K5)
        |--------------------------------------------------------------------------
        */

        Route::get(
            'cms/site-settings',
            [CmsSiteSettingController::class, 'edit']
        )->name('cms.site-settings.edit');

        Route::put(
            'cms/site-settings',
            [CmsSiteSettingController::class, 'update']
        )->name('cms.site-settings.update');


        /*
        |--------------------------------------------------------------------------
        | Media Library (K6)
        |--------------------------------------------------------------------------
        */

        // Search and listing
        Route::get(
            'media',
            [CmsMediaController::class, 'index']
        )->name('media.index');

        // Upload new media
        Route::post(
            'media',
            [CmsMediaController::class, 'store']
        )->name('media.store');

        // Where-used information
        Route::get(
            'media/{media}/where-used',
            [CmsMediaController::class, 'whereUsed']
        )->name('media.where-used');

        // Replace an existing file
        Route::put(
            'media/{media}/replace',
            [CmsMediaController::class, 'replace']
        )->name('media.replace');

        // Update alternative text and caption
        Route::patch(
            'media/{media}/alt-text',
            [CmsMediaController::class, 'updateAltText']
        )->name('media.alt-text');

        // Delete media
        Route::delete(
            'media/{media}',
            [CmsMediaController::class, 'destroy']
        )->name('media.destroy');

        // View media details
        Route::get(
            'media/{media}',
            [CmsMediaController::class, 'show']
        )->name('media.show');

    });
