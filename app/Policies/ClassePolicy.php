<?php

namespace App\Policies;

use App\Models\Classe;
use App\Models\User;

class ClassePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('view-classes'); }
    public function view(User $user, Classe $classe): bool { return $user->hasPermission('view-classes'); }
    public function create(User $user): bool { return $user->hasPermission('create-classes'); }
    public function update(User $user, Classe $classe): bool { return $user->hasPermission('edit-classes'); }
    public function delete(User $user, Classe $classe): bool { return $user->hasPermission('delete-classes'); }
}