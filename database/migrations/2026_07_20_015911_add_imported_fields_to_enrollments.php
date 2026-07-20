<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->string('imported_name')->nullable()->after('student_id');
            $table->string('imported_number')->nullable()->after('imported_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->dropColumn(['imported_name', 'imported_number']);
        });
    }
};
