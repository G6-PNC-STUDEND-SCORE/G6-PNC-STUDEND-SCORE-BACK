<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('classes', 'room')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->string('room', 50)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('classes', 'room')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropColumn(['room']);
            });
        }
    }
};