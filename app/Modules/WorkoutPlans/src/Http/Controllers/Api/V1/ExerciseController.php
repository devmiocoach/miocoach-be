<?php

namespace App\Modules\WorkoutPlans\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\WorkoutPlans\Http\Resources\ExerciseResource;
use App\Modules\WorkoutPlans\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExerciseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $coachId = $request->user()->id;

        $exercises = Exercise::where(function ($q) use ($coachId) {
            $q->where('is_public', true)
              ->orWhere('coach_id', $coachId);
        })
            ->when($request->muscle_group, fn ($q, $v) => $q->where('muscle_group', $v))
            ->when($request->difficulty, fn ($q, $v) => $q->where('difficulty', $v))
            ->when($request->search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->orderBy('name')
            ->paginate(50);

        return ExerciseResource::collection($exercises);
    }

    public function show(Exercise $exercise): ExerciseResource
    {
        return new ExerciseResource($exercise);
    }
}
