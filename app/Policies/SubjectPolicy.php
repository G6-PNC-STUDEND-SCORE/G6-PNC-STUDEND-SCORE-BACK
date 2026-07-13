<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view-subjects'); }
    public function view(User $user, Subject $subject): bool { return $user->hasPermission('view-subjects'); }
    public function create(User $user): bool { return $user->hasPermission('create-subjects'); }
    public function update(User $user, Subject $subject): bool { return $user->hasPermission('edit-subjects'); }
    public function delete(User $user, Subject $subject): bool { return $user->hasPermission('delete-subjects'); }
}