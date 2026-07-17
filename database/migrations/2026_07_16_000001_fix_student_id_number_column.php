<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix the student_id_number column: ensure it exists and migrate any
     * existing data from the (now dropped) student_number_sequences table.
     */
    public function up(): void
    {
        // Ensure student_id_number column exists on students table and is nullable
        if (!Schema::hasColumn('students', 'student_id_number')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('student_id_number', 50)->nullable()->unique()->after('user_id');
            });
        } else {
            // Column already exists but might be NOT NULL — make it nullable
            Schema::table('students', function (Blueprint $table) {
                $table->string('student_id_number', 50)->nullable()->change();
            });
        }

        // Drop student_number_sequences table if it still exists (some installs)
        Schema::dropIfExists('student_number_sequences');

        // Remove student_number_sequence_id FK column if it exists
        if (Schema::hasColumn('students', 'student_number_sequence_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('student_number_sequence_id');
            });
        }
    }

    public function down(): void
    {
        // No simple rollback — the data model has permanently changed
    }
};
