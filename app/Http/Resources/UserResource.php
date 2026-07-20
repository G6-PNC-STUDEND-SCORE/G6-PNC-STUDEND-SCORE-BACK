<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'gender'        => $this->gender,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'avatar'        => $this->avatar,
            'google_id'     => $this->google_id,
            'role_id'       => $this->role_id,
            'role'          => $this->relationLoaded('role') ? [
                'id' => $this->role?->id,
                'name' => $this->role?->name,
                'slug' => $this->role?->slug,
            ] : null,
            'department'    => $this->department,
            'school'        => $this->school,
            'bio'           => $this->bio,
            'status'        => $this->status,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
