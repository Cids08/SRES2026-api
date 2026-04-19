<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    /* ══════════════════════════════════════════════════════════════════
     |  PUBLIC ROUTES  (no auth)
     ══════════════════════════════════════════════════════════════════ */

    public function publicIndex(Request $request): JsonResponse
    {
        $query = Announcement::with('category')
            ->published()
            ->ofType('announcement')
            ->latest('posted_at');

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }
        if ($request->filled('importance')) {
            $query->where('importance', $request->input('importance'));
        }
        // Filter by show_on_homepage if requested (used by Home page)
        if ($request->filled('show_on_homepage')) {
            $query->where('show_on_homepage', (bool) $request->input('show_on_homepage'));
        }

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json(
            $query->paginate($perPage)->through(fn ($a) => $this->formatAnnouncement($a))
        );
    }

    public function publicShow(Announcement $announcement): JsonResponse
    {
        if (! $announcement->is_published || $announcement->type !== 'announcement') {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $announcement->increment('view_count');
        return response()->json($this->formatAnnouncement($announcement->load('category')));
    }

    public function publicNewsIndex(Request $request): JsonResponse
    {
        $query = Announcement::with('category')
            ->published()
            ->ofType('news')
            ->latest('posted_at');

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }
        // Filter by show_on_homepage if requested (used by Home page)
        if ($request->filled('show_on_homepage')) {
            $query->where('show_on_homepage', (bool) $request->input('show_on_homepage'));
        }

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json(
            $query->paginate($perPage)->through(fn ($n) => $this->formatNews($n))
        );
    }

    public function publicNewsShow(Announcement $announcement): JsonResponse
    {
        if (! $announcement->is_published || $announcement->type !== 'news') {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $announcement->increment('view_count');
        return response()->json($this->formatNews($announcement->load('category')));
    }

    public function publicCategories(): JsonResponse
    {
        return response()->json(
            AnnouncementCategory::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
        );
    }

    /* ── Private format helpers ──────────────────────────────────────── */

    private function formatAnnouncement(Announcement $a): array
    {
        return [
            'id'               => $a->id,
            'type'             => $a->type,
            'category'         => $a->category ? [
                'id'   => $a->category->id,
                'name' => $a->category->name,
                'slug' => $a->category->slug,
            ] : null,
            'title'            => $a->title,
            'content'          => $a->content,
            'details'          => $a->details ?? '',
            'importance'       => $a->importance,
            'image_url'        => $a->image ? asset('storage/' . $a->image) : null,
            'show_on_homepage' => (bool) $a->show_on_homepage,
            'date'             => $a->posted_at?->format('F j, Y'),
            'posted_at'        => $a->posted_at?->toISOString(),
            'expires_at'       => $a->expires_at?->format('F j, Y'),
            'view_count'       => $a->view_count,
            'created_by'       => $a->created_by,
        ];
    }

    private function formatNews(Announcement $n): array
    {
        return [
            'id'               => $n->id,
            'type'             => $n->type,
            'category'         => $n->category ? [
                'id'    => $n->category->id,
                'name'  => $n->category->name,
                'slug'  => $n->category->slug,
            ] : null,
            'title'            => $n->title,
            'content'          => $n->content,
            'details'          => $n->details ?? '',
            'date'             => $n->posted_at?->format('F j, Y'),
            'posted_at'        => $n->posted_at?->toISOString(),
            'is_featured'      => $n->is_featured,
            'show_on_homepage' => (bool) $n->show_on_homepage,
            'event_date'       => $n->event_date?->format('F j, Y'),
            'event_location'   => $n->event_location,
            'image_url'        => $n->image ? asset('storage/' . $n->image) : null,
            'view_count'       => $n->view_count,
            'created_by'       => $n->created_by,
        ];
    }

    /* ══════════════════════════════════════════════════════════════════
     |  ADMIN ROUTES  (auth:sanctum)
     ══════════════════════════════════════════════════════════════════ */

    public function index(Request $request): JsonResponse
    {
        $query = Announcement::with('category')->latest('posted_at');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title',   'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
        if ($request->filled('importance')) {
            $query->where('importance', $request->input('importance'));
        }
        if ($request->filled('is_published')) {
            $query->where('is_published', (bool) $request->input('is_published'));
        }
        if ($request->filled('show_on_homepage')) {
            $query->where('show_on_homepage', (bool) $request->input('show_on_homepage'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json(
            $query->paginate($perPage)->through(function ($a) {
                $data = $a->toArray();
                $data['image_url']        = $a->image ? asset('storage/' . $a->image) : null;
                $data['show_on_homepage'] = (bool) $a->show_on_homepage;
                return $data;
            })
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeEmptyDates($request, ['expires_at', 'event_date']);

        $type = $request->input('type', 'announcement');

        $rules = [
            'type'             => ['required', Rule::in(['announcement', 'news'])],
            'category_id'      => ['required', 'exists:announcement_categories,id'],
            'title'            => ['required', 'string', 'max:255'],
            'content'          => ['required', 'string'],
            'details'          => ['nullable', 'string'],
            'posted_at'        => ['required', 'date'],
            'expires_at'       => ['nullable', 'date', 'after:posted_at'],
            'is_published'     => ['required', 'boolean'],
            'show_on_homepage' => ['sometimes', 'boolean'],
            'image'            => ['nullable', 'image', 'max:4096'],
        ];

        if ($type === 'announcement') {
            $rules['importance'] = ['required', Rule::in(['high', 'medium', 'low'])];
        } else {
            $rules['is_featured']    = ['sometimes', 'boolean'];
            $rules['event_date']     = ['nullable', 'date'];
            $rules['event_location'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('announcements', 'public');
        }

        $data['created_by'] = auth()->user()?->name ?? 'Admin';

        $announcement = Announcement::create($data);
        $announcement->load('category');

        $result = $announcement->toArray();
        $result['image_url']        = $announcement->image ? asset('storage/' . $announcement->image) : null;
        $result['show_on_homepage'] = (bool) $announcement->show_on_homepage;

        return response()->json([
            'message'      => 'Created successfully.',
            'announcement' => $result,
        ], 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $announcement->load('category');
        $data = $announcement->toArray();
        $data['image_url']        = $announcement->image ? asset('storage/' . $announcement->image) : null;
        $data['show_on_homepage'] = (bool) $announcement->show_on_homepage;
        return response()->json($data);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $this->normalizeEmptyDates($request, ['expires_at', 'event_date']);

        $type = $announcement->type;

        $rules = [
            'category_id'      => ['sometimes', 'exists:announcement_categories,id'],
            'title'            => ['sometimes', 'string', 'max:255'],
            'content'          => ['sometimes', 'string'],
            'details'          => ['nullable', 'string'],
            'posted_at'        => ['sometimes', 'date'],
            'expires_at'       => ['nullable', 'date', 'after:posted_at'],
            'is_published'     => ['sometimes', 'boolean'],
            'show_on_homepage' => ['sometimes', 'boolean'],
            'image'            => ['nullable', 'image', 'max:4096'],
        ];

        if ($type === 'announcement') {
            $rules['importance'] = ['sometimes', Rule::in(['high', 'medium', 'low'])];
        } else {
            $rules['is_featured']    = ['sometimes', 'boolean'];
            $rules['event_date']     = ['nullable', 'date'];
            $rules['event_location'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        if ($request->hasFile('image')) {
            if ($announcement->image) {
                Storage::disk('public')->delete($announcement->image);
            }
            $data['image'] = $request->file('image')->store('announcements', 'public');
        }

        $announcement->update($data);
        $announcement->load('category');

        $result = $announcement->toArray();
        $result['image_url']        = $announcement->image ? asset('storage/' . $announcement->image) : null;
        $result['show_on_homepage'] = (bool) $announcement->show_on_homepage;

        return response()->json([
            'message'      => 'Updated successfully.',
            'announcement' => $result,
        ]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        if ($announcement->image) {
            Storage::disk('public')->delete($announcement->image);
        }
        $announcement->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    /* ══════════════════════════════════════════════════════════════════
     |  CATEGORY ROUTES
     ══════════════════════════════════════════════════════════════════ */

    public function categoriesIndex(): JsonResponse
    {
        return response()->json(
            AnnouncementCategory::orderBy('name')->get()
        );
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:announcement_categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['required', 'boolean'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        $cat = AnnouncementCategory::create($data);

        return response()->json(['message' => 'Category created.', 'category' => $cat], 201);
    }

    public function categoriesUpdate(Request $request, AnnouncementCategory $cat): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255', Rule::unique('announcement_categories', 'name')->ignore($cat->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $cat->update($data);

        return response()->json(['message' => 'Category updated.', 'category' => $cat]);
    }

    public function categoriesDestroy(AnnouncementCategory $cat): JsonResponse
    {
        $count = $cat->announcements()->count();
        if ($count > 0) {
            return response()->json([
                'message' => "Cannot delete — {$count} item(s) use this category.",
            ], 422);
        }

        $cat->delete();
        return response()->json(['message' => 'Category deleted.']);
    }

    /* ── Private helpers ─────────────────────────────────────────────── */

    private function normalizeEmptyDates(Request $request, array $fields): void
    {
        $merge = [];
        foreach ($fields as $field) {
            $value = $request->input($field);
            if ($value !== null && trim((string) $value) === '') {
                $merge[$field] = null;
            }
        }
        if ($merge) {
            $request->merge($merge);
        }
    }
}