<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'students'    => Student::count(),
            'enrollments' => Enrollment::count(),
            'pending'     => Enrollment::where('status', 'pending')->count(),
            'approved'    => Enrollment::where('status', 'approved')->count(),
            'rejected'    => Enrollment::where('status', 'rejected')->count(),
            'faculty'     => User::where('role', 'faculty')->count(),
        ]);
    }
}