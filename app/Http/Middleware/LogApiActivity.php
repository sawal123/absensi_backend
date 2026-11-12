<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiActivity
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), self::WRITE_METHODS, true)) {
            try {
                $payload = $request->except([
                    'password',
                    'password_confirmation',
                    'current_password',
                    'admin_password',
                    'new_password',
                    'old_password',
                    'token',
                    'file',
                    'photo_file',
                ]);

                AuditLog::query()->create([
                    'user_id' => $request->user()?->id,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status_code' => $response->getStatusCode(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'payload' => $this->sanitize($payload),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $error) {
                report($error);
            }
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitize(array $payload): array
    {
        array_walk_recursive($payload, function (&$value) {
            if (is_string($value) && strlen($value) > 500) {
                $value = substr($value, 0, 500).'...';
            }
        });

        return $payload;
    }
}
