<?php

namespace Modules\Chat\Data;

use Modules\Chat\Enums\MessageType;

readonly class SendMessageData
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public MessageType $type,
        public ?string $body = null,
        public ?array $meta = null,
    ) {}
}
