<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('generation_id')->constrained('generations')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['subject_id', 'teacher_id', 'class_id', 'generation_id', 'term_id'], 'offering_unique');
            $table->index('subject_id');
            $table->index('teacher_id');
            $table->index('class_id');
            $table->index('generation_id');
            $table->index('term_id');
            $table->index('status');
            $table->index(['generation_id', 'term_id', 'class_id', 'teacher_id'], 'subject_offerings_dashboard_filter_idx');
            $table->index(['teacher_id', 'status'], 'subject_offerings_teacher_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_offerings');
    }
};
