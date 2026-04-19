<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class GalleryController extends Controller
{
    /* ══════════════════════════════════════════════════════════════════
     |  PUBLIC ROUTES
     ══════════════════════════════════════════════════════════════════ */

    /**
     * GET /api/gallery
     */
    public function index(): JsonResponse
    {
        $albums = Album::where('is_active', true)
            ->withCount('photos')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($album) => $this->formatAlbum($album));

        return response()->json($albums);
    }

    /**
     * GET /api/gallery/{album}
     */
    public function show(Album $album): JsonResponse
    {
        if (! $album->is_active) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $album->load('photos');

        return response()->json($this->formatAlbumWithPhotos($album));
    }

    /**
     * GET /api/gallery/{album}/download
     * Streams a ZIP of all photos in the album.
     */
    public function downloadAlbumZip(Album $album)
    {
        if (! $album->is_active) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $photos = $album->photos()->orderBy('display_order')->orderBy('created_at')->get();

        if ($photos->isEmpty()) {
            return response()->json(['message' => 'This album has no photos.'], 404);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'album_') . '.zip';
        $zip     = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Could not create ZIP file.'], 500);
        }

        foreach ($photos as $index => $photo) {
            $storagePath = Storage::disk('public')->path($photo->filename);

            if (! file_exists($storagePath)) {
                continue;
            }

            $ext      = pathinfo($photo->filename, PATHINFO_EXTENSION);
            $baseName = $photo->original_filename
                ? pathinfo($photo->original_filename, PATHINFO_FILENAME)
                : ($photo->title ?: 'photo-' . ($index + 1));

            $safeName = Str::slug($baseName) . '.' . $ext;
            $zipName  = ($index + 1) . '_' . $safeName;

            $zip->addFile($storagePath, $zipName);
        }

        $zip->close();

        if (! file_exists($zipPath)) {
            return response()->json(['message' => 'Failed to build ZIP.'], 500);
        }

        $albumSlug    = $album->slug ?: Str::slug($album->title);
        $downloadName = $albumSlug . '-photos.zip';

        return response()->download($zipPath, $downloadName, [
            'Content-Type'                => 'application/zip',
            'Content-Disposition'         => 'attachment; filename="' . $downloadName . '"',
            'Access-Control-Allow-Origin' => '*',
        ])->deleteFileAfterSend(true);
    }

    /**
     * ✅ NEW: GET /api/gallery/{album}/photos/{photo}/download
     *
     * Streams a single photo as a forced file download.
     * This bypasses CORS entirely — the frontend just navigates to this URL
     * instead of using fetch(). The server sends Content-Disposition: attachment
     * so the browser saves the file directly without opening a new tab.
     */
    public function downloadPhoto(Album $album, Photo $photo)
    {
        if (! $album->is_active) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($photo->album_id !== $album->id) {
            return response()->json(['message' => 'Photo not found in this album.'], 404);
        }

        $storagePath = Storage::disk('public')->path($photo->filename);

        if (! file_exists($storagePath)) {
            return response()->json(['message' => 'File not found on server.'], 404);
        }

        $ext          = pathinfo($photo->filename, PATHINFO_EXTENSION) ?: 'jpg';
        $baseName     = $photo->original_filename
            ? pathinfo($photo->original_filename, PATHINFO_FILENAME)
            : ($photo->title ?: 'photo-' . $photo->id);

        $downloadName = Str::slug($baseName) . '.' . $ext;

        $mimeType = mime_content_type($storagePath) ?: 'image/jpeg';

        return response()->download($storagePath, $downloadName, [
            'Content-Type'                => $mimeType,
            'Content-Disposition'         => 'attachment; filename="' . $downloadName . '"',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════
     |  ADMIN ROUTES  (auth:sanctum)
     ══════════════════════════════════════════════════════════════════ */

    /**
     * GET /api/admin/albums
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Album::withCount('photos')->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title',       'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        return response()->json($query->get()->map(fn ($a) => $this->formatAlbum($a)));
    }

    /**
     * POST /api/admin/albums
     */
    public function adminStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['required', 'boolean'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $data['slug'] = $this->uniqueSlug($data['title']);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')
                ->store('albums/covers', 'public');
        }

        $album = Album::create($data);

        return response()->json([
            'message' => 'Album created.',
            'album'   => $this->formatAlbum($album->loadCount('photos')),
        ], 201);
    }

    /**
     * PUT /api/admin/albums/{album}
     */
    public function adminUpdate(Request $request, Album $album): JsonResponse
    {
        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
        ]);

        if (isset($data['title']) && $data['title'] !== $album->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $album->id);
        }

        if ($request->hasFile('cover_image')) {
            if ($album->cover_image) {
                Storage::disk('public')->delete($album->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')
                ->store('albums/covers', 'public');
        }

        $album->update($data);

        return response()->json([
            'message' => 'Album updated.',
            'album'   => $this->formatAlbum($album->loadCount('photos')),
        ]);
    }

    /**
     * DELETE /api/admin/albums/{album}
     */
    public function adminDestroy(Album $album): JsonResponse
    {
        if ($album->cover_image) {
            Storage::disk('public')->delete($album->cover_image);
        }

        foreach ($album->photos as $photo) {
            Storage::disk('public')->delete($photo->filename);
        }

        $album->delete();

        return response()->json(['message' => 'Album deleted.']);
    }

    /* ── Photos ── */

    /**
     * GET /api/admin/albums/{album}/photos
     */
    public function photosIndex(Album $album): JsonResponse
    {
        $photos = $album->photos()
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($p) => $this->formatPhoto($p));

        return response()->json($photos);
    }

    /**
     * POST /api/admin/albums/{album}/photos
     */
    public function photosStore(Request $request, Album $album): JsonResponse
    {
        $request->validate([
            'photo'   => ['required', 'image', 'max:5120'],
            'title'   => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
        ]);

        $file     = $request->file('photo');
        $filename = $file->store('albums/' . $album->id, 'public');

        $photo = Photo::create([
            'album_id'          => $album->id,
            'filename'          => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'title'             => $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'caption'           => $request->input('caption'),
            'mime_type'         => $file->getMimeType(),
            'file_size'         => $file->getSize(),
            'display_order'     => $album->photos()->count(),
        ]);

        $album->increment('photo_count');

        return response()->json([
            'message' => 'Photo uploaded.',
            'photo'   => $this->formatPhoto($photo),
        ], 201);
    }

    /**
     * DELETE /api/admin/albums/{album}/photos/{photo}
     */
    public function photosDestroy(Album $album, Photo $photo): JsonResponse
    {
        if ($photo->album_id !== $album->id) {
            return response()->json(['message' => 'Photo not found in this album.'], 404);
        }

        Storage::disk('public')->delete($photo->filename);

        if ($album->cover_image === $photo->filename) {
            $album->update(['cover_image' => null]);
        }

        $photo->delete();
        $album->decrement('photo_count');

        return response()->json(['message' => 'Photo deleted.']);
    }

    /**
     * PATCH /api/admin/albums/{album}/photos/{photo}/cover
     */
    public function photosSetCover(Album $album, Photo $photo): JsonResponse
    {
        if ($photo->album_id !== $album->id) {
            return response()->json(['message' => 'Photo not found in this album.'], 404);
        }

        $album->photos()->update(['is_featured' => false]);
        $photo->update(['is_featured' => true]);
        $album->update(['cover_image' => $photo->filename]);

        return response()->json([
            'message' => 'Cover updated.',
            'album'   => $this->formatAlbum($album->loadCount('photos')),
        ]);
    }

    /* ── Private helpers ── */

    private function formatAlbum($a): array
    {
        return [
            'id'          => $a->id,
            'slug'        => $a->slug,
            'title'       => $a->title,
            'description' => $a->description,
            'is_active'   => (bool) $a->is_active,
            'cover_url'   => $a->cover_image
                                ? asset('storage/' . $a->cover_image)
                                : null,
            'photo_count' => $a->photos_count ?? $a->photo_count ?? 0,
            'created_at'  => isset($a->created_at)
                                ? (is_string($a->created_at) ? $a->created_at : $a->created_at?->toISOString())
                                : null,
            'updated_at'  => isset($a->updated_at)
                                ? (is_string($a->updated_at) ? $a->updated_at : $a->updated_at?->toISOString())
                                : null,
        ];
    }

    private function formatAlbumWithPhotos($a): array
    {
        return array_merge($this->formatAlbum($a), [
            'photos' => $a->photos
                ->sortBy('display_order')
                ->map(fn ($p) => $this->formatPhoto($p))
                ->values(),
        ]);
    }

    private function formatPhoto(Photo $p): array
    {
        return [
            'id'            => $p->id,
            'album_id'      => $p->album_id,
            'url'           => asset('storage/' . $p->filename),
            'title'         => $p->title,
            'caption'       => $p->caption,
            'alt_text'      => $p->alt_text,
            'filename'      => $p->original_filename ?? basename($p->filename),
            'is_featured'   => (bool) $p->is_featured,
            'file_size'     => $p->file_size,
            'display_order' => $p->display_order,
            'created_at'    => $p->created_at?->toISOString(),
        ];
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i    = 1;

        while (
            Album::where('slug', $slug)
                 ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                 ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}