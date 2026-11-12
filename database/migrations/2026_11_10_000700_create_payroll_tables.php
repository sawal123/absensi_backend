<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedBigInteger('overtime_rate')->default(0);
            $table->string('ptkp_status', 10)->default('TK/0');
            $table->string('npwp', 32)->default('');
            $table->timestamps();
        });

        Schema::create('salary_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('basis', 20)->default('monthly');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('employee_id');
        });

        Schema::create('payroll_deduction_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('basis', 40);
            $table->decimal('rate', 14, 2)->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('overtime_enabled')->default(true);
            $table->unsignedInteger('overtime_min_minutes')->default(30);
            $table->json('ptkp');
            $table->json('pph21_brackets');
            $table->timestamps();
        });

        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('employee_name');
            $table->string('nip', 32)->default('');
            $table->string('position')->default('');
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedBigInteger('gross_earnings')->default(0);
            $table->unsignedBigInteger('total_deductions')->default(0);
            $table->unsignedBigInteger('net_pay')->default(0);
            $table->json('breakdown');
            $table->string('status', 20)->default('final');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period_year', 'period_month']);
            $table->index(['period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('payroll_settings');
        Schema::dropIfExists('payroll_deduction_rules');
        Schema::dropIfExists('salary_allowances');
        Schema::dropIfExists('salary_profiles');
    }
};
