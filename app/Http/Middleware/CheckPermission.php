<?php

namespace App\Http\Middleware;

use App\Services\Permissions\ProfilePermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless(app(ProfilePermissionService::class)->allows($user, $permission, [
            'division_id' => session('active_division_id'),
            'location_id' => session('active_location_id'),
            'module' => 'fleet',
        ]), 403);

        return $next($request);
    }
}
