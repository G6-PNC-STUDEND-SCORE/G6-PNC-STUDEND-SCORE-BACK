<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('teacher_code', 50)->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('position', ['lecturer', 'senior_lecturer', 'assistant_professor', 'associate_professor', 'professor', 'instructor', 'other'])->default('lecturer');
            $table->date('hire_date')->nullable();
            $table->string('qualification', 100)->nullable(); // e.g., Bachelor, Master, PhD
            $table->string('specialization', 255)->nullable();
            $table->enum('employment_type', ['permanent', 'contract', 'visiting', 'part_time'])->default('permanent');
            $table->string('salary_grade', 50)->nullable();
            $table->string('office_location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};