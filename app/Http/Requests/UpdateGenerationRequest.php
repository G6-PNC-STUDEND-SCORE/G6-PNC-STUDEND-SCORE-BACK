<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['sometimes', 'integer', Rule::unique('generations', 'year')->ignore($this->route('generation'))],
            'is_current' => 'sometimes|boolean',
        ];
    }
}
