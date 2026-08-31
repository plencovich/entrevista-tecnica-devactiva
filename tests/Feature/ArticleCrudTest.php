<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_endpoints_require_authentication(): void
    {
        $article = Article::factory()->create();

        $this->getJson('/api/v1/articles')->assertUnauthorized();
        $this->postJson('/api/v1/articles')->assertUnauthorized();
        $this->getJson("/api/v1/articles/{$article->id}")->assertUnauthorized();
        $this->patchJson("/api/v1/articles/{$article->id}")->assertUnauthorized();
        $this->deleteJson("/api/v1/articles/{$article->id}")->assertUnauthorized();
    }

    public function test_editor_lists_views_and_updates_only_their_own_articles_and_never_deletes(): void
    {
        $editor = User::factory()->editor()->create();
        $otherEditor = User::factory()->editor()->create();
        $ownArticle = Article::factory()->for($editor, 'author')->create();
        $otherArticle = Article::factory()->for($otherEditor, 'author')->create();
        Sanctum::actingAs($editor);

        $response = $this->getJson('/api/v1/articles')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);

        $articleIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($articleIds->contains($ownArticle->id));
        $this->assertFalse($articleIds->contains($otherArticle->id));

        $this->getJson("/api/v1/articles/{$ownArticle->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownArticle->id);
        $this->getJson("/api/v1/articles/{$otherArticle->id}")->assertForbidden();

        $this->patchJson("/api/v1/articles/{$ownArticle->id}", [
            'content' => 'Contenido propio actualizado.',
        ])->assertOk()->assertJsonPath('data.content', 'Contenido propio actualizado.');
        $this->patchJson("/api/v1/articles/{$otherArticle->id}", [
            'content' => 'Intento indebido.',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/articles/{$ownArticle->id}")->assertForbidden();
        $this->deleteJson("/api/v1/articles/{$otherArticle->id}")->assertForbidden();
        $this->assertDatabaseHas('articles', ['id' => $ownArticle->id]);
        $this->assertDatabaseHas('articles', ['id' => $otherArticle->id]);
    }

    public function test_admin_lists_updates_and_deletes_articles_from_any_author(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $adminArticle = Article::factory()->for($admin, 'author')->create();
        $editorArticle = Article::factory()->for($editor, 'author')->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/articles?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);

        $articleIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($articleIds->contains($adminArticle->id));
        $this->assertTrue($articleIds->contains($editorArticle->id));

        $this->getJson("/api/v1/articles/{$editorArticle->id}")->assertOk();
        $this->putJson("/api/v1/articles/{$editorArticle->id}", [
            'title' => 'Editado por admin',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Editado por admin')
            ->assertJsonPath('data.slug', 'editado-por-admin');

        $this->deleteJson("/api/v1/articles/{$editorArticle->id}")->assertNoContent();
        $this->assertDatabaseMissing('articles', ['id' => $editorArticle->id]);
    }

    public function test_article_creation_controls_author_slug_categories_and_resource_shape(): void
    {
        $editor = User::factory()->editor()->create();
        $otherUser = User::factory()->create();
        $categories = Category::factory()->count(2)->create();
        Sanctum::actingAs($editor);

        $response = $this->postJson('/api/v1/articles', $this->validPayload($categories->modelKeys(), [
            'title' => 'Mi Primer Artículo',
            'published_at' => '2026-01-15T10:00:00Z',
            'author_id' => $otherUser->id,
            'slug' => 'slug-manipulado',
        ]))->assertCreated()
            ->assertJsonPath('data.title', 'Mi Primer Artículo')
            ->assertJsonPath('data.slug', 'mi-primer-articulo')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.author.id', $editor->id)
            ->assertJsonCount(2, 'data.categories')
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'slug', 'content', 'status', 'published_at',
                    'author' => ['id', 'name', 'email', 'role', 'status'],
                    'categories' => [['id', 'name', 'description', 'status', 'created_at', 'updated_at']],
                    'created_at', 'updated_at',
                ],
            ]);

        $article = Article::query()->findOrFail($response->json('data.id'));
        $this->assertSame($editor->id, $article->author_id);
        $this->assertSame('mi-primer-articulo', $article->slug);
        $this->assertNull($article->published_at);
        $this->assertEqualsCanonicalizing($categories->modelKeys(), $article->categories()->pluck('categories.id')->all());
    }

    public function test_article_categories_are_required_distinct_existing_and_cannot_be_emptied(): void
    {
        $editor = User::factory()->editor()->create();
        $category = Category::factory()->create();
        $article = Article::factory()->for($editor, 'author')->create();
        $article->categories()->attach($category);
        Sanctum::actingAs($editor);

        $payload = $this->validPayload([$category->id]);
        unset($payload['category_ids']);
        $this->postJson('/api/v1/articles', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['category_ids']);

        $this->postJson('/api/v1/articles', $this->validPayload([]))
            ->assertUnprocessable()->assertJsonValidationErrors(['category_ids']);

        $this->postJson('/api/v1/articles', $this->validPayload([$category->id, $category->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['category_ids.0', 'category_ids.1']);

        $this->postJson('/api/v1/articles', $this->validPayload([999999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['category_ids.0']);

        $this->patchJson("/api/v1/articles/{$article->id}", ['category_ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['category_ids']);

        $this->assertDatabaseHas('article_category', [
            'article_id' => $article->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_article_update_atomically_replaces_categories_without_changing_author(): void
    {
        $editor = User::factory()->editor()->create();
        $originalCategory = Category::factory()->create();
        $newCategories = Category::factory()->count(2)->create();
        $article = Article::factory()->for($editor, 'author')->create();
        $article->categories()->attach($originalCategory);
        Sanctum::actingAs($editor);

        $this->patchJson("/api/v1/articles/{$article->id}", [
            'category_ids' => $newCategories->modelKeys(),
            'author_id' => User::factory()->create()->id,
            'slug' => 'manual',
        ])->assertOk()
            ->assertJsonPath('data.author.id', $editor->id)
            ->assertJsonCount(2, 'data.categories');

        $article->refresh();
        $this->assertSame($editor->id, $article->author_id);
        $this->assertNotSame('manual', $article->slug);
        $this->assertEqualsCanonicalizing($newCategories->modelKeys(), $article->categories()->pluck('categories.id')->all());
        $this->assertDatabaseMissing('article_category', [
            'article_id' => $article->id,
            'category_id' => $originalCategory->id,
        ]);
    }

    public function test_observer_generates_incremental_unique_slugs_and_stable_empty_fallback(): void
    {
        $first = Article::factory()->create(['title' => 'Mi Artículo']);
        $second = Article::factory()->create(['title' => 'Mi Artículo']);
        $third = Article::factory()->create(['title' => 'Mi Artículo']);
        $empty = Article::factory()->create(['title' => '!!!']);
        $secondEmpty = Article::factory()->create(['title' => '???']);

        $this->assertSame('mi-articulo', $first->slug);
        $this->assertSame('mi-articulo-2', $second->slug);
        $this->assertSame('mi-articulo-3', $third->slug);
        $this->assertSame('article', $empty->slug);
        $this->assertSame('article-2', $secondEmpty->slug);
    }

    public function test_title_update_regenerates_slug_and_excludes_current_article_from_collisions(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Article::factory()->create(['title' => 'Original']);
        $second = Article::factory()->create(['title' => 'Destino']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/articles/{$first->id}", ['title' => 'Destino'])
            ->assertOk()->assertJsonPath('data.slug', 'destino-2');

        $this->patchJson("/api/v1/articles/{$second->id}", ['content' => 'Sin cambiar título'])
            ->assertOk()->assertJsonPath('data.slug', 'destino');
    }

    public function test_observer_keeps_publication_status_and_date_consistent(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        Sanctum::actingAs($admin);

        $draftId = $this->postJson('/api/v1/articles', $this->validPayload([$category->id], [
            'status' => 'draft',
            'published_at' => '2026-01-15T10:00:00Z',
        ]))->assertCreated()->assertJsonPath('data.published_at', null)->json('data.id');

        $automaticId = $this->postJson('/api/v1/articles', $this->validPayload([$category->id], [
            'title' => 'Publicación automática',
            'status' => 'published',
            'published_at' => null,
        ]))->assertCreated()->json('data.id');

        $explicitDate = '2026-01-15T10:00:00Z';
        $explicitId = $this->postJson('/api/v1/articles', $this->validPayload([$category->id], [
            'title' => 'Publicación programada',
            'status' => 'published',
            'published_at' => $explicitDate,
        ]))->assertCreated()->json('data.id');

        $this->assertNull(Article::query()->findOrFail($draftId)->published_at);
        $this->assertNotNull(Article::query()->findOrFail($automaticId)->published_at);
        $this->assertTrue(
            Article::query()->findOrFail($explicitId)->published_at->equalTo(Carbon::parse($explicitDate)),
        );

        $this->patchJson("/api/v1/articles/{$automaticId}", ['status' => 'draft'])
            ->assertOk()->assertJsonPath('data.published_at', null);
        $this->assertNull(Article::query()->findOrFail($automaticId)->published_at);
    }

    public function test_user_deactivated_after_token_issuance_cannot_create_or_update_articles(): void
    {
        $editor = User::factory()->editor()->active()->create();
        $category = Category::factory()->create();
        $article = Article::factory()->for($editor, 'author')->create();
        $article->categories()->attach($category);
        $token = $editor->createToken('existing-token')->plainTextToken;
        $editor->update(['status' => UserStatus::Inactive]);

        $this->withToken($token)
            ->postJson('/api/v1/articles', $this->validPayload([$category->id]))
            ->assertForbidden();

        $this->withToken($token)
            ->patchJson("/api/v1/articles/{$article->id}", ['title' => 'No permitido'])
            ->assertForbidden();

        $this->assertNotSame('No permitido', $article->refresh()->title);
    }

    /**
     * @param  list<int>  $categoryIds
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $categoryIds, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Artículo de prueba',
            'content' => 'Contenido del artículo.',
            'status' => 'draft',
            'published_at' => null,
            'category_ids' => $categoryIds,
        ], $overrides);
    }
}
