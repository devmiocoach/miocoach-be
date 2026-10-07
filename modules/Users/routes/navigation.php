<?php

use App\Facades\Navigation;
use App\Navigation\Section;
use Illuminate\Support\Facades\Auth;

Navigation::addWhen(
    fn () => Auth::check() && Auth::user()->isCoach(),
    'Clients',
    route('coach.clients.index'),
    function (Section $section) {
        $section->attributes([
            'group' => 'main',
            'slug' => 'coach-clients',
            'icon' => 'coach-clients',
            'order' => 2,
        ]);
    }
);

Navigation::addWhen(
    fn () => Auth::check() && Auth::user()->isCoach(),
    'Agenda',
    route('coach.agenda'),
    function (Section $section) {
        $section->attributes([
            'group' => 'main',
            'slug' => 'coach-agenda',
            'icon' => 'coach-agenda',
            'order' => 3,
        ]);
    }
);
