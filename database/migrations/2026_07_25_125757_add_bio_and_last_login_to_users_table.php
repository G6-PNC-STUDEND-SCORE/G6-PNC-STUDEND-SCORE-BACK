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
        // User::$fillable has referenced these since the model was written, but they were
        // never actually migrated — saving a profile with a bio/date_of_birth has been
        // silently failing (or erroring outright) the entire time.
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('status');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->timestamp('last_login_at')->nullable()->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'date_of_birth', 'last_login_at']);
        });
    }
};
