<?php

namespace Modules\CoachCommerce\Http\Controllers;

use Inertia\Inertia;

class CoachCommerceController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('CoachCommerce::Index', [
            'message' => 'Welcome to CoachCommerce Module',
            'module' => 'coachcommerce',
        ]);
    }
}
