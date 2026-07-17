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
            $table->string('student_id_number', 50)->unique()->comment('PNC generated student ID, e.g. PNC2026-0001');
            $table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->string('enrollment_number', 50)->nullable()->unique()->comment('Official enrollment/registration number');
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->enum('status', ['active', 'graduated', 'dropped', 'suspended'])->default('active');
            $table->timestamps();

            $table->index('generation_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};