<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Document;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EnrollmentController extends Controller
{
    /*
    |------------------------------------------------------------------
    | GET /api/admin/enrollments
    |------------------------------------------------------------------
    */
    public function index(Request $request): JsonResponse
    {
        $query = Enrollment::query();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->grade_level) {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name',  'like', "%{$request->search}%")
                  ->orWhere('email',      'like', "%{$request->search}%");
            });
        }

        return response()->json(
            $query->latest()->paginate($request->per_page ?? 15)
        );
    }

    /*
    |------------------------------------------------------------------
    | GET /api/admin/enrollments/{id}
    |------------------------------------------------------------------
    */
    public function show($id): JsonResponse
    {
        $enrollment = Enrollment::with('documents')->findOrFail($id);
        return response()->json($enrollment);
    }

    /*
    |------------------------------------------------------------------
    | PATCH /api/admin/enrollments/{id}/status
    |------------------------------------------------------------------
    */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $enrollment = Enrollment::findOrFail($id);

        $enrollment->update(['status' => $request->status]);

        if ($request->status === 'approved') {
            if (!Student::where('enrollment_id', $enrollment->id)->exists()) {
                Student::create([
                    'enrollment_id'  => $enrollment->id,
                    'student_number' => 'S-' . now()->year . '-' . str_pad(Student::max('id') + 1 ?? 1, 5, '0', STR_PAD_LEFT),
                    'first_name'     => $enrollment->first_name,
                    'middle_name'    => $enrollment->middle_name,
                    'last_name'      => $enrollment->last_name,
                    'grade_level'    => $enrollment->grade_level,
                    'section'        => null,
                    'gender'         => $enrollment->gender,
                    'date_of_birth'  => $enrollment->date_of_birth,
                    'parent_name'    => $enrollment->parent_name,
                    'contact_number' => $enrollment->mobile_number,
                    'address'        => $enrollment->address,
                    'status'         => 'active',
                ]);
            }
        }

        return response()->json([
            'message'    => 'Status updated successfully.',
            'enrollment' => $enrollment,
        ]);
    }

    /*
    |------------------------------------------------------------------
    | POST /api/enroll  — PUBLIC
    |------------------------------------------------------------------
    */
    public function store(Request $request): JsonResponse
    {
        // ── Honeypot — bots fill this, real users never see it ──
        if ($request->filled('honeypot')) {
            // Fake success — don't reveal the block to bots
            return response()->json([
                'message'       => 'Enrollment submitted successfully.',
                'enrollment_id' => 0,
            ], 201);
        }

        $data = $request->validate([
            'student_type'           => 'required|string|max:50',
            'grade_level'            => 'required|string|max:50',
            'first_name'             => 'required|string|max:100',
            'middle_name'            => 'nullable|string|max:100',
            'last_name'              => 'required|string|max:100',
            'date_of_birth'          => 'required|date|before:today',
            'gender'                 => 'required|string|max:20',
            'student_email'          => 'nullable|email:rfc,dns|max:150',
            'previous_school'        => 'nullable|string|max:255',
            'special_needs'          => 'nullable|string|max:500',
            'parent_name'            => 'required|string|max:150',
            'relationship'           => 'required|string|max:50',
            'mobile_number'          => 'required|string|max:20',
            'landline'               => 'nullable|string|max:20',
            'email'                  => 'required|email:rfc,dns|max:150',
            'address'                => 'required|string|max:500',
            'emergency_name'         => 'required|string|max:150',
            'emergency_relationship' => 'required|string|max:50',
            'emergency_phone'        => 'required|string|max:20',
            'has_id_pictures'        => 'required|boolean',
            'agreement'              => 'required|boolean',
            'documents'              => 'nullable|array|max:10',
            'documents.*'            => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
            'document_types'         => 'nullable|array',
            'document_types.*'       => 'string|max:100',
        ]);

        $enrollment = Enrollment::create([
            ...$data,
            'status'            => 'pending',
            'confirmed_details' => true,
        ]);

        if ($request->hasFile('documents')) {
            $folder = $enrollment->id . '_' . Str::slug($enrollment->last_name);

            foreach ($request->file('documents') as $index => $file) {
                $docType  = $request->input("document_types.$index", 'Document');
                $fileName = Str::slug($docType) . '-' . time() . '.' . $file->getClientOriginalExtension();
                $path     = $file->storeAs("student_documents/{$folder}", $fileName, 'public');

                Document::create([
                    'enrollment_id'  => $enrollment->id,
                    'document_type'  => $docType,
                    'file_name'      => $file->getClientOriginalName(),
                    'file_path'      => $path,
                    'file_size'      => $file->getSize(),
                    'file_extension' => $file->getClientOriginalExtension(),
                ]);
            }
        }

        try {
            Mail::raw(
                "Enrollment received for {$enrollment->first_name} {$enrollment->last_name}",
                function ($message) use ($enrollment) {
                    $message->to($enrollment->email)
                            ->subject('Enrollment Received — San Roque Elementary School');
                }
            );
        } catch (\Exception $e) {
            \Log::warning('Enrollment email failed: ' . $e->getMessage());
        }

        return response()->json([
            'message'       => 'Enrollment submitted successfully.',
            'enrollment_id' => $enrollment->id,
        ], 201);
    }
}