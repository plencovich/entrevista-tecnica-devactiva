<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_endpoints_require_authentication(): void
    {
        $category = Category::factory()->create();

        $this->getJson('/api/v1/categories')->assertUnauthorized();
        $this->postJson('/api/v1/categories')->assertUnauthorized();
        $this->getJson("/api/v1/categories/{$category->id}")->assertUnauthorized();
        $this->patchJson("/api/v1/categories/{$category->id}")->assertUnauthorized();
        $this->deleteJson("/api/v1/categories/{$category->id}")->assertUnauthorized();
    }

    public function test_editor_can_list_and_view_categories_but_cannot_manage_them(): void
    {
        $editor = User::factory()->editor()->create();
        $category = Category::factory()->create();
        Sanctum::actingAs($editor);

        $this->getJson('/api/v1/categories?per_page=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('data.0.id', $category->id);

        $this->getJson("/api/v1/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'status', 'created_at', 'updated_at'],
            ]);

        $this->postJson('/api/v1/categories', $this->validPayload())->assertForbidden();
        $this->putJson("/api/v1/categories/{$category->id}", ['name' => 'Nueva'])->assertForbidden();
        $this->patchJson("/api/v1/categories/{$category->id}", ['name' => 'Nueva'])->assertForbidden();
        $this->deleteJson("/api/v1/categories/{$category->id}")->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_an_unused_category(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $created = $this->postJson('/api/v1/categories', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Backend')
            ->assertJsonPath('data.status', 'active');

        $categoryId = $created->json('data.id');

        $this->putJson("/api/v1/categories/{$categoryId}", [
            'description' => 'Descripción actualizada',
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('data.description', 'Descripción actualizada')
            ->assertJsonPath('data.status', 'inactive');

        $this->deleteJson("/api/v1/categories/{$categoryId}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
    }

    public function test_category_payload_validates_required_fields_and_enum(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/categories', [
            'name' => '',
            'description' => null,
            'status' => 'archived',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'description', 'status']);

        $this->patchJson("/api/v1/categories/{$category->id}", [
            'status' => 'archived',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_category_with_articles_returns_conflict_and_preserves_relations(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create();
        $category = Category::factory()->create();
        $article->categories()->attach($category);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/categories/{$category->id}")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'No se puede eliminar la categoría porque tiene artículos asociados.',
            ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
        $this->assertDatabaseHas('article_category', [
            'article_id' => $article->id,
            'category_id' => $category->id,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Backend',
            'description' => 'Contenido sobre backend.',
            'status' => 'active',
        ];
    }
}
