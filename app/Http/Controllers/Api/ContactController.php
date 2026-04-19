<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactReply;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * POST /api/contact
     * Public — anyone can submit a contact message.
     */
    public function store(Request $request): JsonResponse
    {
        // Honeypot — bots fill hidden fields, humans don't
        if ($request->filled('honeypot')) {
            // Silently fake success so bots don't know they were blocked
            return response()->json([
                'message' => 'Message received. We will respond within 1–2 school days.',
            ], 201);
        }

        $data = $request->validate([
            'sender_type'   => 'required|in:parent,student,teacher,other',
            'name'          => 'required|string|max:100',
            'email'         => 'required|email:rfc,dns|max:100',
            'grade_section' => 'nullable|string|max:60',
            'subject'       => 'required|string|max:150',
            'message'       => 'required|string|min:10|max:2000',
        ]);

        ContactMessage::create($data);

        return response()->json([
            'message' => 'Message received. We will respond within 1–2 school days.',
        ], 201);
    }

    /**
     * GET /api/admin/contact-messages/counts
     */
    public function counts(): JsonResponse
    {
        $total   = ContactMessage::count();
        $unread  = ContactMessage::where('status', 'unread')->count();
        $read    = ContactMessage::where('status', 'read')->count();
        $replied = ContactMessage::where('status', 'replied')->count();

        return response()->json(compact('total', 'unread', 'read', 'replied'));
    }

    /**
     * GET /api/admin/contact-messages
     */
    public function index(Request $request): JsonResponse
    {
        $messages = ContactMessage::query()
            ->when($request->status,      fn ($q, $v) => $q->where('status', $v))
            ->when($request->sender_type, fn ($q, $v) => $q->where('sender_type', $v))
            ->when($request->search,      fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('name',    'like', "%{$v}%")
                  ->orWhere('email',   'like', "%{$v}%")
                  ->orWhere('subject', 'like', "%{$v}%");
            }))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($messages);
    }

    /**
     * GET /api/admin/contact-messages/{contactMessage}
     */
    public function show(ContactMessage $contactMessage): JsonResponse
    {
        if ($contactMessage->status === 'unread') {
            $contactMessage->update([
                'read_at' => now(),
                'status'  => 'read',
            ]);
        }

        return response()->json($contactMessage->fresh());
    }

    /**
     * POST /api/admin/contact-messages/{contactMessage}/reply
     */
    public function reply(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:3000',
        ]);

        Mail::to($contactMessage->email)
            ->send(new ContactReply($contactMessage, $data['body']));

        $contactMessage->update([
            'status'  => 'replied',
            'read_at' => $contactMessage->read_at ?? now(),
        ]);

        return response()->json([
            'message'         => 'Reply sent successfully.',
            'contact_message' => $contactMessage->fresh(),
        ]);
    }

    /**
     * PATCH /api/admin/contact-messages/{contactMessage}/replied
     */
    public function markReplied(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->update(['status' => 'replied']);
        return response()->json(['message' => 'Marked as replied.']);
    }

    /**
     * DELETE /api/admin/contact-messages/{contactMessage}
     */
    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();
        return response()->json(['message' => 'Message deleted.']);
    }
}