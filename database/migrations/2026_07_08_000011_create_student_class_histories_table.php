<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_class_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->date('start_date')->nullable()->comment('When the student entered this class');
            $table->date('end_date')->nullable()->comment('When the student left this class (null = still enrolled)');
            $table->enum('status', ['active', 'transferred', 'graduated', 'dropped'])->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'class_id', 'academic_year_id'], 'student_class_history_unique');
            $table->index('student_id');
            $table->index('class_id');
            $table->index('academic_year_id');
            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_histories');
    }
};