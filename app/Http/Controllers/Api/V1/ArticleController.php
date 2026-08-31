<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Article\StoreArticleRequest;
use App\Http\Requests\Api\V1\Article\UpdateArticleRequest;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArticleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Article::class);

        $query = Article::query()
            ->with(['author', 'categories'])
            ->latest();

        if ($request->user()->role === UserRole::Editor) {
            $query->whereBelongsTo($request->user(), 'author');
        }

        return ArticleResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $article = DB::transaction(function () use ($request, $validated): Article {
            $article = $request->user()->articles()->create(
                Arr::except($validated, 'category_ids'),
            );
            $article->categories()->attach($validated['category_ids']);

            return $article;
        });

        $article->load(['author', 'categories']);

        return (new ArticleResource($article))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Article $article): ArticleResource
    {
        Gate::authorize('view', $article);

        return new ArticleResource($article->load(['author', 'categories']));
    }

    public function update(UpdateArticleRequest $request, Article $article): ArticleResource
    {
        $validated = $request->validated();

        DB::transaction(function () use ($article, $validated): void {
            $article->update(Arr::except($validated, 'category_ids'));

            if (array_key_exists('category_ids', $validated)) {
                $article->categories()->sync($validated['category_ids']);
            }
        });

        return new ArticleResource($article->refresh()->load(['author', 'categories']));
    }

    public function destroy(Article $article): Response
    {
        Gate::authorize('delete', $article);

        $article->delete();

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
