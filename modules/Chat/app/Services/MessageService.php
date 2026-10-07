<?php

namespace Modules\Chat\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Chat\Data\SendMessageData;
use Modules\Chat\Enums\MessageType;
use Modules\Chat\Events\MessageRead as MessageReadEvent;
use Modules\Chat\Events\MessageSent;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Chat\Models\MessageRead;

class MessageService
{
    public function send(Conversation $conversation, User $sender, SendMessageData $data): Message
    {
        $message = DB::transaction(function () use ($conversation, $sender, $data) {
            $message = $conversation->messages()->create([
                'sender_user_id' => $sender->id,
                'type' => $data->type,
                'body' => $data->body,
                'meta' => $data->meta,
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            return $message;
        });

        MessageSent::dispatch($message);

        return $message;
    }

    public function sendAttachment(Conversation $conversation, User $sender, UploadedFile $file): Message
    {
        $isImage = str_starts_with((string) $file->getMimeType(), 'image/');
        $originalName = $file->getClientOriginalName();
        $mime = (string) $file->getMimeType();
        $size = $file->getSize();
        $path = $file->store('chat/'.$conversation->id, 'local');

        try {
            $message = DB::transaction(function () use ($conversation, $sender, $isImage, $originalName, $mime, $size, $path) {
                $message = $conversation->messages()->create([
                    'sender_user_id' => $sender->id,
                    'type' => $isImage ? MessageType::Image : MessageType::File,
                    'body' => null,
                    'meta' => [
                        'original_name' => $originalName,
                        'mime' => $mime,
                        'size_bytes' => $size,
                    ],
                ]);

                $attachment = $message->attachments()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $originalName,
                    'mime' => $mime,
                    'size_bytes' => $size,
                ]);

                $message->update([
                    'meta' => array_merge($message->meta ?? [], ['attachment_id' => $attachment->id]),
                ]);

                $conversation->forceFill(['last_message_at' => now()])->save();

                return $message->load('attachments');
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            throw $e;
        }

        MessageSent::dispatch($message);

        return $message;
    }

    /**
     * @return array<int, int>
     */
    public function markRead(Conversation $conversation, User $reader): array
    {
        $ids = $conversation->messages()
            ->where('sender_user_id', '!=', $reader->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $reader->id))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return [];
        }

        $now = now();
        MessageRead::insert(array_map(fn (int $id) => [
            'message_id' => $id,
            'user_id' => $reader->id,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $ids));

        MessageReadEvent::dispatch($conversation->id, $reader->id, $ids);

        return $ids;
    }

    public function unreadCountFor(Conversation $conversation, User $user): int
    {
        return $conversation->messages()
            ->where('sender_user_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }
}
