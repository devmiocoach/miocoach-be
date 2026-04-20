<?php

namespace App\Modules\Auth\Http\Middleware;

use App\Models\User;
use App\Modules\Auth\Services\JwtService;
use Closure;
use Firebase\JWT\ExpiredException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $payload = $this->jwt->decode($bearer);
        } catch (ExpiredException) {
            return response()->json(['message' => 'Token scaduto.'], 401);
        } catch (\Throwable) {
            return response()->json(['message' => 'Token non valido.'], 401);
        }

        // Reject temp tokens (two_fa_pending) — they cannot authenticate full requests
        if (! empty($payload->two_fa_pending)) {
            return response()->json(['message' => 'Autenticazione incompleta.'], 401);
        }

        $user = User::find($payload->sub);

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
