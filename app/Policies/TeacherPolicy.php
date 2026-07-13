<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-teachers');
    }

    public function view(User $user, Teacher $teacher): bool
    {
        return $user->hasPermission('view-teachers');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create-teachers');
    }

    public function update(User $user, Teacher $teacher): bool
    {
        return $user->hasPermission('edit-teachers');
    }

    public function delete(User $user, Teacher $teacher): bool
    {
        return $user->hasPermission('delete-teachers');
    }
}