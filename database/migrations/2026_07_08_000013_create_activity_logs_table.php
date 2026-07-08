<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 50); // Create, Update, Delete, Login, Logout, Export, Import, ResetPassword
            $table->string('module', 50); // Students, Teachers, Classes, Subjects, Scores, Users, Roles, Permissions, Reports, Auth, System
            $table->text('description');
            $table->string('model_type', 100)->nullable(); // e.g., App\Models\Student
            $table->unsignedBigInteger('model_id')->nullable(); // ID of affected record
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();

            // Indexes for efficient querying
            $table->index('action');
            $table->index('module');
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};