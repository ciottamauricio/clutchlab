<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Machine-to-machine gate for the /api/internal/* ingestion routes. These are called by
// CI, which has no user and no session, so the shared secret is the whole identity —
// which is why an unset token denies everything rather than waving callers through.
class EnsureInternalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('clutch.dora.token');
        $presented = (string) $request->header('X-Internal-Token', '');

        // Constant-time: a plain === leaks the secret one byte at a time to anyone who
        // can time the response.
        if ($expected === '' || ! hash_equals($expected, $presented)) {
            return response()->json(['error' => 'internal.unauthorized'], 401);
        }

        return $next($request);
    }
}
