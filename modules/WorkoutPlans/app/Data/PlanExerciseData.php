<?php

namespace Modules\WorkoutPlans\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PlanExerciseData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $exerciseId,
        public readonly ?int $sets = null,
        public readonly ?int $repsTarget = null,
        public readonly ?float $weightKg = null,
        public readonly ?int $rirTarget = null,
        public readonly ?int $restSeconds = null,
        public readonly ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            exerciseId: (int) $data['exerciseId'],
            sets: isset($data['sets']) ? (int) $data['sets'] : null,
            repsTarget: isset($data['repsTarget']) ? (int) $data['repsTarget'] : null,
            weightKg: isset($data['weightKg']) ? (float) $data['weightKg'] : null,
            rirTarget: isset($data['rirTarget']) ? (int) $data['rirTarget'] : null,
            restSeconds: isset($data['restSeconds']) ? (int) $data['restSeconds'] : null,
            notes: $data['notes'] ?? null,
        );
    }
}
