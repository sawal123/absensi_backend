<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            TenantContext::clear();

            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            $tenantId = $request->header('X-Tenant-Id') ?? $request->query('tenant_id');
            if ($tenantId !== null && $tenantId !== '') {
                $tenant = Tenant::query()->find((int) $tenantId);
                abort_unless($tenant !== null, 404, 'Tenant tidak ditemukan.');
                TenantContext::set($tenant);
            } else {
                TenantContext::clear();
            }

            return $next($request);
        }

        $tenant = $user->tenant;
        abort_if($tenant === null, 403, 'Akun tidak terhubung ke perusahaan.');
        abort_unless($tenant->isActive(), 403, 'Langganan perusahaan tidak aktif.');

        TenantContext::set($tenant);

        return $next($request);
    }
}
