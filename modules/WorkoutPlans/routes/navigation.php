<?php

use App\Facades\Navigation;
use App\Navigation\Section;

/*
|--------------------------------------------------------------------------
| WorkoutPlans Module Navigation
|--------------------------------------------------------------------------
|
| Define WorkoutPlans module navigation items here.
| These items will be loaded automatically when the module is enabled.
|
*/

Navigation::add('Workout Plans', route('coach.plans.index'), function (Section $section) {
    $section->attributes([
        'group' => 'main',
        'slug' => 'workoutplans',
        'icon' => 'workoutplans',
    ]);
});

Navigation::add('Exercises', route('coach.exercises.index'), function (Section $section) {
    $section->attributes([
        'group' => 'main',
        'slug' => 'exercises',
        'icon' => 'exercises',
    ]);
});
