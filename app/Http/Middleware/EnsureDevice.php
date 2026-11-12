<?php

namespace App\Http\Middleware;

use App\Models\OfficeSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $deviceId = trim((string) $request->header('X-Device-Id', ''));

        if ($token !== null && OfficeSetting::current()->device_binding_enabled) {
            if ($token->device_id === null && $deviceId !== '') {
                $token->forceFill(['device_id' => $deviceId])->save();
            } elseif ($token->device_id !== null && $deviceId !== $token->device_id) {
                abort(401, 'Perangkat tidak dikenali. Silakan login ulang di perangkat ini.');
            }
        }

        return $next($request);
    }
}
