<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = auth('api')->user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved',
            'data'    => NotificationResource::collection($notifications),
            'meta'    => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
            ],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $count = auth('api')->user()->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'message' => 'Unread count',
            'data'    => ['count' => $count],
        ]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $notification = auth('api')->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    public function markAllAsRead(): JsonResponse
    {
        auth('api')->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }
}
