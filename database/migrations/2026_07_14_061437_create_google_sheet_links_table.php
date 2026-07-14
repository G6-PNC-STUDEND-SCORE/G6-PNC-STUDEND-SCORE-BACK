<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_sheet_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('spreadsheet_id');
            $table->string('spreadsheet_url');
            $table->string('spreadsheet_name');
            $table->timestamp('last_sync_at')->nullable();
            $table->integer('last_updated_records')->default(0);
            $table->integer('last_failed_records')->default(0);
            $table->enum('sync_status', ['idle', 'syncing', 'success', 'failed'])->default('idle');
            $table->unique(['subject_id', 'term_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheet_links');
    }
};
