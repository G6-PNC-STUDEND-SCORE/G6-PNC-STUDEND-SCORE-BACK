<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_weights', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('name');
            $table->decimal('percent', 5, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Add foreign key if subjects table exists
            if (Schema::hasTable('subjects')) {
                $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_weights');
    }
};
