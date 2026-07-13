<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old role and avatar columns from users (moved to RBAC system)
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['role', 'avatar']);
            });
        }

        // Drop old tables that are being replaced
        Schema::dropIfExists('grade_rules');
    }

    public function down(): void
    {
        // Restore old columns if rolled back
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher'])->after('email')->nullable();
            $table->string('avatar')->nullable()->after('role');
        });
    }
};