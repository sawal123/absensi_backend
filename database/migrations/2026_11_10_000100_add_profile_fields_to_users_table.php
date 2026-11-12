<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('employee')->after('password');
            $table->string('nip', 32)->nullable()->after('role');
            $table->string('position')->default('')->after('nip');
            $table->string('team')->default('')->after('position');
            $table->string('phone', 32)->nullable()->after('team');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('avatar_url');
            $table->date('joined_at')->nullable()->after('is_active');

            $table->index('role');
            $table->index('nip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['nip']);
            $table->dropColumn([
                'role',
                'nip',
                'position',
                'team',
                'phone',
                'avatar_url',
                'is_active',
                'joined_at',
            ]);
        });
    }
};
