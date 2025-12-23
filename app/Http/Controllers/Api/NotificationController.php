<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\DeviceToken;
use App\Models\AdminConversation;
use App\Models\AdminMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    /**
     * Get user notifications (paginated)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Notification::where('user_id', $user->id);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        $notifications = $query->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'data' => $notification->data,
                        'is_read' => $notification->is_read,
                        'read_at' => $notification->read_at?->format('Y-m-d H:i:s'),
                        'created_at' => $notification->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                ]
            ]
        ]);
    }

    /**
     * Get unread notifications count (cached for 10 seconds)
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cache the count for 10 seconds to prevent repeated DB queries
        $cacheKey = "user:{$user->id}:unread_notifications_count";

        $count = Cache::remember($cacheKey, 10, function () use ($user) {
            return Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count
            ]
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        // Clear unread count cache
        Cache::forget("user:{$user->id}:unread_notifications_count");

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $updated = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        // Clear unread count cache
        Cache::forget("user:{$user->id}:unread_notifications_count");

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'updated_count' => $updated
            ]
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Delete all read notifications
     */
    public function deleteAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $deleted = Notification::where('user_id', $user->id)
            ->where('is_read', true)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'All read notifications deleted',
            'data' => [
                'deleted_count' => $deleted
            ]
        ]);
    }

    /**
     * Register/Update device FCM token
     */
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $user = $request->user();

        Log::info('=== DEVICE TOKEN REGISTRATION START ===');
        Log::info('User ID: ' . $user->id);
        Log::info('Request data: ' . json_encode($request->all()));

        $validated = $request->validate([
            'token' => 'required|string',
            'device_type' => 'nullable|string|in:ios,android',
            'device_name' => 'nullable|string|max:255',
        ]);

        Log::info('Validated token: ' . substr($validated['token'], 0, 50) . '...');
        Log::info('Device type: ' . ($validated['device_type'] ?? 'null'));

        // Check if token already exists
        $deviceToken = DeviceToken::where('token', $validated['token'])->first();

        if ($deviceToken) {
            Log::info('Token already exists, updating. ID: ' . $deviceToken->id);
            // Update existing token
            $deviceToken->update([
                'user_id' => $user->id,
                'device_type' => $validated['device_type'] ?? $deviceToken->device_type,
                'device_name' => $validated['device_name'] ?? $deviceToken->device_name,
                'is_active' => true,
                'last_used_at' => now(),
            ]);
            Log::info('Token updated successfully');
        } else {
            Log::info('Creating new token');
            // Create new token
            $deviceToken = DeviceToken::create([
                'user_id' => $user->id,
                'token' => $validated['token'],
                'device_type' => $validated['device_type'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]);
            Log::info('Token created successfully. ID: ' . $deviceToken->id);
        }

        Log::info('Final token state - ID: ' . $deviceToken->id . ', User: ' . $deviceToken->user_id . ', Active: ' . ($deviceToken->is_active ? 'true' : 'false'));
        Log::info('=== DEVICE TOKEN REGISTRATION END ===');

        return response()->json([
            'success' => true,
            'message' => 'Device token registered successfully',
            'data' => [
                'id' => $deviceToken->id,
                'device_type' => $deviceToken->device_type,
                'device_name' => $deviceToken->device_name,
            ]
        ]);
    }

    /**
     * Remove device token (logout)
     */
    public function removeDeviceToken(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $deleted = DeviceToken::where('user_id', $user->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted > 0 ? 'Device token removed successfully' : 'Device token not found'
        ]);
    }

    /**
     * TEST: Send test notification to current user
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        $user = $request->user();

        $notificationService = app(\App\Services\NotificationService::class);

        $notification = $notificationService->send(
            $user->id,
            'test',
            'Test Notification',
            'This is a test notification to verify FCM is working!',
            ['test' => true, 'timestamp' => now()->toDateTimeString()]
        );

        if ($notification) {
            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully',
                'data' => [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send test notification'
        ], 500);
    }

    /**
     * Get user's registered devices
     */
    public function getDevices(Request $request): JsonResponse
    {
        $user = $request->user();

        $devices = DeviceToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('last_used_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $devices->map(function ($device) {
                return [
                    'id' => $device->id,
                    'device_type' => $device->device_type,
                    'device_name' => $device->device_name,
                    'last_used_at' => $device->last_used_at?->format('Y-m-d H:i:s'),
                    'registered_at' => $device->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ]);
    }

    /**
     * Send message to admin
     */
    public function sendMessageToAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        DB::beginTransaction();
        try {
            // Get all users who have permission to view/manage admin conversations
            // This is determined by the admin panel's role and permission system
            // We look for users with ANY permission related to viewing customer service
            $adminUsers = \App\Models\User::whereHas('permissions', function($q) {
                $q->where('name', 'like', '%customer%')
                  ->orWhere('name', 'like', '%support%')
                  ->orWhere('name', 'like', '%chat%');
            })->orWhereHas('roles', function($q) {
                // Also include any user with admin-related roles
                $q->where('name', 'like', '%admin%')
                  ->orWhere('name', 'like', '%support%');
            })->get();

            // If no users found with specific permissions, fall back to any admin-like role
            if ($adminUsers->isEmpty()) {
                $adminUsers = \App\Models\User::whereHas('roles')->get();
            }

            // If still no users, use first user as fallback
            if ($adminUsers->isEmpty()) {
                $firstUser = \App\Models\User::orderBy('id', 'asc')->first();
                if ($firstUser && $firstUser->id !== $user->id) {
                    $adminUsers = collect([$firstUser]);
                }
            }

            foreach ($adminUsers as $admin) {
                // Note: No notification is created for admins
                // Admins will see messages in the dashboard's customer service chat section

                // Create or get admin conversation
                $conversation = AdminConversation::firstOrCreate([
                    'admin_id' => $admin->id,
                    'user_id' => $user->id,
                    'user_type' => $userType,
                ], [
                    'last_message_at' => now(),
                ]);

                // Create admin message
                $adminMessage = AdminMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'sender_type' => $userType,
                    'message_type' => 'text',
                    'content' => $validated['message'],
                    'is_read' => false,
                ]);

                // Update conversation with last message
                $conversation->update([
                    'last_message_id' => $adminMessage->id,
                    'last_message_at' => now(),
                    'admin_unread_count' => DB::raw('admin_unread_count + 1'),
                ]);
            }

            // Note: No notification copy is created for the user
            // The message will appear in the chat screen through real-time polling

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Message sent to admin successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error sending message to admin', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message to admin',
            ], 500);
        }
    }

    /**
     * Get admin conversation messages for client/provider
     */
    public function getAdminMessages(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        try {
            // Find any admin conversation for this user
            $conversation = AdminConversation::where('user_id', $user->id)
                ->where('user_type', $userType)
                ->with(['admin'])
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'conversation' => null,
                        'messages' => [],
                        'has_conversation' => false
                    ]
                ]);
            }

            // Get messages
            $messages = AdminMessage::where('conversation_id', $conversation->id)
                ->with('sender')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'content' => $message->content,
                        'sender_id' => $message->sender_id,
                        'sender_name' => $message->sender->name,
                        'sender_type' => $message->sender_type,
                        'message_type' => $message->message_type,
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            // Mark messages from admin as read
            AdminMessage::where('conversation_id', $conversation->id)
                ->where('sender_type', 'admin')
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            // Update user unread count
            $conversation->update(['user_unread_count' => 0]);

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation' => [
                        'id' => $conversation->id,
                        'admin_name' => $conversation->admin->name,
                        'last_message_at' => $conversation->last_message_at?->format('Y-m-d H:i:s'),
                        'unread_count' => 0,
                    ],
                    'messages' => $messages,
                    'has_conversation' => true
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting admin messages', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get messages',
            ], 500);
        }
    }
}
