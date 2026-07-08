<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'code')) {
                $table->string('code')->nullable()->after('name');
            }
        });

        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'teacher')) {
                $table->string('teacher')->nullable()->after('code');
            }
        });

        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'class')) {
                $table->string('class')->after('teacher');
            }
        });

        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'credits')) {
                $table->integer('credits')->nullable()->after('class');
            }
        });

        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'status')) {
                $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('credits');
            }
        });

        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'image')) {
                $table->string('image')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['code', 'teacher', 'class', 'credits', 'status', 'image']);
        });
    }
};
