<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->date('date');
            $table->string('scheduled_start', 5)->default('');
            $table->string('scheduled_end', 5)->default('');
            $table->timestamp('recorded_check_in')->nullable();
            $table->timestamp('recorded_check_out')->nullable();
            $table->timestamp('proposed_check_in')->nullable();
            $table->timestamp('proposed_check_out')->nullable();
            $table->string('correction_type', 20);
            $table->text('reason');
            $table->string('evidence_url')->nullable();
            $table->string('evidence_name')->nullable();
            $table->string('evidence_meta')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->default('');
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
