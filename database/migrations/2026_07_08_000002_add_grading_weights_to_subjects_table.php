<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedInteger('quiz_weight')->default(20)->after('status');
            $table->unsignedInteger('assignment_weight')->default(10)->after('quiz_weight');
            $table->unsignedInteger('midterm_weight')->default(30)->after('assignment_weight');
            $table->unsignedInteger('final_weight')->default(40)->after('midterm_weight');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['quiz_weight', 'assignment_weight', 'midterm_weight', 'final_weight']);
        });
    }
};
