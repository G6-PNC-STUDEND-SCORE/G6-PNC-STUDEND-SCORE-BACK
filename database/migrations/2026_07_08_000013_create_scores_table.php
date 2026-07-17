<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_subject_enrollment_id')->unique()->constrained('student_subject_enrollments')->cascadeOnDelete();
            $table->decimal('quiz_total', 5, 2)->nullable()->comment('Sum of all quiz scores (weighted)');
            $table->decimal('assignment_total', 5, 2)->nullable()->comment('Sum of all assignment scores (weighted)');
            $table->decimal('midterm_score', 5, 2)->nullable();
            $table->decimal('final_exam_score', 5, 2)->nullable();
            $table->decimal('total_weighted_score', 5, 2)->nullable()->comment('Calculated dynamically using weights from assessment_types table. Admin/Teachers can update assessment_types.weight_percent anytime.');
            $table->string('grade', 10)->nullable()->comment('A, B+, B, C+, C, D, F');
            $table->enum('pass_fail', ['pass', 'fail'])->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('student_subject_enrollment_id', 'scores_enrollment_idx');
            $table->index('grade', 'scores_grade_idx');
            $table->index('total_weighted_score', 'scores_total_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};