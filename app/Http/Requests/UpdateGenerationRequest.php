<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'sometimes|integer|min:2000|max:2100|unique:generations,year,' . $this->route('generation'),
            'is_current' => 'boolean',
        ];
    }
}
