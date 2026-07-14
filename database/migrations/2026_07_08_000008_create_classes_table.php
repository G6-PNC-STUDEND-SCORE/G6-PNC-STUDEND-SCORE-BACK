<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('e.g. A, B, C, or Web Dev, System Admin');
            $table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};