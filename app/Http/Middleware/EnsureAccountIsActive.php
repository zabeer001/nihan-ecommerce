<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? auth('api')->user();

        if ($user && $user->is_frozen) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'This account has been frozen. Please contact an administrator.',
            ], 403);
        }

        return $next($request);
    }
}
