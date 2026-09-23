<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** v11: mobil ilova foydalanuvchisiga kelgan bildirishnomalar ro'yxati (sAdmin yuborgan). */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $recipients = NotificationRecipient::with('notification')->where('user_id', $request->user()->id)
            ->orderByDesc('id')->paginate(min(50, max(5, $request->integer('per_page', 20))));

        return response()->json(['success' => true, 'data' => $recipients->getCollection()->map(fn ($r) => [
            'id' => $r->notification_id,
            'title' => $r->notification->title,
            'body' => $r->notification->body,
            'data' => $r->notification->data,
            'read' => $r->read_at !== null,
            'created_at' => $r->notification->created_at->toIso8601String(),
        ]), 'meta' => [
            'page' => $recipients->currentPage(), 'last_page' => $recipients->lastPage(), 'total' => $recipients->total(),
            'unread_count' => NotificationRecipient::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
        ]]);
    }

    public function read(Request $request, int $notification): JsonResponse
    {
        $recipient = NotificationRecipient::where('user_id', $request->user()->id)->where('notification_id', $notification)->firstOrFail();

        if (! $recipient->read_at) {
            $recipient->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        NotificationRecipient::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
