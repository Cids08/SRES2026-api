<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactMessageController extends Controller
{
    public function counts(): JsonResponse
    {
        return response()->json([
            'unread' => ContactMessage::where('is_read', false)->count(),
            'total'  => ContactMessage::count(),
        ]);
    }
}