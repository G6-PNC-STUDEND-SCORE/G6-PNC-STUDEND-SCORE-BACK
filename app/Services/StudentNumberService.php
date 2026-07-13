<?php

namespace App\Services;

use App\Models\StudentNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Service class responsible for generating PNC-style student numbers.
 *
 * Format: PNC{intakeYear}-{threeDigitSequence}
 * Example: PNC2026-001, PNC2026-002, PNC2027-001
 *
 * The sequence restarts every intake year (the year the student first enrolled).
 * Database transactions and row-level locking ensure no duplicate numbers,
 * even under concurrent creation requests.
 */
class StudentNumberService
{
    private const PREFIX = 'PNC';
    private const PAD_LENGTH = 3;
    private const PAD_CHAR = '0';

    /**
     * Generate the next student number for a given intake year.
     *
     * This method MUST be called inside a database transaction.
     * It uses SELECT ... FOR UPDATE to lock the sequence row,
     * preventing race conditions.
     *
     * @param int $intakeYear The year the student first enrolled (e.g. 2026)
     * @return array{student_number: string, intake_year: int, sequence_number: int}
     *
     * @throws \Throwable If the database operation fails
     */
    public function generateNext(int $intakeYear): array
    {
        $sequenceNumber = StudentNumberSequence::where('intake_year', $intakeYear)
            ->lockForUpdate()
            ->count() + 1;

        // Format: PNC2026-001
        $studentNumber = sprintf(
            '%s%d-%s',
            self::PREFIX,
            $intakeYear,
            str_pad((string) $sequenceNumber, self::PAD_LENGTH, self::PAD_CHAR, STR_PAD_LEFT)
        );

        return [
            'student_number' => $studentNumber,
            'intake_year' => $intakeYear,
            'sequence_number' => $sequenceNumber,
        ];
    }

    public function createSequence(int $intakeYear): StudentNumberSequence
    {
        $numberData = $this->generateNext($intakeYear);

        return StudentNumberSequence::create([
            'intake_year' => $numberData['intake_year'],
            'student_number' => $numberData['student_number'],
        ]);
    }

    /**
     * Generate a student number for a new student within a database transaction.
     *
     * This is the recommended way to create student numbers safely.
     *
     * @param int $intakeYear
     * @param callable $callback Function that receives the generated data and returns a Student
     * @return mixed The result of the callback
     *
     * @throws \Throwable
     */
    public function generateWithinTransaction(int $intakeYear, callable $callback): mixed
    {
        return DB::transaction(function () use ($intakeYear, $callback) {
            $numberData = $this->generateNext($intakeYear);
            return $callback($numberData);
        });
    }

    /**
     * Parse an existing student number into its components.
     *
     * @param string $studentNumber e.g. "PNC2026-001"
     * @return array{prefix: string, year: int, sequence: int}|null
     */
    public static function parse(string $studentNumber): ?array
    {
        if (preg_match('/^PNC(\d{4})-(\d+)$/', $studentNumber, $matches)) {
            return [
                'prefix' => 'PNC',
                'year' => (int) $matches[1],
                'sequence' => (int) $matches[2],
            ];
        }
        return null;
    }

    /**
     * Validate that a student number matches the expected format.
     */
    public static function isValidFormat(string $studentNumber): bool
    {
        return self::parse($studentNumber) !== null;
    }

    /**
     * Get the intake year from a student number.
     */
    public static function getIntakeYear(string $studentNumber): ?int
    {
        $parsed = self::parse($studentNumber);
        return $parsed['year'] ?? null;
    }
}
