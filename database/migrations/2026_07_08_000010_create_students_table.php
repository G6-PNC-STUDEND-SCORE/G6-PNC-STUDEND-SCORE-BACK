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
            $table->foreignId('student_number_sequence_id')->nullable()->constrained('student_number_sequences')->nullOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->timestamps();
            $table->index('student_number_sequence_id');
            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
