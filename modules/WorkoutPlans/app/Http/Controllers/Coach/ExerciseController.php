<?php

namespace Modules\WorkoutPlans\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Http\Requests\StoreCoachExerciseRequest;
use Modules\WorkoutPlans\Http\Requests\UpdateCoachExerciseRequest;
use Modules\WorkoutPlans\Http\Resources\ExerciseResource;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Services\VideoThumbnailResolver;

class ExerciseController extends Controller
{
    public function __construct(private VideoThumbnailResolver $thumbnails) {}

    public function index(Request $request): Response
    {
        $coach = $request->user()->coach;

        $exercises = Exercise::active()
            ->forCoach($coach->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('WorkoutPlans::Coach/Exercises/Index', [
            'exercises' => ExerciseResource::collection($exercises)->toArray($request),
        ])->withoutSSR();
    }

    public function store(StoreCoachExerciseRequest $request): JsonResponse
    {
        $exercise = Exercise::create([
            'coach_id' => $request->user()->coach->id,
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

        return response()->json(['exercise' => new ExerciseResource($exercise)], 201);
    }

    public function update(UpdateCoachExerciseRequest $request, Exercise $exercise): JsonResponse
    {
        Gate::authorize('update', $exercise);

        $data = array_filter([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'instructions' => $request->validated('instructions'),
            'secondary_muscles' => $request->validated('secondaryMuscles'),
        ], fn ($v) => $v !== null);

        if ($request->has('type')) {
            $data['category'] = $request->validated('type');
        }

        // Muscles are clearable, so an empty array must be written rather than
        // filtered out as "field absent".
        if ($request->has('primaryMuscles')) {
            $data['primary_muscles'] = $request->validated('primaryMuscles') ?? [];
        }

        // When the video changes, write the url and its thumbnail together — even
        // to null — so a resolver miss can't strand the previous thumbnail on the
        // new video.
        if ($request->has('videoUrl')) {
            $data['video_url'] = $request->validated('videoUrl');
            $data['thumbnail_url'] = $this->thumbnails->resolve($request->validated('videoUrl'));
        }

        $exercise->update($data);

        return response()->json(['exercise' => new ExerciseResource($exercise->fresh())]);
    }

    public function destroy(Request $request, Exercise $exercise): RedirectResponse
    {
        Gate::authorize('delete', $exercise);

        $plansCount = WorkoutExercise::query()
            ->where('exercise_id', $exercise->id)
            ->distinct()
            ->count('workout_plan_id');

        if ($plansCount > 0) {
            return back()->withErrors([
                'exercise' => __('This exercise is used in :count plans.', ['count' => $plansCount]),
            ]);
        }

        $exercise->delete();

        return back();
    }
}
