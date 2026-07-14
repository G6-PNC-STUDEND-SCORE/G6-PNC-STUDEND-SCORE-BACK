<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            if (Schema::hasColumn('scores', 'total_weighted_score') && !Schema::hasColumn('scores', 'total')) {
                $table->renameColumn('total_weighted_score', 'total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            if (Schema::hasColumn('scores', 'total') && !Schema::hasColumn('scores', 'total_weighted_score')) {
                $table->renameColumn('total', 'total_weighted_score');
            }
            foreach ($this->oldColumns as $column) {
                if (!Schema::hasColumn('scores', $column)) {
                    $colType = in_array($column, ['quiz_total', 'assignment_total', 'midterm_score', 'final_exam_score'])
                        ? 'decimal'
                        : 'string';
                    $method = $colType === 'decimal'
                        ? $table->decimal($column, 5, 2)->nullable()
                        : $table->string($column, 10)->nullable();

                    if ($column === 'pass_fail') {
                        $table->dropColumn('pass_fail');
                        $table->enum('pass_fail', ['pass', 'fail'])->nullable();
                    }
                }
            }
        });
    }
};
