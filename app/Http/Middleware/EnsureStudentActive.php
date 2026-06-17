<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isActive()) {
            return response()->json(['message' => 'Акаунт заблокований або неактивний', 'code' => 'FORBIDDEN'], 403);
        }

        return $next($request);
    }
}
