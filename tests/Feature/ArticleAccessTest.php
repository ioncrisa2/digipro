<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');

    foreach ([
        'view_article',
        'create_article',
        'update_article',
    ] as $permissionName) {
        Permission::findOrCreate($permissionName, 'web');
    }

    AdminWorkspaceAccessSynchronizer::sync();
});

function createAdminArticleUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('admin');

    return $user;
}

it('shows only live published articles on the public index', function () {
    $category = ArticleCategory::create([
        'name' => 'Insight',
        'slug' => 'insight',
        'is_active' => true,
    ]);

    Article::create([
        'title' => 'Artikel Live',
        'slug' => 'artikel-live',
        'content_html' => '<p>Live</p>',
        'category_id' => $category->id,
        'is_published' => true,
        'published_at' => now()->subHour(),
    ]);

    Article::create([
        'title' => 'Artikel Terjadwal',
        'slug' => 'artikel-terjadwal',
        'content_html' => '<p>Scheduled</p>',
        'category_id' => $category->id,
        'is_published' => true,
        'published_at' => now()->addDay(),
    ]);

    $this
        ->get(route('articles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Articles/Index')
            ->has('articles.data', 1)
            ->where('articles.data.0.title', 'Artikel Live'));
});

it('allows only admin users to preview unpublished articles', function () {
    $article = Article::create([
        'title' => 'Artikel Draft',
        'slug' => 'artikel-draft',
        'content_html' => '<p>Draft</p>',
        'is_published' => false,
    ]);

    $admin = createAdminArticleUser();
    $customer = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $customer->assignRole('customer');

    $this
        ->get(route('articles.show', $article->slug))
        ->assertNotFound();

    $this
        ->actingAs($customer)
        ->get(route('articles.show', ['slug' => $article->slug, 'preview' => 1]))
        ->assertNotFound();

    $this
        ->actingAs($admin)
        ->get(route('articles.show', ['slug' => $article->slug, 'preview' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Articles/Show')
            ->where('article.title', 'Artikel Draft'));
});

it('marks scheduled articles correctly in the admin workspace', function () {
    $admin = createAdminArticleUser();

    $category = ArticleCategory::create([
        'name' => 'Insight',
        'slug' => 'insight',
        'is_active' => true,
    ]);

    Article::create([
        'title' => 'Artikel Scheduled',
        'slug' => 'artikel-scheduled',
        'content_html' => '<p>Scheduled</p>',
        'category_id' => $category->id,
        'is_published' => true,
        'published_at' => now()->addDay(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.content.articles.index', ['status' => 'scheduled']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Articles/Index')
            ->where('summary.scheduled', 1)
            ->where('records.data.0.editorial_status_label', 'Scheduled'));
});
