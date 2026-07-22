<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('classes', 'teacher_id')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->foreignId('teacher_id')->nullable()->after('name')
                    ->constrained('teachers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('classes', 'teacher_id')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropForeign(['teacher_id']);
                $table->dropColumn('teacher_id');
            });
        }
    }
};
