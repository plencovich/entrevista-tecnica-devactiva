<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CategoryStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DomainPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_creates_a_valid_user_with_enum_casts_and_hashed_password(): void
    {
        $user = User::factory()->admin()->inactive()->create([
            'password' => 'secret-password',
        ]);

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame(UserStatus::Inactive, $user->status);
        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_user_email_is_normalized_and_remains_unique(): void
    {
        User::factory()->create(['email' => 'ADMIN@EXAMPLE.COM']);

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'admin@example.com']);
    }

    public function test_user_has_articles(): void
    {
        $user = User::factory()->create();
        $articles = Article::factory()->count(2)->for($user, 'author')->create();

        $this->assertCount(2, $user->articles);
        $this->assertTrue($user->articles->contains($articles->first()));
    }

    public function test_category_factory_creates_a_valid_category_with_enum_cast(): void
    {
        $category = Category::factory()->inactive()->create();

        $this->assertNotEmpty($category->description);
        $this->assertSame(CategoryStatus::Inactive, $category->status);
    }

    public function test_article_factory_creates_valid_draft_and_published_articles(): void
    {
        $draft = Article::factory()->draft()->create();
        $published = Article::factory()->published()->create();

        $this->assertSame(ArticleStatus::Draft, $draft->status);
        $this->assertNull($draft->published_at);
        $this->assertSame(ArticleStatus::Published, $published->status);
        $this->assertInstanceOf(Carbon::class, $published->published_at);
    }

    public function test_article_belongs_to_its_author(): void
    {
        $author = User::factory()->create();
        $article = Article::factory()->for($author, 'author')->create();

        $this->assertTrue($article->author->is($author));
    }

    public function test_articles_and_categories_have_a_many_to_many_relationship(): void
    {
        $articles = Article::factory()->count(2)->create();
        $categories = Category::factory()->count(2)->create();

        $articles[0]->categories()->attach($categories->modelKeys());
        $articles[1]->categories()->attach($categories[0]);

        $this->assertCount(2, $articles[0]->categories);
        $this->assertCount(2, $categories[0]->articles);
        $this->assertCount(1, $categories[1]->articles);
    }

    public function test_same_article_category_association_cannot_be_duplicated(): void
    {
        $article = Article::factory()->create();
        $category = Category::factory()->create();
        $article->categories()->attach($category);

        $this->expectException(QueryException::class);
        $article->categories()->attach($category);
    }

    public function test_deleting_an_article_cascades_its_pivot_associations(): void
    {
        $article = Article::factory()->create();
        $category = Category::factory()->create();
        $article->categories()->attach($category);

        $article->delete();

        $this->assertDatabaseMissing('article_category', [
            'article_id' => $article->id,
            'category_id' => $category->id,
        ]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $article = Article::factory()->create();
        $category = Category::factory()->create();
        $article->categories()->attach($category);

        $this->expectException(QueryException::class);
        $category->delete();
    }

    public function test_author_with_articles_cannot_be_deleted(): void
    {
        $author = User::factory()->create();
        Article::factory()->for($author, 'author')->create();

        $this->expectException(QueryException::class);
        $author->delete();
    }
}
