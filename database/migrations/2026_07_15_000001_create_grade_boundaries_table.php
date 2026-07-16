<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_boundaries', function (Blueprint $table) {
            $table->id();
            $table->string('grade', 10)->unique();
            $table->decimal('min_percent', 5, 2);
            $table->decimal('max_percent', 5, 2);
            $table->string('label', 100);
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default grade boundaries matching the existing spreadsheets grading scale
        $boundaries = [
            ['grade' => 'A',   'min_percent' => 90, 'max_percent' => 100, 'label' => 'A (90-100)', 'color' => '#22c55e'],
            ['grade' => 'B+',  'min_percent' => 80, 'max_percent' => 89,  'label' => 'B+ (80-89)',  'color' => '#3b82f6'],
            ['grade' => 'B',   'min_percent' => 75, 'max_percent' => 79,  'label' => 'B (75-79)',   'color' => '#3b82f6'],
            ['grade' => 'C+',  'min_percent' => 70, 'max_percent' => 74,  'label' => 'C+ (70-74)',  'color' => '#f59e0b'],
            ['grade' => 'C',   'min_percent' => 60, 'max_percent' => 69,  'label' => 'C (60-69)',   'color' => '#f59e0b'],
            ['grade' => 'D',   'min_percent' => 50, 'max_percent' => 59,  'label' => 'D (50-59)',   'color' => '#f97316'],
            ['grade' => 'F',   'min_percent' => 0,  'max_percent' => 49,  'label' => 'F (0-49)',    'color' => '#ef4444'],
        ];

        foreach ($boundaries as $b) {
            DB::table('grade_boundaries')->insert([
                'grade'       => $b['grade'],
                'min_percent' => $b['min_percent'],
                'max_percent' => $b['max_percent'],
                'label'       => $b['label'],
                'color'       => $b['color'],
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_boundaries');
    }
};
