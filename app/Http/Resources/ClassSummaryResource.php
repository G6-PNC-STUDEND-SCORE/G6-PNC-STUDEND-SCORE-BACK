<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'class_id' => $this->resource['class_id'],
            'class_name' => $this->resource['class_name'],
            'total_students' => $this->resource['total_students'],
            'highest_average' => $this->resource['highest_average'],
            'lowest_average' => $this->resource['lowest_average'],
            'class_average' => $this->resource['class_average'],
            'pass_count' => $this->resource['pass_count'],
            'fail_count' => $this->resource['fail_count'],
        ];
    }
}
