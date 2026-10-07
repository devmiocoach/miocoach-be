<?php

namespace Modules\Checkins\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Checkins\Http\Controllers\Api\V1\Concerns\ResolvesCoach;
use Modules\Checkins\Http\Requests\AssignCheckinFormRequest;
use Modules\Checkins\Http\Requests\StoreCheckinFormRequest;
use Modules\Checkins\Http\Requests\UpdateCheckinFormRequest;
use Modules\Checkins\Http\Resources\CheckinFormResource;
use Modules\Checkins\Models\CheckinForm;

class CheckinFormController extends Controller
{
    use ResolvesCoach;

    public function index(): JsonResponse
    {
        $coach = $this->resolveCoach(request()->user());

        $forms = CheckinForm::query()
            ->where('coach_id', $coach->id)
            ->with('clients:id')
            ->latest()
            ->get();

        return response()->json(['data' => CheckinFormResource::collection($forms)]);
    }

    public function store(StoreCheckinFormRequest $request): JsonResponse
    {
        $coach = $this->resolveCoach($request->user());
        $clientIds = $request->validated('clientIds', []);

        if (! empty($clientIds)) {
            $this->assertCoachOwnsClients($coach, $clientIds);
        }

        $form = DB::transaction(function () use ($coach, $request, $clientIds) {
            $form = CheckinForm::create([
                'coach_id' => $coach->id,
                'title' => $request->validated('title'),
                'frequency' => $request->validated('frequency'),
                'send_time' => $request->validated('sendTime'),
                'questions' => $request->validated('questions'),
                'is_active' => true,
            ]);

            if (! empty($clientIds)) {
                $form->clients()->attach(
                    collect($clientIds)->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->all()
                );
            }

            return $form;
        });

        return response()->json(['data' => new CheckinFormResource($form->load('clients:id'))], 201);
    }

    public function update(UpdateCheckinFormRequest $request, CheckinForm $checkinForm): JsonResponse
    {
        $coach = $this->resolveCoach($request->user());
        $this->assertOwnsForm($coach, $checkinForm);

        $checkinForm->update([
            'title' => $request->validated('title'),
            'frequency' => $request->validated('frequency'),
            'send_time' => $request->validated('sendTime'),
            'questions' => $request->validated('questions'),
            'is_active' => $request->validated('isActive', $checkinForm->is_active),
        ]);

        return response()->json(['data' => new CheckinFormResource($checkinForm->load('clients:id'))]);
    }

    public function assign(AssignCheckinFormRequest $request, CheckinForm $checkinForm): JsonResponse
    {
        $coach = $this->resolveCoach($request->user());
        $this->assertOwnsForm($coach, $checkinForm);

        $clientIds = $request->validated('clientIds');
        $this->assertCoachOwnsClients($coach, $clientIds);

        $checkinForm->clients()->syncWithoutDetaching(
            collect($clientIds)->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->all()
        );

        return response()->json(['data' => new CheckinFormResource($checkinForm->load('clients:id'))]);
    }
}
