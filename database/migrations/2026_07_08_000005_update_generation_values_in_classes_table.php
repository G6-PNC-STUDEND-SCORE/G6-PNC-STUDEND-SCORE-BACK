<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update existing generation values
        \DB::table('classes')
            ->where('generation', 'Grade 10')
            ->update(['generation' => '2025']);

        \DB::table('classes')
            ->where('generation', 'Grade 11')
            ->update(['generation' => '2026']);

        \DB::table('classes')
            ->where('generation', 'Grade 12')
            ->update(['generation' => '2027']);
    }

    public function down(): void
    {
        // Revert back to original values
        \DB::table('classes')
            ->where('generation', '2025')
            ->update(['generation' => 'Grade 10']);

        \DB::table('classes')
            ->where('generation', '2026')
            ->update(['generation' => 'Grade 11']);

        \DB::table('classes')
            ->where('generation', '2027')
            ->update(['generation' => 'Grade 12']);
    }
};
