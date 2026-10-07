<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CoachPlanRequest extends FormRequest
{
    /**
     * Rules for `days.*.id` and `days.*.exercises.*.id`.
     *
     * @return array{day: array<int, mixed>, exercise: array<int, mixed>}
     */
    abstract protected function childIdRules(): array;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $coachId = $this->user()->coach?->id;
        $childIds = $this->childIdRules();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'goal' => ['nullable', 'string', 'max:50'],
            'difficulty' => ['nullable', 'string', 'max:50'],
            'durationWeeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'sessionsPerWeek' => ['nullable', 'integer', 'min:1', 'max:7'],
            'isTemplate' => ['boolean'],

            'days' => ['nullable', 'array', 'max:7'],
            'days.*.id' => $childIds['day'],
            'days.*.dayNumber' => ['required_with:days', 'integer', 'min:1', 'max:31'],
            'days.*.name' => ['nullable', 'string', 'max:255'],

            'days.*.exercises' => ['nullable', 'array', 'max:50'],
            'days.*.exercises.*.id' => $childIds['exercise'],
            'days.*.exercises.*.exerciseId' => [
                'required_with:days.*.exercises',
                'integer',
                // Global exercises, or this coach's own. Never another coach's.
                Rule::exists('exercises', 'id')->where(
                    fn ($query) => $query->whereNull('coach_id')->orWhere('coach_id', $coachId)
                ),
            ],
            'days.*.exercises.*.sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'days.*.exercises.*.repsTarget' => ['nullable', 'integer', 'min:1', 'max:100'],
            'days.*.exercises.*.weightKg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'days.*.exercises.*.rirTarget' => ['nullable', 'integer', 'min:0', 'max:10'],
            'days.*.exercises.*.restSeconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'days.*.exercises.*.notes' => ['nullable', 'string', 'max:1000'],

            'clientIds' => ['sometimes', 'array'],
            'clientIds.*' => [
                'integer',
                // Athletes of this coach, through the athlete_coach pivot.
                Rule::exists('athlete_coach', 'athlete_id')->where('coach_id', $coachId),
            ],
            'activeFrom' => ['nullable', 'date'],
        ];
    }
}
