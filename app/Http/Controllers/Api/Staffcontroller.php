<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    /* ── Public ──────────────────────────────────────────────────────── */

    /** GET /api/staff — active staff ordered by display_order */
    public function index(): JsonResponse
    {
        $staff = Staff::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Staff $s) => $this->fmt($s));

        return response()->json($staff);
    }

    /* ── Admin CRUD ──────────────────────────────────────────────────── */

    /** GET /api/admin/staff — all staff including inactive */
    public function adminIndex(): JsonResponse
    {
        $staff = Staff::orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Staff $s) => $this->fmt($s));

        return response()->json($staff);
    }

    /** POST /api/admin/staff — create a new staff member */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'position'      => ['required', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active'     => ['nullable', 'boolean'],
            'facebook_url'  => ['nullable', 'url', 'max:255'],
            'twitter_url'   => ['nullable', 'url', 'max:255'],
            'linkedin_url'  => ['nullable', 'url', 'max:255'],
        ]);

        // Auto-assign display_order to max + 1 when not supplied
        if (! array_key_exists('display_order', $data) || $data['display_order'] === null) {
            $data['display_order'] = (int) (Staff::max('display_order') ?? -1) + 1;
        }

        $staff = Staff::create([
            'name'          => $data['name'],
            'position'      => $data['position'],
            'display_order' => (int) $data['display_order'],
            'is_active'     => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'facebook_url'  => $data['facebook_url'] ?? null,
            'twitter_url'   => $data['twitter_url']  ?? null,
            'linkedin_url'  => $data['linkedin_url'] ?? null,
        ]);

        return response()->json(['staff' => $this->fmt($staff)], 201);
    }

    /**
     * PUT /api/admin/staff/{staff} — update fields (JSON only).
     *
     * Validation note: display_order is clamped on the frontend
     * to [0 .. currentMax], but we also enforce it here so a
     * direct API call cannot create gaps in the ordering.
     */
    public function update(Request $request, Staff $staff): JsonResponse
    {
        // Compute allowed max from the existing records (excluding this one so
        // swapping with its own position is always valid).
        $maxOrder = (int) Staff::where('id', '!=', $staff->id)->max('display_order');

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'position'      => ['sometimes', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:' . ($maxOrder + 1)],
            'is_active'     => ['nullable', 'boolean'],
            'facebook_url'  => ['nullable', 'url', 'max:255'],
            'twitter_url'   => ['nullable', 'url', 'max:255'],
            'linkedin_url'  => ['nullable', 'url', 'max:255'],
        ]);

        $staff->update([
            'name'     => $data['name']     ?? $staff->name,
            'position' => $data['position'] ?? $staff->position,

            'display_order' => array_key_exists('display_order', $data) && $data['display_order'] !== null
                ? (int) $data['display_order']
                : $staff->display_order,

            'is_active' => array_key_exists('is_active', $data) && $data['is_active'] !== null
                ? (bool) $data['is_active']
                : $staff->is_active,

            'facebook_url' => array_key_exists('facebook_url', $data)
                ? ($data['facebook_url'] ?: null)
                : $staff->facebook_url,

            'twitter_url' => array_key_exists('twitter_url', $data)
                ? ($data['twitter_url'] ?: null)
                : $staff->twitter_url,

            'linkedin_url' => array_key_exists('linkedin_url', $data)
                ? ($data['linkedin_url'] ?: null)
                : $staff->linkedin_url,
        ]);

        return response()->json(['staff' => $this->fmt($staff->fresh())]);
    }

    /** DELETE /api/admin/staff/{staff} */
    public function destroy(Staff $staff): JsonResponse
    {
        if ($staff->image) {
            Storage::disk('public')->delete($staff->image);
        }

        $staff->delete();

        return response()->json(['message' => 'Staff member deleted.']);
    }

    /* ── Photo management ────────────────────────────────────────────── */

    /** POST /api/admin/staff/{staff}/photo — upload / replace photo */
    public function uploadPhoto(Request $request, Staff $staff): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($staff->image) {
            Storage::disk('public')->delete($staff->image);
        }

        $path = $request->file('image')->store('staff', 'public');

        $staff->update(['image' => $path]);

        return response()->json([
            'message'   => 'Photo updated.',
            'image_url' => asset('storage/' . $path),
        ]);
    }

    /** DELETE /api/admin/staff/{staff}/photo — remove photo */
    public function deletePhoto(Staff $staff): JsonResponse
    {
        if ($staff->image) {
            Storage::disk('public')->delete($staff->image);
            $staff->update(['image' => null]);
        }

        return response()->json(['message' => 'Photo removed.']);
    }

    /* ── Private helpers ─────────────────────────────────────────────── */

    private function fmt(Staff $s): array
    {
        return [
            'id'            => $s->id,
            'name'          => $s->name,
            'position'      => $s->position,
            'image_url'     => $s->image ? asset('storage/' . $s->image) : null,
            'facebook_url'  => $s->facebook_url,
            'twitter_url'   => $s->twitter_url,
            'linkedin_url'  => $s->linkedin_url,
            'display_order' => $s->display_order,
            'is_active'     => (bool) $s->is_active,
            'created_at'    => $s->created_at?->toISOString(),
        ];
    }
}