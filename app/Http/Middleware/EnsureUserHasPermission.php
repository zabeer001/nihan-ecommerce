<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        abort_unless(array_intersect($permissions, $request->user()->permissionNames()), 403, 'You do not have permission to perform this action.');

        return $next($request);
    }
}
