<?php

use App\Facades\Navigation;
use App\Navigation\Section;

/*
|--------------------------------------------------------------------------
| CoachCommerce Module Navigation
|--------------------------------------------------------------------------
|
| Define CoachCommerce module navigation items here.
| These items will be loaded automatically when the module is enabled.
|
*/

Navigation::add('CoachCommerce', route('coach-commerce.index'), function (Section $section) {
    $section->attributes([
        'group' => 'main',
        'slug' => 'coach-commerce',
        'icon' => 'coach-commerce',
        'badge' => [
            'content' => 'New',
            'variant' => 'info',
        ],
    ]);
});
