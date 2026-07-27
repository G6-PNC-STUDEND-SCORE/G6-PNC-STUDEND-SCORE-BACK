<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('subject_offerings', function (Blueprint $table) {
                $table->dropIndex('offerings_subj_term_status_idx');
            });
        } catch (\Throwable $e) { /* ignore if it doesn't exist */ }

        try {
            Schema::table('scores', function (Blueprint $table) {
                $table->dropIndex('scores_total_wscore_idx');
            });
        } catch (\Throwable $e) { /* ignore */ }

        try {
            Schema::table('score_details', function (Blueprint $table) {
                $table->dropIndex('scoredetails_score_label_type_idx');
            });
        } catch (\Throwable $e) { /* ignore */ }

        // Composite index for the most common query: subject + term + status
        Schema::table('subject_offerings', function (Blueprint $table) {
            $table->index(['subject_id', 'term_id', 'status'], 'offerings_subj_term_status_idx');
        });

        // Index on the total_weighted_score column (model uses 'total' accessor)
        Schema::table('scores', function (Blueprint $table) {
            $table->index('total_weighted_score', 'scores_total_wscore_idx');
        });

        // Composite index for score details lookups by label+assessment_type
        Schema::table('score_details', function (Blueprint $table) {
            $table->index(['score_id', 'label', 'assessment_type_id'], 'scoredetails_score_label_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subject_offerings', function (Blueprint $table) {
            $table->dropIndex('offerings_subj_term_status_idx');
        });
        Schema::table('scores', function (Blueprint $table) {
            $table->dropIndex('scores_total_wscore_idx');
        });
        Schema::table('score_details', function (Blueprint $table) {
            $table->dropIndex('scoredetails_score_label_type_idx');
        });
    }
};
