<?php

namespace Modules\Chat\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Chat\Models\Message;
use Modules\Chat\Models\MessageAttachment;

/** @mixin Message */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_user_id' => $this->sender_user_id,
            'type' => $this->type->value,
            'body' => $this->body,
            'meta' => $this->meta,
            'attachments' => $this->whenLoaded(
                'attachments',
                fn () => $this->attachments
                    ->map(fn (MessageAttachment $attachment) => $this->attachmentToArray($attachment, $request))
                    ->values(),
            ),
            'is_read' => $this->reads_count > 0 || $this->relationLoaded('reads') && $this->reads->isNotEmpty(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentToArray(MessageAttachment $attachment, Request $request): array
    {
        $routeName = $request->routeIs('coach.*') ? 'coach.chat.attachments.download' : 'chat.attachments.download';

        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime' => $attachment->mime,
            'size_bytes' => $attachment->size_bytes,
            'download_url' => route($routeName, [
                'conversation' => $this->conversation_id,
                'attachment' => $attachment->id,
            ]),
        ];
    }
}
