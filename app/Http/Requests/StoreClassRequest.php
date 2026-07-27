<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'teacher_id' => 'nullable|exists:teachers,id',
            'generation_id' => 'nullable|exists:generations,id',
            'room' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ];
    }
}
