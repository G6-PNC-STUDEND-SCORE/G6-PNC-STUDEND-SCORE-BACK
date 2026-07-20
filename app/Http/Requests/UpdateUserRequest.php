<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof \App\Models\User ? $user->id : $user;

        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email,' . $userId,
            'password' => 'nullable|string|min:8|max:255',
            'role_id'  => 'required|exists:roles,id',
            'phone'    => 'nullable|string|max:20',
            'gender'   => 'nullable|in:Male,Female,Other',
            'status'   => 'nullable|in:active,inactive,suspended',
        ];
    }
}
