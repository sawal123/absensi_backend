<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        TenantContext::clear();

        $email = mb_strtolower((string) config('absensi.super_admin.email'));
        $password = (string) config('absensi.super_admin.password');

        if ($password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('SUPER_ADMIN_PASSWORD wajib diisi di .env sebelum menjalankan seeder produksi.');
            }

            $password = 'superadmin123';
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('absensi.super_admin.name'),
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
