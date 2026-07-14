<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_id')->constrained('scores')->cascadeOnDelete();
            $table->foreignId('assessment_type_id')
                ->nullable()
                ->constrained('assessment_types')
                ->nullOnDelete();
            $table->string('label', 100)->comment('e.g. Quiz 1, Quiz 2, Assignment 1, Midterm, Final Exam');
            $table->unsignedTinyInteger('sequence_number')->nullable()->comment('Sequence for ordering: 1, 2, 3...');
            $table->decimal('max_score', 5, 2)->nullable()->comment('Maximum possible score for this item');
            $table->decimal('score', 5, 2)->nullable()->comment('Actual score achieved');
            $table->decimal('weight_percent', 5, 2)->nullable()->comment('Weight applied at detail level if different from assessment_type default');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('score_id');
            $table->index('assessment_type_id');
            $table->index(['score_id', 'assessment_type_id'], 'score_details_type_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_details');
    }
};