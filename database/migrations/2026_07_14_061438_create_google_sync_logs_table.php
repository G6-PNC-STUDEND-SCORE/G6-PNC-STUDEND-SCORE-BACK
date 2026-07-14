<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_sheet_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('direction', ['push', 'pull']); // push = system→sheets, pull = sheets→system
            $table->enum('status', ['success', 'failed', 'partial']);
            $table->integer('updated_records')->default(0);
            $table->integer('failed_records')->default(0);
            $table->text('error_message')->nullable();
            $table->json('changes_summary')->nullable(); // [{student, column, old, new}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sync_logs');
    }
};
