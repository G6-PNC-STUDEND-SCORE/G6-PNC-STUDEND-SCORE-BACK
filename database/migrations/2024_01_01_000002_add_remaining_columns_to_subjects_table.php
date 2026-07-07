<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('teacher')->after('code');
            $table->string('class')->after('teacher');
            $table->integer('credits')->after('class');
            $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('credits');
            $table->string('image')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['teacher', 'class', 'credits', 'status', 'image']);
        });
    }
};
