<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_settings', function (Blueprint $table) {
            $table->id();
            $table->string('office_name')->default('Kantor Pusat');
            $table->decimal('latitude', 10, 7)->default(-6.2000000);
            $table->decimal('longitude', 10, 7)->default(106.8166660);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->string('work_start', 5)->default('08:00');
            $table->string('work_end', 5)->default('17:00');
            $table->unsignedInteger('early_check_in_minutes')->default(60);
            $table->unsignedInteger('late_tolerance_minutes')->default(0);
            $table->string('timezone')->default('Asia/Jakarta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_settings');
    }
};
