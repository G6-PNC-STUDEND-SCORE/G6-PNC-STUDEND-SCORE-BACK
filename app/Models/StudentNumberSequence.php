<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Manages per-year sequential counters for PNC-style student number generation.
 *
 * Each row represents one intake year and its current sequence counter.
 * Row locking (lockForUpdate) is used to guarantee unique number generation
 * even under concurrent requests.
 */
class StudentNumberSequence extends Model
{
    protected $fillable = [
        'intake_year',
        'next_sequence',
    ];

    /**
     * Get the next sequence number for a given intake year.
     * Creates a new sequence row if one doesn't exist for that year.
     * Uses database-level row locking to prevent duplicate numbers.
     */
    public static function getNextSequence(int $intakeYear): int
    {
        return self::lockForUpdate()->firstOrCreate(
            ['intake_year' => $intakeYear],
            ['next_sequence' => 1]
        );
    }

    /**
     * Increment and retrieve the next sequence number atomically.
     * The caller MUST be inside a database transaction.
     */
    public static function reserveNext(string $intakeYear): int
    {
        /** @var self $sequence */
        $sequence = self::where('intake_year', $intakeYear)
            ->lockForUpdate()
            ->firstOrCreate(
                ['intake_year' => $intakeYear],
                ['next_sequence' => 1]
            );

        $nextNumber = $sequence->next_sequence;
        $sequence->increment('next_sequence');

        return $nextNumber;
    }
}