<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('term_number')->comment('1, 2, 3, 4');
            $table->string('name', 50)->comment('e.g. Term 1, Term 2, Term 3, Term 4');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_year_id', 'term_number']);
            $table->index('academic_year_id');
            $table->index('is_current');
        });

        // Pivot table: Subjects <-> Terms (a subject can exist in multiple terms)
        // e.g. "English" appears in Terms 1-4, "Logic" only in Term 1
        // Admin assigns subjects to terms dynamically
        Schema::create('subject_term', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subject_id', 'term_id']);
            $table->index('subject_id');
            $table->index('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_term');
        Schema::dropIfExists('terms');
    }
};
