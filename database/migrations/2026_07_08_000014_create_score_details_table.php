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

            // Normalized assessment type (weights live in assessment_types)
            $table->foreignId('assessment_type_id')
                ->nullable()
                ->constrained('assessment_types')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('label', 50)->comment('e.g. Quiz 1, Quiz 2, Midterm, Final');
            $table->integer('order_number')->nullable()->comment('Display order for quiz/assignment items');
            $table->integer('max_score')->nullable()->comment('Maximum possible score (e.g., 10, 20, 100)');
            $table->decimal('mark', 5, 2)->nullable();
            $table->timestamps();

            $table->index('score_id');
            $table->index('assessment_type_id');
            $table->index(['assessment_type_id', 'score_id'], 'score_details_type_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_details');
    }
}
;
