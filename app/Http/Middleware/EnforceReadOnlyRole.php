<?php

namespace App\Http\Middleware;

use App\Services\WorkAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceReadOnlyRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (WorkAccess::readOnly($request->user()) && ! $request->isMethodSafe()
            && ! $request->routeIs('logout', 'password.update', 'notifications.read', 'chat.read')) {
            abort(403, 'Jabatan Anda hanya memiliki akses melihat.');
        }

        return $next($request);
    }
}
