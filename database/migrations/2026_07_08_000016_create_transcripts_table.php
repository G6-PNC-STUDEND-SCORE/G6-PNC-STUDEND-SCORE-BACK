<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->decimal('overall_average', 5, 2)->nullable()->comment('Average across all terms and subjects');
            $table->decimal('overall_gpa', 4, 2)->nullable()->comment('Overall GPA across entire program');
            $table->string('overall_grade', 10)->nullable();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('generation_id');
            $table->index('generated_at', 'transcripts_generated_at_idx');
            $table->index(['student_id', 'generation_id'], 'transcripts_student_gen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};