<?php

namespace App\Http\Controllers;

use App\Models\WmsNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, WmsNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->markRead();

        return response()->json(['ok' => true]);
    }

    /**
     * Mark all unread notifications for the current user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        WmsNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
