<?php

namespace App\Services;

use App\Models\Score;
use App\Models\ScoreDetail;

class ScoreCalculationService
{
    public function recalculateTotal(Score $score): void
    {
        $details = ScoreDetail::with('assessmentType')
            ->where('score_id', $score->id)
            ->whereNotNull('mark')
            ->get();

        if ($details->isEmpty()) {
            $score->update(['total' => null, 'grade' => null]);
            return;
        }

        $total = round($details
            ->groupBy(fn($d) => $d->assessmentType?->code ?? 'unknown')
            ->sum(function ($group) {
                $assessmentType = $group->first()->assessmentType;
                if (!$assessmentType) return 0;
                $average = $this->calculateSimpleAverage($group);
                return (($average ?? 0) * ((float) $assessmentType->weight_percent / 100));
            }), 2);

        $grade = match (true) {
            $total >= 90 => 'A',
            $total >= 80 => 'B+',
            $total >= 75 => 'B',
            $total >= 70 => 'C+',
            $total >= 60 => 'C',
            $total >= 50 => 'D',
            default => 'F',
        };

        $score->update(['total' => $total, 'grade' => $grade]);
    }

    public function recalculateTotalById(?int $scoreId): void
    {
        if (!$scoreId) return;
        $score = Score::find($scoreId);
        if (!$score) return;
        $this->recalculateTotal($score);
    }

    private function calculateSimpleAverage($details): ?float
    {
        $details = $details->filter(fn($d) => $d->mark !== null);
        if ($details->isEmpty()) return null;
        $totalMarks = $details->sum('mark');
        $totalMaxScores = $details->filter(fn($d) => $d->max_score)->sum('max_score');
        if ($totalMaxScores > 0) return ($totalMarks / $totalMaxScores) * 100;
        return $details->avg('mark');
    }
}
