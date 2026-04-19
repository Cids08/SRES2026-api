<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    /*
    |------------------------------------------------------------------
    | GET /api/admin/students
    |------------------------------------------------------------------
    */
    public function index(Request $request): JsonResponse
    {
        $query = Student::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name',      'like', "%{$s}%")
                  ->orWhere('last_name',      'like', "%{$s}%")
                  ->orWhere('student_number', 'like', "%{$s}%");
            });
        }

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->latest()->paginate($request->per_page ?? 15);

        $students->through(function ($student) {
            $student->profile_picture_url = $student->profile_picture
                ? asset('storage/' . $student->profile_picture)
                : null;
            return $student;
        });

        return response()->json($students);
    }

    /*
    |------------------------------------------------------------------
    | GET /api/admin/students/{id}
    |------------------------------------------------------------------
    */
    public function show($id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $student->profile_picture_url = $student->profile_picture
            ? asset('storage/' . $student->profile_picture)
            : null;

        return response()->json($student);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/admin/students/{id}/photo
    |------------------------------------------------------------------
    */
    public function uploadPhoto(Request $request, $id): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $student = Student::findOrFail($id);

        // Delete old photo
        if ($student->profile_picture) {
            Storage::disk('public')->delete($student->profile_picture);
        }

        $path = $request->file('photo')->store('student_photos', 'public');
        $student->update(['profile_picture' => $path]);

        return response()->json([
            'message'             => 'Profile photo updated.',
            'profile_picture'     => $path,
            'profile_picture_url' => asset('storage/' . $path),
        ]);
    }

    /*
    |------------------------------------------------------------------
    | DELETE /api/admin/students/{id}/photo
    |------------------------------------------------------------------
    */
    public function deletePhoto($id): JsonResponse
    {
        $student = Student::findOrFail($id);

        if ($student->profile_picture) {
            Storage::disk('public')->delete($student->profile_picture);
            $student->update(['profile_picture' => null]);
        }

        return response()->json(['message' => 'Profile photo removed.']);
    }

    /*
    |------------------------------------------------------------------
    | PATCH /api/admin/students/{id}/status
    |------------------------------------------------------------------
    */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive,graduated',
        ]);

        $student = Student::findOrFail($id);
        $student->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status updated.',
            'status'  => $student->status,
        ]);
    }
}