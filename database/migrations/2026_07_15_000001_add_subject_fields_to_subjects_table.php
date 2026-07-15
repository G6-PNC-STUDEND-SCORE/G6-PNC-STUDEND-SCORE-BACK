<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'subject_code')) {
                $table->string('subject_code', 50)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('subjects', 'credits')) {
                $table->integer('credits')->nullable()->after('description');
            }
            if (!Schema::hasColumn('subjects', 'department_id')) {
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->after('credits');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['subject_code', 'credits', 'department_id']);
        });
    }
};
