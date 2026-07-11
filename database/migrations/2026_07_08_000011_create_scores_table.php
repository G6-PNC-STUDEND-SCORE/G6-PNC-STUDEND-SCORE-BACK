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
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->decimal('total', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'term_id'], 'score_unique');
            $table->index('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};