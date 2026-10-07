<?php

namespace Modules\Chat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Chat\Database\Factories\MessageFactory;
use Modules\Chat\Enums\MessageType;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = ['conversation_id', 'sender_user_id', 'type', 'body', 'meta'];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'meta' => 'array',
        ];
    }

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }
}
