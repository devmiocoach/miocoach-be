<?php

namespace Modules\WorkoutPlans\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PlanTreeData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $goal = null,
        public readonly ?string $difficulty = null,
        public readonly ?int $durationWeeks = null,
        public readonly ?int $sessionsPerWeek = null,
        public readonly bool $isTemplate = false,
        /** @var PlanDayData[] */
        public readonly array $days = [],
    ) {}

    /** @param array<string, mixed> $validated */
    public static function fromArray(array $validated): self
    {
        return new self(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            goal: $validated['goal'] ?? null,
            difficulty: $validated['difficulty'] ?? null,
            durationWeeks: isset($validated['durationWeeks']) ? (int) $validated['durationWeeks'] : null,
            sessionsPerWeek: isset($validated['sessionsPerWeek']) ? (int) $validated['sessionsPerWeek'] : null,
            isTemplate: (bool) ($validated['isTemplate'] ?? false),
            days: array_map(
                static fn (array $day): PlanDayData => PlanDayData::fromArray($day),
                $validated['days'] ?? [],
            ),
        );
    }
}
