<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = [
        'users',
        'office_settings',
        'office_locations',
        'attendances',
        'work_shifts',
        'shift_assignments',
        'leave_types',
        'leave_balances',
        'leave_requests',
        'attendance_corrections',
        'salary_profiles',
        'salary_allowances',
        'payroll_deduction_rules',
        'payroll_settings',
        'payroll_slips',
        'holidays',
        'overtime_requests',
        'notifications',
        'audit_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedBigInteger('tenant_id')->nullable()->index();
                });
            }
        }

        $tenantId = DB::table('tenants')->orderBy('id')->value('id');
        if ($tenantId === null) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Default Tenant',
                'code' => 'default',
                'plan' => 'basic',
                'status' => 'active',
                'max_employees' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }

        // Satu baris pengaturan global per tenant.
        Schema::table('office_settings', function (Blueprint $table) {
            $table->unique('tenant_id');
        });

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->unique('tenant_id');
        });

        // Kode pengajuan harus unik per tenant, bukan global.
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropUnique('leave_requests_code_unique');
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->dropUnique('attendance_corrections_code_unique');
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique('holidays_date_office_location_id_unique');
            $table->unique(['tenant_id', 'date', 'office_location_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id']);
        });

        Schema::table('office_settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id']);
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'date', 'office_location_id']);
            $table->unique(['date', 'office_location_id']);
        });

        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique('code');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique('code');
        });

        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('tenant_id');
                });
            }
        }
    }
};
