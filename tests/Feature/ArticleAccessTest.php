<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Database\Seeders\ArticleCategorySeeder;
use Database\Seeders\EditorialArticleSeeder;
use Database\Seeders\TagSeeder;
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

it('publishes editorial article refresh content with structured references', function () {
    $this->seed([
        ArticleCategorySeeder::class,
        TagSeeder::class,
    ]);

    Article::create([
        'title' => 'AI dan Big Data dalam Penilaian Properti Tahun 2026: Ketika Valuasi Berubah dari Seni Menjadi Ilmu Data',
        'slug' => 'ai-big-data-penilaian-properti-2026',
        'content_html' => '<p>Konten lama</p>',
        'cover_image_path' => 'articles/old-ai-cover.png',
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);

    Article::create([
        'title' => 'Perubahan Regulasi Penilaian Properti 2026: Tantangan Baru bagi Penilai, Perbankan, dan Industri Properti',
        'slug' => 'regulasi-penilaian-properti-2026',
        'content_html' => '<p>Konten lama</p>',
        'cover_image_path' => 'articles/old-regulation-cover.jpg',
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);

    $this->seed(EditorialArticleSeeder::class);

    $article = Article::query()
        ->where('slug', 'ai-big-data-penilaian-properti-2026')
        ->firstOrFail();

    expect($article->title)->toBe('Membaca Data Pembanding untuk Review Appraisal Properti')
        ->and($article->cover_image_path)->toBe('/images/articles/digipro-data-pembanding.svg')
        ->and($article->content_html)->toContain('Bank Indonesia, Survei Harga Properti Residensial')
        ->and(preg_match_all('/<h[23]/', $article->content_html))->toBeGreaterThan(5);

    $this
        ->get(route('articles.show', $article->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Articles/Show')
            ->where('article.title', 'Membaca Data Pembanding untuk Review Appraisal Properti')
            ->where('article.cover_image_path', '/images/articles/digipro-data-pembanding.svg'));
});
