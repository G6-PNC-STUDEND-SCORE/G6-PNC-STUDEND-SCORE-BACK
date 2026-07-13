<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
<<<<<<< HEAD:database/migrations/2024_06_07_000001_create_subjects_table.php
            $table->string('teacher')->nullable();
            $table->string('class');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
=======
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
>>>>>>> 2f7a714627b59ef1f7770da653c5690dab9f8268:database/migrations/2026_07_08_000001_create_departments_table.php
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
