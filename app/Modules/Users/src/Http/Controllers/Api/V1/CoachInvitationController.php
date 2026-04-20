<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\SendInvitationAction;
use App\Modules\Users\Http\Resources\CoachInvitationResource;
use App\Modules\Users\Models\CoachInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CoachInvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $invitations = $coach->invitations()
            ->latest()
            ->paginate(20);

        return response()->json(CoachInvitationResource::collection($invitations)->response()->getData(true));
    }

    public function send(Request $request, SendInvitationAction $action): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ]);

        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $invitation = $action->handle($coach, $request->email);

        return response()->json([
            'message'    => 'Invito inviato con successo.',
            'invitation' => new CoachInvitationResource($invitation),
        ], 201);
    }

    public function revoke(Request $request, CoachInvitation $invitation): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $invitation->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => ['Questo invito non può essere revocato.'],
            ]);
        }

        $invitation->update(['status' => 'revoked']);

        return response()->json(['message' => 'Invito revocato.']);
    }
}
