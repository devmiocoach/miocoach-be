<?php

namespace Modules\WorkoutPlans\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PlanDayData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $dayNumber,
        public readonly ?string $name = null,
        /** @var PlanExerciseData[] */
        public readonly array $exercises = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            dayNumber: (int) $data['dayNumber'],
            name: $data['name'] ?? null,
            exercises: array_map(
                static fn (array $exercise): PlanExerciseData => PlanExerciseData::fromArray($exercise),
                $data['exercises'] ?? [],
            ),
        );
    }
}
