<?php

namespace Modules\Checkins\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Modules\Checkins\Http\Controllers\Api\V1\Concerns\AuthorizesClientAccess;
use Modules\Checkins\Http\Controllers\Api\V1\Concerns\ResolvesCoach;
use Modules\Checkins\Http\Requests\CheckinHistoryRequest;
use Modules\Checkins\Http\Resources\CheckinResponseResource;
use Modules\Checkins\Models\CheckinResponse;

class CoachCheckinController extends Controller
{
    use AuthorizesClientAccess, ResolvesCoach;

    public function index(CheckinHistoryRequest $request, Client $client): JsonResponse
    {
        $this->resolveCoach($request->user());
        $this->authorizeClientAccess($request->user(), $client);

        $responses = CheckinResponse::query()
            ->with('checkinForm')
            ->where('client_id', $client->id)
            ->when($request->validated('from'), fn ($q, $from) => $q->where('submitted_at', '>=', $from))
            ->when($request->validated('to'), fn ($q, $to) => $q->where('submitted_at', '<=', $to.' 23:59:59'))
            ->latest('submitted_at')
            ->get();

        return response()->json(['data' => CheckinResponseResource::collection($responses)]);
    }
}
