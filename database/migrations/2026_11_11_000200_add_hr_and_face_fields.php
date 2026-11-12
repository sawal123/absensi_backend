<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_settings', function (Blueprint $table) {
            $table->boolean('face_match_required')->default(false)->after('late_tolerance_minutes');
            $table->unsignedTinyInteger('face_match_threshold')->default(12)->after('face_match_required');
            $table->boolean('overtime_requires_approval')->default(true)->after('face_match_threshold');
            $table->boolean('device_binding_enabled')->default(true)->after('overtime_requires_approval');
            $table->boolean('selfie_required')->default(true)->after('device_binding_enabled');
            $table->boolean('mock_location_blocked')->default(true)->after('selfie_required');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('office_location_id')
                ->nullable()
                ->after('team')
                ->constrained('office_locations')
                ->nullOnDelete();
            $table->string('face_photo_path')->nullable()->after('avatar_url');
            $table->string('face_hash', 64)->nullable()->after('face_photo_path');
            $table->timestamp('face_enrolled_at')->nullable()->after('face_hash');
            $table->timestamp('last_login_at')->nullable()->after('face_enrolled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_location_id');
            $table->dropColumn([
                'face_photo_path',
                'face_hash',
                'face_enrolled_at',
                'last_login_at',
            ]);
        });

        Schema::table('office_settings', function (Blueprint $table) {
            $table->dropColumn([
                'face_match_required',
                'face_match_threshold',
                'overtime_requires_approval',
                'device_binding_enabled',
                'selfie_required',
                'mock_location_blocked',
            ]);
        });
    }
};
