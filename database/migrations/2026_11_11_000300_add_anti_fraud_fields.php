<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_mocked')->default(false)->after('check_out_distance');
            $table->boolean('liveness_passed')->default(true)->after('is_mocked');
            $table->unsignedTinyInteger('face_match_score')->nullable()->after('liveness_passed');
            $table->string('device_id', 64)->nullable()->after('face_match_score');
            $table->string('ip_address', 45)->nullable()->after('device_id');
            $table->text('user_agent')->nullable()->after('ip_address');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('device_id', 64)->nullable()->after('abilities');
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['device_id']);
            $table->dropColumn('device_id');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'is_mocked',
                'liveness_passed',
                'face_match_score',
                'device_id',
                'ip_address',
                'user_agent',
            ]);
        });
    }
};
