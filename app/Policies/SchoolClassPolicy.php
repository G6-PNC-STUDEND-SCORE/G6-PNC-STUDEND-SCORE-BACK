<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-classes');
    }

    public function view(User $user, SchoolClass $schoolClass): bool
    {
        return $user->hasPermission('view-classes');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create-classes');
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return $user->hasPermission('update-classes');
    }

    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        return $user->hasPermission('delete-classes');
    }
}
