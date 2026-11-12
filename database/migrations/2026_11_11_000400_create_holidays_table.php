<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->boolean('is_national')->default(true);
            $table->foreignId('office_location_id')
                ->nullable()
                ->constrained('office_locations')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->unique(['date', 'office_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
