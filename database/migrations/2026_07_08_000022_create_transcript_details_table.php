<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained('transcripts')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();

            // Snapshot fields
            $table->string('subject_name')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->timestamps();

            $table->unique(['transcript_id', 'subject_id', 'term_id'], 'transcript_detail_unique');
            $table->index('subject_id');
            $table->index('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_details');
    }
};