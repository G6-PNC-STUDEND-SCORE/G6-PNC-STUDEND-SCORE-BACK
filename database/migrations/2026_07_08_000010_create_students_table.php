<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // PNC-style student number fields
            $table->string('student_number', 20)->unique()->comment('Generated ID: PNC{year}-{seq} e.g. PNC2026-001');
            $table->year('intake_year')->comment('The year the student first enrolled (e.g. 2026)');
            $table->unsignedInteger('sequence_number')->comment('Per-year sequential number (1, 2, 3...)');

            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->date('enrollment_date')->nullable();
            $table->timestamps();

            $table->index('class_id');
            $table->index('academic_year_id');
            $table->index('intake_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};