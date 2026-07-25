<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer|unique:generations,year',
            'is_current' => 'boolean',
        ];
    }
}
