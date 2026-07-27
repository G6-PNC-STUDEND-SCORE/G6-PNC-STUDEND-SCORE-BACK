<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks students that were auto-created as a side effect of adding a row to a score
     * sheet or importing a scores file/Google Sheet, rather than through a deliberate
     * "add student" action on the Students/Users pages. These still need a real
     * Student+User row to attach enrollments/scores to, but shouldn't clutter the
     * Students/Users management lists — only the score sheet cares about them.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_placeholder')->default(false)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_placeholder');
        });
    }
};
