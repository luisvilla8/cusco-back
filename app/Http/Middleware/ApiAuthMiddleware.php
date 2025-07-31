<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiAuthenticationException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        $guards = empty($guards) ? ['sanctum'] : $guards;

        foreach ($guards as $guard) {
            if (auth()->guard($guard)->check()) {
                auth()->shouldUse($guard);
                return $next($request);
            }
        }

        throw new ApiAuthenticationException('Token de acceso requerido. Por favor, inicia sesión.');
    }
}