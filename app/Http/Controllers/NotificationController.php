<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = AdminNotification::unreadForUser()->limit(20)->get();
        $count = AdminNotification::unreadForUser()->count();

        return response()->json([
            'count'         => $count,
            'notifications' => $notifications,
        ]);
    }

    public function markRead($id)
    {
        $notification = AdminNotification::findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        AdminNotification::unreadForUser()->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}
