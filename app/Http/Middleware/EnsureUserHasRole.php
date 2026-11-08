<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user() ?? auth('api')->user();
        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => preg_split('/[|,]/', $role) ?: [])
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!$user) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        if (empty($allowedRoles) || !in_array($user->role, $allowedRoles, true)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Forbidden. Required role: '.implode(', ', $allowedRoles),
            ], 403);
        }

        return $next($request);
    }
}
