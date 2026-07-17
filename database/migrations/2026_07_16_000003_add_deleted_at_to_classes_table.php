<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add soft deletes column to the classes table.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('classes', 'deleted_at')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('classes', 'deleted_at')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
