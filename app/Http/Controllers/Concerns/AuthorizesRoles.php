<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait AuthorizesRoles
{
    protected function ensureManager(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isManager(), 403, 'Akses hanya untuk admin atau atasan.');

        return $user;
    }

    protected function ensureAdmin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin(), 403, 'Akses hanya untuk admin.');

        return $user;
    }

    protected function ensureSelfOrManager(Request $request, int $employeeId): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->id === $employeeId || $user->isManager(),
            403,
            'Anda tidak memiliki akses ke data ini.',
        );

        return $user;
    }
}
