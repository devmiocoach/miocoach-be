<?php

namespace Modules\Bookings\Http\Controllers;

use Inertia\Inertia;

class BookingsController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Bookings::Index', [
            'message' => 'Welcome to Bookings Module',
            'module' => 'bookings',
        ]);
    }
}
