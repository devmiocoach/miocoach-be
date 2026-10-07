<?php

namespace Modules\WorkoutPlans\Tests\Unit;

use Modules\WorkoutPlans\Data\PlanTreeData;
use PHPUnit\Framework\TestCase;

class PlanTreeDataTest extends TestCase
{
    public function test_it_builds_a_full_tree_from_validated_input(): void
    {
        $tree = PlanTreeData::fromArray([
            'name' => 'Forza Base',
            'description' => 'Quattro settimane.',
            'goal' => 'strength',
            'difficulty' => 'beginner',
            'durationWeeks' => 4,
            'sessionsPerWeek' => 3,
            'isTemplate' => true,
            'days' => [
                [
                    'id' => 7,
                    'dayNumber' => 1,
                    'name' => 'Push',
                    'exercises' => [
                        [
                            'id' => null,
                            'exerciseId' => 42,
                            'sets' => 4,
                            'repsTarget' => 8,
                            'weightKg' => 60.5,
                            'rirTarget' => 2,
                            'restSeconds' => 90,
                            'notes' => 'tempo 3-1-1',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Forza Base', $tree->name);
        $this->assertTrue($tree->isTemplate);
        $this->assertCount(1, $tree->days);
        $this->assertSame(7, $tree->days[0]->id);
        $this->assertSame('Push', $tree->days[0]->name);
        $this->assertCount(1, $tree->days[0]->exercises);
        $this->assertNull($tree->days[0]->exercises[0]->id);
        $this->assertSame(42, $tree->days[0]->exercises[0]->exerciseId);
        $this->assertSame(60.5, $tree->days[0]->exercises[0]->weightKg);
    }

    public function test_it_defaults_absent_optional_fields(): void
    {
        $tree = PlanTreeData::fromArray(['name' => 'Minimo']);

        $this->assertSame('Minimo', $tree->name);
        $this->assertNull($tree->description);
        $this->assertFalse($tree->isTemplate);
        $this->assertSame([], $tree->days);
    }

    public function test_a_day_without_exercises_is_valid(): void
    {
        $tree = PlanTreeData::fromArray([
            'name' => 'Vuoto',
            'days' => [['id' => null, 'dayNumber' => 1, 'name' => null]],
        ]);

        $this->assertCount(1, $tree->days);
        $this->assertSame([], $tree->days[0]->exercises);
    }
}
