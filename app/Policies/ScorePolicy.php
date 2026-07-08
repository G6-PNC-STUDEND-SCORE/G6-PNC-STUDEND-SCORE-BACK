<?php

namespace App\Policies;

use App\Models\Score;
use App\Models\User;

class ScorePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view-scores'); }
    public function view(User $user, Score $score): bool { return $user->hasPermission('view-scores'); }
    public function create(User $user): bool { return $user->hasPermission('manage-scores'); }
    public function update(User $user, Score $score): bool { return $user->hasPermission('manage-scores'); }
    public function delete(User $user, Score $score): bool { return $user->hasPermission('manage-scores'); }
}