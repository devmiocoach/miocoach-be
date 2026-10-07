<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole(Role::COACH->value)) {
            return redirect()->route('coach.dashboard');
        }

        if ($user->hasRole(Role::CLIENT->value)) {
            return redirect()->route('client.dashboard');
        }

        return Inertia::location(route('filament.admin.pages.dashboard'));
    }
}
