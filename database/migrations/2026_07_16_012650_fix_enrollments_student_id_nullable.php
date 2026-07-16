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
        // Make student_id nullable so empty rows can be added
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable(false)->change();
        });
    }
};
