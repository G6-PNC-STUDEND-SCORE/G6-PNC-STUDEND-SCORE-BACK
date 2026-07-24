<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add teacher_id foreign key to the classes table.
     * The Teacher model defines: $this->hasMany(SchoolClass::class, 'teacher_id')
     */
    public function up(): void
    {
        if (!Schema::hasColumn('classes', 'teacher_id')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->foreignId('teacher_id')
                    ->nullable()
                    ->after('generation_id')
                    ->constrained('teachers')
                    ->nullOnDelete();
                $table->index('teacher_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('classes', 'teacher_id')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropIndex(['teacher_id']);
                $table->dropForeign(['teacher_id']);
                $table->dropColumn('teacher_id');
            });
        }
    }
};
