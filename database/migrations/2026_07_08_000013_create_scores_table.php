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
            $table->decimal('total', 5, 2)->nullable()->comment('Calculated dynamically using weights from assessment_types table.');
            $table->string('grade', 10)->nullable()->comment('A, B+, B, C+, C, D, F');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('student_subject_enrollment_id', 'scores_enrollment_idx');
            $table->index('grade', 'scores_grade_idx');
            $table->index('total', 'scores_total_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};