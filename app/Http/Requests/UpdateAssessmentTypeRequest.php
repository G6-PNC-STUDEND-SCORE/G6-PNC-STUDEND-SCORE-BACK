<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:100',
            'weight_percent' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
