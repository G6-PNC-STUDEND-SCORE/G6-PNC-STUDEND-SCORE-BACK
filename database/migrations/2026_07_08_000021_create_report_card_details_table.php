<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained('report_cards')->cascadeOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->constrained('subject_offerings')->nullOnDelete();
            $table->foreignId('score_id')->nullable()->constrained('scores')->nullOnDelete();

            // Snapshot fields (immutable once generated)
            $table->string('subject_name')->nullable()->comment('Snapshot of subject name at time of report');
            $table->decimal('total_score', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->enum('pass_fail', ['pass', 'fail'])->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['report_card_id', 'subject_offering_id'], 'report_card_detail_unique');
            $table->index('subject_offering_id');
            $table->index('score_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_details');
    }
};