<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds role-based access to Laravel's default users table.
 *
 * `role` is a plain string backed by the App\Enums\Role PHP enum rather than a
 * MySQL ENUM column: adding a new role later is a code change, not an ALTER TABLE.
 * `is_demo` marks the public demo accounts so destructive actions can be blocked for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff')->after('email')->index();
            $table->boolean('is_demo')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'is_demo']);
        });
    }
};
