<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isCoach()) {
            return $next($request);
        }

        $coach = $user->coach;

        if (! $coach || ! $coach->hasCompletedOnboarding()) {
            return redirect()->route('coach.onboarding.index');
        }

        return $next($request);
    }
}
