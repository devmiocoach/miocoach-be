<?php

namespace Modules\Chat\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Chat\Models\Conversation;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coach' => [
                'id' => $this->coach?->id,
                'name' => $this->coach?->user?->name,
                'avatar_url' => $this->coach?->user?->avatar,
            ],
            'client' => [
                'id' => $this->client?->id,
                'name' => $this->client?->user?->name,
                'avatar_url' => $this->client?->user?->avatar,
            ],
            'last_message' => $this->latestMessage ? [
                'body' => $this->latestMessage->body,
                'type' => $this->latestMessage->type->value,
                'created_at' => $this->latestMessage->created_at?->toISOString(),
            ] : null,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'unread_count' => $this->unread_count ?? 0,
        ];
    }
}
