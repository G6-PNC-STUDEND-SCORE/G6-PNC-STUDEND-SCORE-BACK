<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make student_id_number nullable to allow creating students without a number.
     * Uses raw SQL to avoid the doctrine/dbal dependency required by ->change().
     */
    public function up(): void
    {
        if (Schema::hasColumn('students', 'student_id_number')) {
            DB::statement('ALTER TABLE students MODIFY student_id_number VARCHAR(50) NULL');
        }
    }

    public function down(): void
    {
        // No simple rollback — changing column nullability is a one-way operation
    }
};
