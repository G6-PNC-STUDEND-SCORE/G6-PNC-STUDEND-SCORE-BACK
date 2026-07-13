<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('quiz, assignment, midterm, final');
            $table->string('name', 100);
            $table->decimal('weight_percent', 5, 2)->default(0)->comment('e.g. 20.00');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_types');
    }
};

