<?php

namespace Modules\Checkins\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Checkins\Http\Requests\RespondCheckinRequest;
use Modules\Checkins\Http\Resources\CheckinResponseResource;
use Modules\Checkins\Models\CheckinForm;
use Modules\Checkins\Models\CheckinResponse;
use Modules\Checkins\Notifications\ClientSubmittedCheckinNotification;
use Modules\Checkins\Services\Contracts\PushServiceContract;
use Symfony\Component\HttpFoundation\Response;

class CheckinResponseController extends Controller
{
    public function respond(RespondCheckinRequest $request, CheckinForm $checkinForm, PushServiceContract $push): JsonResponse
    {
        $client = $request->user()->client;
        abort_unless($client !== null, Response::HTTP_FORBIDDEN, 'Non autorizzato.');

        $isAssigned = $checkinForm->clients()->where('clients.id', $client->id)->exists();
        abort_unless($isAssigned, Response::HTTP_FORBIDDEN, 'Non autorizzato.');

        $response = CheckinResponse::create([
            'checkin_form_id' => $checkinForm->id,
            'client_id' => $client->id,
            'answers' => $request->validated('answers'),
            'submitted_at' => now(),
        ]);

        $clientName = $client->user->name;
        $coachUser = $checkinForm->coach->user;

        $coachUser->notify(new ClientSubmittedCheckinNotification($response, $clientName));
        $push->send($coachUser, 'Nuovo check-in', "{$clientName} ha compilato il check-in");

        return response()->json(['data' => new CheckinResponseResource($response->load('checkinForm'))], 201);
    }
}
