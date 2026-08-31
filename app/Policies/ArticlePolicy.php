<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Article $article): bool
    {
        return $user->role === UserRole::Admin || $article->author_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, Article $article): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return $user->role === UserRole::Admin || $article->author_id === $user->id;
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->role === UserRole::Admin;
    }
}
