<?php

return [
    'name' => 'WorkoutPlans',

    /*
     | Hosts accepted in Exercise::$video_url. Exact host match, https only.
     | The preview renders as an <iframe> to one of these, never a <video>
     | element pointed at a user-supplied URL.
     */
    'video_hosts' => [
        'youtube.com',
        'www.youtube.com',
        'youtu.be',
        'vimeo.com',
        'player.vimeo.com',
    ],
];
