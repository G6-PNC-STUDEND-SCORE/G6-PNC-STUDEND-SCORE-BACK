<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            // Drop the unique constraint first (it includes student_class_history_id)
            $table->dropUnique('enrollment_unique');
            
            // Make the column nullable
            $table->foreignId('student_class_history_id')->nullable()->change();
            
            // Re-add unique constraint - in MySQL, multiple NULLs are allowed in unique indexes
            // But we need to exclude NULLs, so we use a partial unique index approach
            // Actually MySQL treats NULL as a unique value, so multiple NULLs are allowed
            $table->unique(['student_class_history_id', 'subject_offering_id'], 'enrollment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->dropUnique('enrollment_unique');
            $table->foreignId('student_class_history_id')->nullable(false)->change();
            $table->unique(['student_class_history_id', 'subject_offering_id'], 'enrollment_unique');
        });
    }
};
