<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->year('intake_year')->comment('e.g. 2026, 2027');
            $table->string('student_number', 20)->unique()->comment('e.g. PNC2026-001');
            $table->timestamps();

            $table->index('intake_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_number_sequences');
    }
};