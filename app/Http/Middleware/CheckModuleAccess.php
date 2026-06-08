<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    public function handle(
        Request $request,
        Closure $next,
        string $module
    ): Response {

        if(! userHasModule($module))
        {
            abort(403);
        }

        return $next($request);
    }
}