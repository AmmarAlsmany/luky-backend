<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Determine if the current authenticated user is the sender
        $isSender = false;
        if ($request->user()) {
            $currentUserId = $request->user()->id;
            $currentUserType = get_class($request->user());
            $isSender = ($this->sender_id == $currentUserId && $this->sender_type == $currentUserType);
        }

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender_type' => $this->sender_type,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ],
            'message_type' => $this->message_type,
            'content' => $this->content,
            'image_url' => $this->image_url,
            'is_sender' => $isSender,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
