<?php

use App\Facades\Navigation;
use App\Navigation\Section;
use Illuminate\Support\Facades\Auth;

Navigation::addWhen(
    fn () => Auth::check() && Auth::user()->isCoach(),
    'Chat',
    route('coach.chat'),
    function (Section $section) {
        $section->attributes([
            'group' => 'main',
            'slug' => 'coach-chat',
            'icon' => 'chat',
            'order' => 4,
        ]);
    }
);
