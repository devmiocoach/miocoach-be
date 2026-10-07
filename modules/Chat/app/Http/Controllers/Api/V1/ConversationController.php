<?php

namespace Modules\Chat\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Chat\Http\Resources\ConversationResource;
use Modules\Chat\Services\ConversationService;
use Modules\Chat\Services\MessageService;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly MessageService $messages,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = $this->conversations->forUser($user)
            ->each(fn ($c) => $c->unread_count = $this->messages->unreadCountFor($c, $user));

        return ConversationResource::collection($conversations);
    }
}
