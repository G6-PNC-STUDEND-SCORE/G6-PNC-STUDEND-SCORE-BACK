<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'student_id' => $this->resource['student_id'],
            'student_name' => $this->resource['student_name'],
            'class_name' => $this->resource['class_name'],
            'academic_year' => $this->resource['academic_year'],
            'semester' => $this->resource['semester'],
            'subjects' => $this->resource['subjects'],
            'total_score' => $this->resource['total_score'],
            'average_score' => $this->resource['average_score'],
            'rank' => $this->resource['rank'],
        ];
    }
}
