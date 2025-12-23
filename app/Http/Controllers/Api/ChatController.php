<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all conversations for the authenticated user
     */
    public function getConversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        $query = Conversation::with(['client', 'provider', 'lastMessage.sender'])
            ->orderByDesc('last_message_at');

        if ($userType === 'client') {
            $query->where('client_id', $user->id);
        } else {
            $provider = ServiceProvider::where('user_id', $user->id)->first();
            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider profile not found',
                ], 404);
            }
            $query->where('provider_id', $provider->id);
        }

        $conversations = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ConversationResource::collection($conversations),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Start or get existing conversation with a provider
     */
    public function startConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|exists:service_providers,id',
            'booking_id' => 'nullable|exists:bookings,id',
        ]);

        $user = $request->user();

        // Only clients can start conversations
        if (!$user->hasRole('client')) {
            throw ValidationException::withMessages([
                'user' => ['Only clients can start conversations.']
            ]);
        }

        // Check if conversation already exists
        $conversation = Conversation::where('client_id', $user->id)
            ->where('provider_id', $validated['provider_id'])
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'client_id' => $user->id,
                'provider_id' => $validated['provider_id'],
                'booking_id' => $validated['booking_id'] ?? null,
            ]);
        }

        $conversation->load(['client', 'provider', 'lastMessage.sender']);

        return response()->json([
            'success' => true,
            'data' => new ConversationResource($conversation),
        ], 201);
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        $conversation = Conversation::findOrFail($conversationId);

        // Check if user has access to this conversation
        if ($userType === 'client' && $conversation->client_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this conversation',
            ], 403);
        }

        if ($userType === 'provider') {
            $provider = ServiceProvider::where('user_id', $user->id)->first();
            if (!$provider || $conversation->provider_id !== $provider->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this conversation',
                ], 403);
            }
        }

        $messages = Message::with('sender')
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => MessageResource::collection($messages),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Send a text message
     */
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        $conversation = Conversation::findOrFail($conversationId);

        // Verify user has access to this conversation
        if ($userType === 'client' && $conversation->client_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($userType === 'provider') {
            $provider = ServiceProvider::where('user_id', $user->id)->first();
            if (!$provider || $conversation->provider_id !== $provider->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
            // Create message
            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'sender_type' => $userType,
                'message_type' => 'text',
                'content' => $validated['content'],
            ]);

            // Update conversation
            $recipientType = $userType === 'client' ? 'provider' : 'client';
            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);
            $conversation->incrementUnreadCount($recipientType);

            DB::commit();

            $message->load('sender');

            // Broadcast event for real-time messaging
            broadcast(new MessageSent($message))->toOthers();

            // Send push notification to recipient
            $recipientUserId = $userType === 'client'
                ? $conversation->provider->user_id
                : $conversation->client_id;

            $this->notificationService->sendChatMessage(
                $recipientUserId,
                $user->name,
                $validated['content'],
                $conversation->id,
                'text'
            );

            return response()->json([
                'success' => true,
                'data' => new MessageResource($message),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send an image message
     */
    public function sendImage(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        $conversation = Conversation::findOrFail($conversationId);

        // Verify user has access to this conversation
        if ($userType === 'client' && $conversation->client_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($userType === 'provider') {
            $provider = ServiceProvider::where('user_id', $user->id)->first();
            if (!$provider || $conversation->provider_id !== $provider->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
            // Create message first
            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'sender_type' => $userType,
                'message_type' => 'image',
            ]);

            // Store and optimize image using Spatie Media Library
            // This automatically creates optimized (1200px @ 85%) and thumb (300px @ 80%) versions
            $message->addMediaFromRequest('image')
                ->toMediaCollection('chat_image');

            // Update conversation
            $recipientType = $userType === 'client' ? 'provider' : 'client';
            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);
            $conversation->incrementUnreadCount($recipientType);

            DB::commit();

            $message->load('sender');

            // Broadcast event for real-time messaging
            broadcast(new MessageSent($message))->toOthers();

            // Send push notification to recipient
            $recipientUserId = $userType === 'client'
                ? $conversation->provider->user_id
                : $conversation->client_id;

            $this->notificationService->sendChatMessage(
                $recipientUserId,
                $user->name,
                '📷 Image',
                $conversation->id,
                'image'
            );

            return response()->json([
                'success' => true,
                'data' => new MessageResource($message),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark a specific message as read
     */
    public function markMessageAsRead(Request $request, int $messageId): JsonResponse
    {
        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        $message = Message::findOrFail($messageId);
        $conversation = $message->conversation;

        // Verify user has access and is the recipient
        if ($userType === 'client' && $conversation->client_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($userType === 'provider') {
            $provider = ServiceProvider::where('user_id', $user->id)->first();
            if (!$provider || $conversation->provider_id !== $provider->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        // Only mark as read if the user is NOT the sender
        if ($message->sender_id !== $user->id) {
            $message->markAsRead();
            // Broadcast message read event
            broadcast(new MessageRead($message))->toOthers();
        }

        return response()->json([
            'success' => true,
            'data' => new MessageResource($message),
        ]);
    }

    /**
     * Mark all messages in a conversation as read
     */
    public function markConversationAsRead(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        $conversation = Conversation::findOrFail($conversationId);

        // Verify user has access to this conversation
        if ($userType === 'client' && $conversation->client_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($userType === 'provider') {
            $provider = ServiceProvider::where('user_id', $user->id)->first();
            if (!$provider || $conversation->provider_id !== $provider->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        // Mark all unread messages as read
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        // Reset unread count
        $conversation->resetUnreadCount($userType);

        return response()->json([
            'success' => true,
            'message' => 'All messages marked as read',
        ]);
    }

    /**
     * Get total unread messages count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        if ($userType === 'client') {
            $totalUnread = Conversation::where('client_id', $user->id)
                ->sum('client_unread_count');
        } else {
            $provider = ServiceProvider::where('user_id', $user->id)->first();
            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider profile not found',
                ], 404);
            }
            $totalUnread = Conversation::where('provider_id', $provider->id)
                ->sum('provider_unread_count');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $totalUnread,
            ],
        ]);
    }

    /**
     * Broadcast typing indicator
     */
    public function sendTypingIndicator(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        $user = $request->user();
        $userType = $user->hasRole('client') ? 'client' : 'provider';

        $conversation = Conversation::findOrFail($conversationId);

        // Verify user has access to this conversation
        if ($userType === 'client' && $conversation->client_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($userType === 'provider') {
            $provider = ServiceProvider::where('user_id', $user->id)->first();
            if (!$provider || $conversation->provider_id !== $provider->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        // Broadcast typing indicator
        broadcast(new UserTyping($user, $conversationId, $validated['is_typing']))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Typing indicator sent',
        ]);
    }
}
