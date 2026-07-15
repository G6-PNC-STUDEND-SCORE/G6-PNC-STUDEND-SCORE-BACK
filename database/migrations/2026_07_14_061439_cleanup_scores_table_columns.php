<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $oldColumns = ['quiz_total', 'assignment_total', 'midterm_score', 'final_exam_score', 'pass_fail'];

    public function up(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            foreach ($this->oldColumns as $column) {
                if (Schema::hasColumn('scores', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('scores', 'total_weighted_score') && !Schema::hasColumn('scores', 'total')) {
            DB::statement('ALTER TABLE scores CHANGE total_weighted_score total DECIMAL(5,2) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('scores', 'total') && !Schema::hasColumn('scores', 'total_weighted_score')) {
            DB::statement('ALTER TABLE scores CHANGE total total_weighted_score DECIMAL(5,2) NULL');
        }

        Schema::table('scores', function (Blueprint $table) {
            if (!Schema::hasColumn('scores', 'quiz_total')) {
                $table->decimal('quiz_total', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('scores', 'assignment_total')) {
                $table->decimal('assignment_total', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('scores', 'midterm_score')) {
                $table->decimal('midterm_score', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('scores', 'final_exam_score')) {
                $table->decimal('final_exam_score', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('scores', 'pass_fail')) {
                $table->enum('pass_fail', ['pass', 'fail'])->nullable();
            }
        });
    }
};
