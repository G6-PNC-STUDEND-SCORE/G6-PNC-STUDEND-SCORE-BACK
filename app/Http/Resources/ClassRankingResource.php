<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->resource['rank'],
            'student_id' => $this->resource['student_id'],
            'student_name' => $this->resource['student_name'],
            'total_score' => $this->resource['total_score'],
            'average_score' => $this->resource['average_score'],
        ];
    }
}
