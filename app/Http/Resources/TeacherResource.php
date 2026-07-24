<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'department_id' => $this->department_id,
            'user'          => $this->relationLoaded('user') ? [
                'id'     => $this->user?->id,
                'name'   => $this->user?->name,
                'email'  => $this->user?->email,
                'gender' => $this->user?->gender,
                'phone'  => $this->user?->phone,
                'avatar' => $this->user?->avatar,
                'status' => $this->user?->status,
            ] : null,
            'department' => $this->relationLoaded('department') ? [
                'id'   => $this->department?->id,
                'name' => $this->department?->name,
            ] : null,
            'subjects_count' => $this->whenCounted('subjects'),
            'classes_count'  => $this->whenCounted('classes'),
            'subjects'       => $this->when($this->relationLoaded('subjects'), function () {
                return $this->subjects->map(fn($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
