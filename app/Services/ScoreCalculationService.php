<?php

namespace App\Services;

use App\Models\GradeBoundary;
use App\Models\Score;
use App\Models\ScoreDetail;
use Illuminate\Support\Collection;

final class ScoreCalculationService
{
    public function recalculate(Score $score): void
    {
        $details = ScoreDetail::with('assessmentType')
            ->where('score_id', $score->id)
            ->whereNotNull('mark')
            ->get();

        if ($details->isEmpty()) {
            $score->update([
                'total' => null,
                'grade' => null,
            ]);

            return;
        }

        $total = $this->calculateWeightedTotal($details);

        $score->update([
            'total' => $total,
            'grade' => $this->resolveGrade($total),
        ]);
    }

    public function recalculateById(?int $scoreId): void
    {
        if (!$scoreId) {
            return;
        }

        $score = Score::find($scoreId);

        if ($score) {
            $this->recalculate($score);
        }
    }

    public function calculateWeightedTotal(Collection $details): float
    {
        return round(
            $details
                ->groupBy(fn (ScoreDetail $detail) => $detail->assessmentType?->code ?? 'unknown')
                ->sum(function (Collection $group) {
                    $assessmentType = $group->first()?->assessmentType;

                    if (!$assessmentType) {
                        return 0;
                    }

                    $average = $this->calculateSimpleAverage($group);

                    return (($average ?? 0) * ((float) $assessmentType->weight_percent / 100));
                }),
            2
        );
    }

    public function resolveGrade(float|int|null $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return GradeBoundary::getGrade($score) ?? 'F';
    }

    private function calculateSimpleAverage(Collection $details): ?float
    {
        $details = $details->filter(fn ($detail) => $detail->mark !== null);

        if ($details->isEmpty()) {
            return null;
        }

        $totalMarks = $details->sum('mark');
        $totalMaxScores = $details->filter(fn ($detail) => $detail->max_score !== null)->sum('max_score');

        if ($totalMaxScores > 0) {
            return ($totalMarks / $totalMaxScores) * 100;
        }

        return $details->avg('mark');
    }
}
