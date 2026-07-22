<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retroactively flags students that were already created via score-sheet import
     * (add-row, CSV/Excel/Google Sheets import) before the is_placeholder column existed.
     * These are identified by the synthetic email patterns those code paths use — see
     * SpreadsheetController::addEnrollment/updateEnrollment/importFromGoogleSheets/importFile
     * ('pending_student_*@example.com', 'student_*@example.com', 'imported_*@example.com').
     * Deliberately created students/users (via the Students/Users pages, or bulk import
     * there) use real or differently-patterned emails and are untouched.
     */
    public function up(): void
    {
        $userIds = DB::table('users')
            ->where(function ($q) {
                $q->where('email', 'like', 'pending_student_%@example.com')
                  ->orWhere('email', 'like', 'student_%@example.com')
                  ->orWhere('email', 'like', 'imported_%@example.com');
            })
            ->pluck('id');

        DB::table('students')->whereIn('user_id', $userIds)->update(['is_placeholder' => true]);
    }

    public function down(): void
    {
        // Not meaningfully reversible — there's no record of which rows this migration
        // actually changed vs. were already true before it ran.
    }
};
