<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_domain_rules', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->unique()->comment('Email domain without the leading @, lowercased, e.g. student.passerellesnumeriques.org');
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_domain_rules');
    }
};
