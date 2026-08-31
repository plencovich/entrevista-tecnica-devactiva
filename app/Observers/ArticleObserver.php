<?php

namespace App\Observers;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Support\Str;

class ArticleObserver
{
    public function creating(Article $article): void
    {
        $article->slug = $this->uniqueSlug($article);
        $this->synchronizePublicationDate($article);
    }

    public function updating(Article $article): void
    {
        if ($article->isDirty('title')) {
            $article->slug = $this->uniqueSlug($article);
        }

        $this->synchronizePublicationDate($article);
    }

    private function synchronizePublicationDate(Article $article): void
    {
        if ($article->status === ArticleStatus::Draft) {
            $article->published_at = null;

            return;
        }

        if ($article->published_at === null) {
            $article->published_at = now();
        }
    }

    private function uniqueSlug(Article $article): string
    {
        $baseSlug = Str::slug($article->title) ?: 'article';
        $slug = $baseSlug;
        $suffix = 2;

        while (Article::query()
            ->where('slug', $slug)
            ->when($article->exists, fn ($query) => $query->whereKeyNot($article->getKey()))
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
