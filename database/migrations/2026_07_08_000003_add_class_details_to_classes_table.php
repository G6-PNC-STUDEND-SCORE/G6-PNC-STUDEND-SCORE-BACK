<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('generation')->after('teacher_id');
            $table->string('room')->after('generation');
            $table->integer('students')->default(0)->after('room');
            $table->string('status')->default('Active')->after('students');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['generation', 'room', 'students', 'status']);
        });
    }
};
