<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        TenantContext::clear();

        $email = mb_strtolower((string) env('SUPER_ADMIN_EMAIL', 'superadmin@absensi.com'));
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'superadmin123');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => $password,
                'role' => User::ROLE_SUPER_ADMIN,
                'tenant_id' => null,
                'position' => 'Platform',
                'team' => '',
                'is_active' => true,
                'joined_at' => now()->subYears(3)->toDateString(),
            ],
        );
    }
}
