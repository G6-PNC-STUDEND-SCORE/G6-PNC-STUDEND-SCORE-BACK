<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clean up any old/unused tables from previous schema versions.
     * This migration runs last to ensure all new tables exist first.
     */
    public function up(): void
    {
        // Drop the old student_number_sequences table if it exists (replaced by student_id_number in students table)
        Schema::dropIfExists('student_number_sequences');
    }

    public function down(): void
    {
        // Nothing to restore - old tables are not coming back
    }
};