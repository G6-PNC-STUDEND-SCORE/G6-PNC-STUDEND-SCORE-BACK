<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Determine whether the user can view any students.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-students');
    }

    /**
     * Determine whether the user can view a specific student.
     * Students can only view their own record.
     */
    public function view(User $user, Student $student): bool
    {
        // Admin/Teacher can view any student
        if ($user->hasPermission('view-students')) {
            return true;
        }

        // Students can only view their own record
        return $user->student && $user->student->id === $student->id;
    }

    /**
     * Determine whether the user can create students.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create-students');
    }

    /**
     * Determine whether the user can update a student.
     */
    public function update(User $user, Student $student): bool
    {
        return $user->hasPermission('edit-students');
    }

    /**
     * Determine whether the user can delete a student.
     */
    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermission('delete-students');
    }
}