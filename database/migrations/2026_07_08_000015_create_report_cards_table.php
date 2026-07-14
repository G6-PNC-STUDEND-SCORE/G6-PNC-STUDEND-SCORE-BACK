<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->decimal('total_average', 5, 2)->nullable()->comment('Average across all enrolled subjects in this term');
            $table->decimal('gpa', 4, 2)->nullable()->comment('Term GPA');
            $table->unsignedInteger('rank_in_class')->nullable()->comment('Student rank within their class for this term');
            $table->unsignedInteger('total_students')->nullable()->comment('Total students in class at time of report');
            $table->string('grade', 10)->nullable();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->text('remarks')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'term_id'], 'report_card_unique');
            $table->index('student_id');
            $table->index('generation_id');
            $table->index('term_id');
            $table->index('class_id');
            $table->index(['term_id', 'class_id'], 'report_cards_term_class_idx');
            $table->index('generated_at', 'report_cards_generated_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};