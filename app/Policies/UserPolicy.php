<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authenticatedUser): bool
    {
        return $this->isAdmin($authenticatedUser);
    }

    public function view(User $authenticatedUser, User $user): bool
    {
        return $this->isAdmin($authenticatedUser);
    }

    public function create(User $authenticatedUser): bool
    {
        return $this->isAdmin($authenticatedUser);
    }

    public function update(User $authenticatedUser, User $user): bool
    {
        return $this->isAdmin($authenticatedUser);
    }

    public function delete(User $authenticatedUser, User $user): bool
    {
        return $this->isAdmin($authenticatedUser);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
