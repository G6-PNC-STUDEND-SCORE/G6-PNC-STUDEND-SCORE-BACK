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
            $table->enum('type', ['quiz', 'assignment', 'midterm', 'final']);
            $table->string('label', 50)->comment('e.g. Quiz 1, Quiz 2, Midterm, Final');
            $table->decimal('mark', 5, 2)->nullable();
            $table->timestamps();

            $table->index('score_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_details');
    }
};
