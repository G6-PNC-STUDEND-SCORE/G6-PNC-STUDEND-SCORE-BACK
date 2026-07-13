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
            $table->foreignId('student_subject_enrollment_id')->unique()->constrained('student_subject_enrollments')->restrictOnDelete();
            $table->decimal('total', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('student_subject_enrollment_id');
            $table->index('total', 'scores_total_idx');
            $table->index('grade', 'scores_grade_idx');
            $table->index('created_at', 'scores_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
