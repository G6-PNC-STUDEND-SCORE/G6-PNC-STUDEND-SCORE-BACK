<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Academic Years table - represents the school calendar year
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->year('year')->unique()->comment('e.g. 2025, 2026, 2027');
            $table->string('name', 50)->comment('e.g. Academic Year 2025-2026');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        // Generations table - represents student intake batches (e.g. Batch 2026)
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('e.g. Batch 2026, Generation PNC-2026');
            $table->year('year')->unique()->comment('e.g. 2025, 2026, 2027');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
        Schema::dropIfExists('academic_years');
    }
};