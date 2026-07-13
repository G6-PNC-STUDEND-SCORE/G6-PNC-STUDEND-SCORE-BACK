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

            $table->foreignId('transcript_id')->constrained('transcripts')->restrictOnDelete();

            $table->foreignId('report_card_id')->nullable()->constrained('report_cards')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();

            // Snapshot fields
            $table->decimal('term_average', 5, 2)->nullable();
            $table->string('term_grade', 10)->nullable();

            $table->timestamps();

            $table->unique(['transcript_id', 'term_id', 'report_card_id'], 'transcript_detail_unique');
            $table->index('report_card_id');
            $table->index('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_details');
    }
};

