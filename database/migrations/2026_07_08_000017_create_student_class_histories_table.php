<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_class_histories', function (Blueprint $table) {
            $table->id();
$table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'completed', 'transferred'])->default('active');
            $table->timestamps();

            $table->index('student_id');
            $table->index('class_id');
            $table->index('generation_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_histories');
    }
};