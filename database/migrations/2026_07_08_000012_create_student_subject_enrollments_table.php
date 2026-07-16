<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_subject_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_class_history_id')->constrained('student_class_histories')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->constrained('subject_offerings')->restrictOnDelete();
            $table->enum('status', ['enrolled', 'completed', 'dropped', 'failed'])->default('enrolled');
            $table->timestamps();

            $table->unique(['student_class_history_id', 'subject_offering_id'], 'enrollment_unique');
            $table->index('student_class_history_id');
            $table->index('subject_offering_id');
            $table->index(['subject_offering_id', 'student_class_history_id'], 'enrollments_offering_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_enrollments');
    }
};