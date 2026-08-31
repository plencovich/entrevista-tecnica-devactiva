<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_endpoints_require_authentication(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->postJson('/api/v1/users')->assertUnauthorized();
        $this->getJson("/api/v1/users/{$user->id}")->assertUnauthorized();
        $this->patchJson("/api/v1/users/{$user->id}")->assertUnauthorized();
        $this->deleteJson("/api/v1/users/{$user->id}")->assertUnauthorized();
    }

    public function test_editor_cannot_use_administrative_user_crud(): void
    {
        $editor = User::factory()->editor()->create();
        $target = User::factory()->create();
        Sanctum::actingAs($editor);

        $this->getJson('/api/v1/users')->assertForbidden();
        $this->postJson('/api/v1/users', $this->validPayload())->assertForbidden();
        $this->getJson("/api/v1/users/{$target->id}")->assertForbidden();
        $this->putJson("/api/v1/users/{$target->id}", ['name' => 'Nombre nuevo'])->assertForbidden();
        $this->patchJson("/api/v1/users/{$target->id}", ['name' => 'Nombre nuevo'])->assertForbidden();
        $this->deleteJson("/api/v1/users/{$target->id}")->assertForbidden();
    }

    public function test_admin_can_create_list_show_and_update_users_with_public_resources(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $created = $this->postJson('/api/v1/users', $this->validPayload([
            'email' => 'NEW.USER@EXAMPLE.COM',
        ]))->assertCreated()
            ->assertJsonPath('data.email', 'new.user@example.com')
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonMissingPath('data.password');

        $userId = $created->json('data.id');
        $user = User::query()->findOrFail($userId);
        $originalPassword = $user->password;

        $this->getJson('/api/v1/users?per_page=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('data.id', $userId)
            ->assertJsonMissingPath('data.password');

        $this->patchJson("/api/v1/users/{$userId}", [
            'name' => 'Nombre actualizado',
            'email' => 'UPDATED@EXAMPLE.COM',
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nombre actualizado')
            ->assertJsonPath('data.email', 'updated@example.com')
            ->assertJsonPath('data.status', 'inactive');

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);

        $this->putJson("/api/v1/users/{$userId}", [
            'password' => 'new-password',
            'role' => 'admin',
        ])->assertOk()->assertJsonPath('data.role', 'admin');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_user_store_and_update_validate_unique_email_enums_and_password(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/users', [
            'name' => '',
            'email' => ' EXISTING@EXAMPLE.COM ',
            'password' => 'short',
            'role' => 'owner',
            'status' => 'blocked',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role', 'status']);

        $this->patchJson("/api/v1/users/{$existing->id}", [
            'email' => 'existing@example.com',
        ])->assertOk();

        $other = User::factory()->create();
        $this->patchJson("/api/v1/users/{$other->id}", [
            'email' => 'existing@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_admin_deletes_user_and_all_their_tokens_when_they_have_no_articles(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $target->createToken('one');
        $target->createToken('two');
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/users/{$target->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $target->id,
        ]);
    }

    public function test_user_with_articles_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();
        $article = Article::factory()->for($author, 'author')->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/users/{$author->id}")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'No se puede eliminar el usuario porque es autor de artículos.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $author->id]);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nuevo usuario',
            'email' => 'new@example.com',
            'password' => 'password',
            'role' => 'editor',
            'status' => 'active',
        ], $overrides);
    }
}
