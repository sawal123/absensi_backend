<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantContext::clear();

        return $next($request);
    }
}
