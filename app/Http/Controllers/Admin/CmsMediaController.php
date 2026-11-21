<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsMedia;
use App\Models\CmsMediaAttachment;
use App\Models\CmsPage;
use App\Models\CmsHomepageSetting;
use App\Models\CmsTestimonial;
use App\Models\CmsTrustItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CmsMediaController extends Controller
{
    public function index(Request $request)
    {
        $media = CmsMedia::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('file_name', 'like', "%{$search}%")
                      ->orWhere('alt_text', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('file_type', $request->type);
            })
            ->with('uploader')
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('admin.media.index', compact('media'));
    }

    public function show(CmsMedia $media)
    {
        $media->load('uploader');

        $whereUsed = $this->getWhereUsed($media);

        return view('admin.media.show', compact('media', 'whereUsed'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'required',
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,svg,mp4,webm,pdf,doc,docx,xls,xlsx',
            ],
        ]);

        foreach ($validated['files'] as $file) {

            $path = $file->store('cms-media', 'public');

            $mimeType = $file->getMimeType();

            $width = null;
            $height = null;

            if (
                str_starts_with($mimeType, 'image/')
                && $mimeType !== 'image/svg+xml'
            ) {
                $dimensions = @getimagesize($file->getRealPath());

                if ($dimensions) {
                    $width = $dimensions[0];
                    $height = $dimensions[1];
                }
            }

            $fileType = 'document';

            if (str_starts_with($mimeType, 'image/')) {
                $fileType = 'image';
            } elseif (str_starts_with($mimeType, 'video/')) {
                $fileType = 'video';
            }

            CmsMedia::create([
                'name' => $file->getClientOriginalName(),
                'file_name' => basename($path),
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $mimeType,
                'file_type' => $fileType,
                'file_size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'uploaded_by' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Media uploaded successfully.');
    }

    public function updateAltText(Request $request, CmsMedia $media)
    {
        $validated = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $media->update($validated);

        return back()->with('success', 'Media information updated successfully.');
    }

    public function replace(Request $request, CmsMedia $media)
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,svg,mp4,webm,pdf,doc,docx,xls,xlsx',
            ],
        ]);

        $file = $validated['file'];

        $oldDisk = $media->disk;
        $oldPath = $media->file_path;

        $newPath = $file->store('cms-media', 'public');

        $mimeType = $file->getMimeType();

        $width = null;
        $height = null;

        if (
            str_starts_with($mimeType, 'image/')
            && $mimeType !== 'image/svg+xml'
        ) {
            $dimensions = @getimagesize($file->getRealPath());

            if ($dimensions) {
                $width = $dimensions[0];
                $height = $dimensions[1];
            }
        }

        $fileType = 'document';

        if (str_starts_with($mimeType, 'image/')) {
            $fileType = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $fileType = 'video';
        }

        $media->update([
            'name' => $file->getClientOriginalName(),
            'file_name' => basename($newPath),
            'file_path' => $newPath,
            'disk' => 'public',
            'mime_type' => $mimeType,
            'file_type' => $fileType,
            'file_size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ]);

        if ($oldPath !== $newPath) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        return back()->with('success', 'Media file replaced successfully.');
    }

    public function whereUsed(CmsMedia $media)
    {
        $whereUsed = $this->getWhereUsed($media);

        return view('admin.media.where-used', compact('media', 'whereUsed'));
    }

    public function destroy(CmsMedia $media)
    {
        $whereUsed = $this->getWhereUsed($media);

        if ($whereUsed->isNotEmpty()) {
            return back()->with(
                'error',
                'This media file is still being used. Remove its references before deleting it.'
            );
        }

        // Soft delete the database record.
        // Keep the physical file for recovery.
        $media->delete();

        return redirect()
            ->route('admin.media.index')
            ->with('success', 'Media record deleted successfully.');
    }

    private function getWhereUsed(CmsMedia $media)
    {
        $uses = collect();

        CmsPage::where('featured_media_id', $media->id)
    ->get(['id', 'title', 'slug'])
    ->each(function ($page) use ($uses) {
        $uses->push([
            'type' => 'Page featured image',
            'title' => $page->title,
            'reference' => $page->slug,
        ]);
    });

        CmsHomepageSetting::where('hero_media_id', $media->id)
            ->get()
            ->each(function () use ($uses) {
                $uses->push([
                    'type' => 'Homepage hero',
                    'title' => 'Homepage',
                    'reference' => 'Homepage settings',
                ]);
            });

        CmsTestimonial::where('media_id', $media->id)
            ->get(['id', 'customer_name'])
            ->each(function ($testimonial) use ($uses) {
                $uses->push([
                    'type' => 'Testimonial',
                    'title' => $testimonial->customer_name,
                    'reference' => 'Testimonial #' . $testimonial->id,
                ]);
            });

        CmsTrustItem::where('media_id', $media->id)
            ->get(['id', 'title'])
            ->each(function ($item) use ($uses) {
                $uses->push([
                    'type' => 'Trust item',
                    'title' => $item->title,
                    'reference' => 'Trust item #' . $item->id,
                ]);
            });

        CmsMediaAttachment::with('attachable')
            ->where('media_id', $media->id)
            ->get()
            ->each(function ($attachment) use ($uses) {
                $uses->push([
                    'type' => class_basename($attachment->attachable_type),
                    'title' => $attachment->attachable
                        ? (
                            $attachment->attachable->title
                            ?? $attachment->attachable->name
                            ?? 'Content'
                        )
                        : 'Deleted content',
                    'reference' => $attachment->collection_name,
                ]);
            });

        return $uses;
    }
}
