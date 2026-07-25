<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:assessment_types,code',
            'name' => 'required|string|max:100',
            'weight_percent' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ];
    }
}
