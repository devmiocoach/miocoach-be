<?php

namespace Modules\WorkoutPlans\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Http\Requests\StoreExerciseRequest;
use Modules\WorkoutPlans\Http\Requests\UpdateExerciseRequest;
use Modules\WorkoutPlans\Http\Resources\ExerciseResource;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Services\VideoThumbnailResolver;

class ExerciseController extends Controller
{
    public function __construct(private VideoThumbnailResolver $thumbnails) {}

    public function index(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;
        $coachId = $coach?->id;

        $query = Exercise::active()
            ->when($coachId, fn ($q) => $q->where(function ($q) use ($coachId) {
                $q->whereNull('coach_id')->orWhere('coach_id', $coachId);
            }), fn ($q) => $q->whereNull('coach_id'))
            ->when($request->input('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->input('type'), fn ($q, $type) => $q->where('category', $type))
            ->when($request->input('muscleGroup'), fn ($q, $muscle) => $q->where(function ($q) use ($muscle) {
                $q->whereJsonContains('primary_muscles', $muscle)
                    ->orWhereJsonContains('secondary_muscles', $muscle);
            }))
            ->orderByRaw($coachId ? "CASE WHEN coach_id = {$coachId} THEN 0 ELSE 1 END" : '1=1')
            ->orderBy('name');

        $limit = min((int) $request->input('limit', 20), 100);
        $exercises = $query->paginate($limit);

        return response()->json([
            'data' => ExerciseResource::collection($exercises->items()),
            'meta' => [
                'total' => $exercises->total(),
                'per_page' => $exercises->perPage(),
                'current_page' => $exercises->currentPage(),
                'last_page' => $exercises->lastPage(),
            ],
        ]);
    }

    public function store(StoreExerciseRequest $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $exercise = Exercise::create([
            'coach_id' => $coach->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'instructions' => $request->validated('instructions'),
            'category' => $request->validated('type'),
            'difficulty' => ExerciseDifficulty::Beginner->value,
            'primary_muscles' => $request->validated('primaryMuscles', []),
            'secondary_muscles' => $request->validated('secondaryMuscles', []),
            'video_url' => $request->validated('videoUrl'),
            'thumbnail_url' => $this->thumbnails->resolve($request->validated('videoUrl')),
            'is_active' => true,
        ]);

        return response()->json(['data' => new ExerciseResource($exercise)], 201);
    }

    public function update(UpdateExerciseRequest $request, Exercise $exercise): JsonResponse
    {
        if ($request->user()->cannot('update', $exercise)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $data = array_filter([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'instructions' => $request->validated('instructions'),
            'category' => $request->validated('type'),
            'primary_muscles' => $request->validated('primaryMuscles'),
            'secondary_muscles' => $request->validated('secondaryMuscles'),
        ], fn ($v) => $v !== null);

        // When the video changes, write the url and its thumbnail together — even
        // to null — so a resolver miss can't strand the previous thumbnail on the
        // new video.
        if ($request->has('videoUrl')) {
            $data['video_url'] = $request->validated('videoUrl');
            $data['thumbnail_url'] = $this->thumbnails->resolve($request->validated('videoUrl'));
        }

        $exercise->update($data);

        return response()->json(['data' => new ExerciseResource($exercise->fresh())]);
    }

    public function destroy(Request $request, Exercise $exercise): JsonResponse
    {
        if ($request->user()->cannot('delete', $exercise)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $plansCount = WorkoutExercise::query()
            ->where('exercise_id', $exercise->id)
            ->distinct()
            ->count('workout_plan_id');

        if ($plansCount > 0) {
            return response()->json([
                'message' => __('This exercise is used in :count plans.', ['count' => $plansCount]),
                'plansCount' => $plansCount,
            ], 422);
        }

        $exercise->delete();

        return response()->json(null, 204);
    }
}
