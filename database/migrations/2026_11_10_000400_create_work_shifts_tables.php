<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('start_time', 5)->default('08:00');
            $table->string('end_time', 5)->default('17:00');
            $table->string('office')->default('');
            $table->string('break_label')->nullable();
            $table->boolean('cross_day')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('color')->default(4278190080);
            $table->timestamps();
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->constrained('work_shifts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('work_shifts');
    }
};
