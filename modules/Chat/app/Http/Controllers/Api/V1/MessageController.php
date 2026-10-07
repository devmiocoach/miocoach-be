<?php

namespace Modules\Chat\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Modules\Chat\Data\SendMessageData;
use Modules\Chat\Enums\MessageType;
use Modules\Chat\Http\Requests\SendMessageRequest;
use Modules\Chat\Http\Requests\UploadAttachmentRequest;
use Modules\Chat\Http\Resources\MessageResource;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\MessageAttachment;
use Modules\Chat\Policies\ConversationPolicy;
use Modules\Chat\Services\MessageService;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messages) {}

    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $this->authorizeParticipant($request, $conversation);

        $messages = $conversation->messages()
            ->with(['reads', 'attachments'])
            ->orderBy('id')
            ->paginate(50);

        return MessageResource::collection($messages);
    }

    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);

        $message = $this->messages->send(
            $conversation,
            $request->user(),
            new SendMessageData(MessageType::Text, $request->validated('body')),
        );

        return MessageResource::make($message)->response()->setStatusCode(201);
    }

    public function storeAttachment(UploadAttachmentRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);

        $message = $this->messages->sendAttachment(
            $conversation,
            $request->user(),
            $request->file('file'),
        );

        return MessageResource::make($message)->response()->setStatusCode(201);
    }

    public function downloadAttachment(Request $request, Conversation $conversation, string $attachment): StreamedResponse
    {
        $this->authorizeParticipant($request, $conversation);

        $attachmentModel = MessageAttachment::whereHas(
            'message',
            fn ($query) => $query->where('conversation_id', $conversation->id),
        )->findOrFail((int) $attachment);

        return Storage::disk($attachmentModel->disk)->download($attachmentModel->path, $attachmentModel->original_name);
    }

    public function storeWorkoutPlan(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);

        $data = $request->validate(['workout_plan_id' => ['required', 'integer']]);

        $plan = WorkoutPlan::findOrFail($data['workout_plan_id']);
        abort_unless($plan->coach_id === $conversation->coach_id, 403);

        $message = $this->messages->send(
            $conversation,
            $request->user(),
            new SendMessageData(MessageType::WorkoutPlan, null, [
                'workout_plan_id' => $plan->id,
                'name' => $plan->name,
            ]),
        );

        return MessageResource::make($message)->response()->setStatusCode(201);
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);

        $ids = $this->messages->markRead($conversation, $request->user());

        return response()->json(['message_ids' => $ids]);
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless(
            (new ConversationPolicy)->participate($request->user(), $conversation),
            403,
        );
    }
}
