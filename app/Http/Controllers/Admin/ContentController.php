<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArticleIndexRequest;
use App\Http\Requests\Admin\ReorderArticleCategoryRequest;
use App\Http\Requests\Admin\ReorderTagRequest;
use App\Http\Requests\Admin\SimpleStatusIndexRequest;
use App\Http\Requests\Admin\StoreArticleCategoryRequest;
use App\Http\Requests\Admin\StoreArticleInlineImageRequest;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\StoreTagRequest;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class ContentController extends Controller
{
    public function articlesIndex(ArticleIndexRequest $request): Response
    {
        $filters = $request->filters();

        $records = Article::query()
            ->with(['category:id,name', 'tags:id,name'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('title', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('slug', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('excerpt', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'published', function ($query): void {
                $query
                    ->where('is_published', true)
                    ->where(function ($innerQuery): void {
                        $innerQuery->whereNull('published_at')->orWhere('published_at', '<=', now());
                    });
            })
            ->when($filters['status'] === 'scheduled', fn ($query) => $query
                ->where('is_published', true)
                ->where('published_at', '>', now()))
            ->when($filters['status'] === 'draft', fn ($query) => $query->where('is_published', false))
            ->when($filters['category'] !== 'all', fn ($query) => $query->where('category_id', $filters['category']))
            ->latest('created_at')
            ->paginate($request->perPage())
            ->withQueryString();

        $records->through(fn (Article $article) => $this->transformArticleRow($article));

        return inertia('Admin/Articles/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'draft', 'label' => 'Draft'],
            ],
            'categoryOptions' => ArticleCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ArticleCategory $category) => [
                    'value' => (string) $category->id,
                    'label' => $category->name,
                ])
                ->values(),
            'summary' => [
                'total' => Article::query()->count(),
                'published' => Article::query()->published()->count(),
                'scheduled' => Article::query()
                    ->where('is_published', true)
                    ->where('published_at', '>', now())
                    ->count(),
                'draft' => Article::query()->where('is_published', false)->count(),
                'categories' => ArticleCategory::query()->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'createUrl' => route('admin.content.articles.create'),
            'categoriesUrl' => route('admin.content.categories.index'),
            'tagsUrl' => route('admin.content.tags.index'),
        ]);
    }

    public function articlesCreate(): Response
    {
        return inertia('Admin/Articles/Form', [
            'mode' => 'create',
            'record' => $this->articleFormPayload(new Article()),
            'categoryOptions' => $this->articleCategorySelectOptions(),
            'tagOptions' => $this->tagSelectOptions(),
            'imageUploadUrl' => route('admin.content.articles.images.store'),
            'indexUrl' => route('admin.content.articles.index'),
            'submitUrl' => route('admin.content.articles.store'),
        ]);
    }

    public function articlesUploadImage(StoreArticleInlineImageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $path = $request->file('image')->store('articles/inline', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
            'alt' => $validated['alt'] ?? null,
        ]);
    }

    public function articlesStore(StoreArticleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $article = new Article();
        $this->persistArticle($article, $validated, $request);

        return redirect()
            ->route('admin.content.articles.index')
            ->with('success', 'Artikel berhasil ditambahkan.');
    }

    public function articlesEdit(Article $article): Response
    {
        $article->loadMissing(['category:id,name', 'tags:id,name']);

        return inertia('Admin/Articles/Form', [
            'mode' => 'edit',
            'record' => $this->articleFormPayload($article),
            'categoryOptions' => $this->articleCategorySelectOptions(),
            'tagOptions' => $this->tagSelectOptions(),
            'imageUploadUrl' => route('admin.content.articles.images.store'),
            'indexUrl' => route('admin.content.articles.index'),
            'submitUrl' => route('admin.content.articles.update', $article),
        ]);
    }

    public function articlesUpdate(StoreArticleRequest $request, Article $article): RedirectResponse
    {
        $validated = $request->validated();
        $this->persistArticle($article, $validated, $request);

        return redirect()
            ->route('admin.content.articles.index')
            ->with('success', 'Artikel berhasil diperbarui.');
    }

    public function articlesDestroy(Article $article): RedirectResponse
    {
        if (filled($article->cover_image_path) && Storage::disk('public')->exists($article->cover_image_path)) {
            Storage::disk('public')->delete($article->cover_image_path);
        }

        $article->tags()->detach();
        $article->delete();

        return redirect()
            ->route('admin.content.articles.index')
            ->with('success', 'Artikel berhasil dihapus.');
    }

    public function articleCategoriesIndex(SimpleStatusIndexRequest $request): Response
    {
        $filters = $request->filters();

        $records = ArticleCategory::query()
            ->withCount('articles')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('slug', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ArticleCategory $category) => $this->transformArticleCategoryRow($category))
            ->values();

        return inertia('Admin/ArticleCategories/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'summary' => [
                'total' => ArticleCategory::query()->count(),
                'active' => ArticleCategory::query()->where('is_active', true)->count(),
                'show_in_nav' => ArticleCategory::query()->where('show_in_nav', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.categories.create'),
            'reorderUrl' => route('admin.content.categories.reorder'),
            'articlesUrl' => route('admin.content.articles.index'),
        ]);
    }

    public function articleCategoriesCreate(): Response
    {
        return inertia('Admin/ArticleCategories/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'slug' => '',
                'description' => '',
                'sort_order' => 0,
                'is_active' => true,
                'show_in_nav' => false,
            ],
            'indexUrl' => route('admin.content.categories.index'),
            'submitUrl' => route('admin.content.categories.store'),
        ]);
    }

    public function articleCategoriesStore(StoreArticleCategoryRequest $request): RedirectResponse
    {
        ArticleCategory::query()->create($request->validated());

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Kategori artikel berhasil ditambahkan.');
    }

    public function articleCategoriesEdit(ArticleCategory $articleCategory): Response
    {
        return inertia('Admin/ArticleCategories/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $articleCategory->id,
                'name' => $articleCategory->name,
                'slug' => $articleCategory->slug,
                'description' => $articleCategory->description,
                'sort_order' => (int) $articleCategory->sort_order,
                'is_active' => (bool) $articleCategory->is_active,
                'show_in_nav' => (bool) $articleCategory->show_in_nav,
            ],
            'indexUrl' => route('admin.content.categories.index'),
            'submitUrl' => route('admin.content.categories.update', $articleCategory),
        ]);
    }

    public function articleCategoriesUpdate(
        StoreArticleCategoryRequest $request,
        ArticleCategory $articleCategory
    ): RedirectResponse {
        $articleCategory->update($request->validated());

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Kategori artikel berhasil diperbarui.');
    }

    public function articleCategoriesDestroy(ArticleCategory $articleCategory): RedirectResponse
    {
        $articleCategory->delete();

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Kategori artikel berhasil dihapus.');
    }

    public function articleCategoriesReorder(ReorderArticleCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->syncSortOrder(ArticleCategory::query(), $validated['ids']);

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Urutan kategori artikel berhasil diperbarui.');
    }

    public function tagsIndex(SimpleStatusIndexRequest $request): Response
    {
        $filters = $request->filters();

        $records = Tag::query()
            ->withCount('articles')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('slug', 'like', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => $this->transformTagRow($tag))
            ->values();

        return inertia('Admin/Tags/Index', [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'summary' => [
                'total' => Tag::query()->count(),
                'active' => Tag::query()->where('is_active', true)->count(),
                'articles' => Tag::query()->withCount('articles')->get()->sum('articles_count'),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.tags.create'),
            'reorderUrl' => route('admin.content.tags.reorder'),
            'articlesUrl' => route('admin.content.articles.index'),
        ]);
    }

    public function tagsCreate(): Response
    {
        return inertia('Admin/Tags/Form', [
            'mode' => 'create',
            'record' => [
                'name' => '',
                'slug' => '',
                'is_active' => true,
                'sort_order' => 0,
            ],
            'indexUrl' => route('admin.content.tags.index'),
            'submitUrl' => route('admin.content.tags.store'),
        ]);
    }

    public function tagsStore(StoreTagRequest $request): RedirectResponse
    {
        Tag::query()->create([
            ...$request->validated(),
            'sort_order' => $this->nextSortOrder(Tag::query()),
        ]);

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Tag artikel berhasil ditambahkan.');
    }

    public function tagsEdit(Tag $tag): Response
    {
        return inertia('Admin/Tags/Form', [
            'mode' => 'edit',
            'record' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'is_active' => (bool) $tag->is_active,
                'sort_order' => (int) $tag->sort_order,
            ],
            'indexUrl' => route('admin.content.tags.index'),
            'submitUrl' => route('admin.content.tags.update', $tag),
        ]);
    }

    public function tagsUpdate(StoreTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Tag artikel berhasil diperbarui.');
    }

    public function tagsDestroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Tag artikel berhasil dihapus.');
    }

    public function tagsReorder(ReorderTagRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->syncSortOrder(Tag::query(), $validated['ids']);

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Urutan tag artikel berhasil diperbarui.');
    }

    private function transformArticleRow(Article $article): array
    {
        $article->loadMissing(['category:id,name', 'tags:id,name']);

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'cover_url' => filled($article->cover_image_path) ? Storage::disk('public')->url($article->cover_image_path) : null,
            'category_name' => $article->category?->name,
            'tag_names' => $article->tags->pluck('name')->values()->all(),
            'is_published' => (bool) $article->is_published,
            'editorial_status_value' => $this->resolveArticleEditorialStatus($article),
            'editorial_status_label' => $this->resolveArticleEditorialStatusLabel($article),
            'published_at' => $article->published_at?->toIso8601String(),
            'views' => (int) ($article->views ?? 0),
            'preview_url' => route('articles.show', $article->slug) . '?preview=1',
            'edit_url' => route('admin.content.articles.edit', $article),
            'destroy_url' => route('admin.content.articles.destroy', $article),
        ];
    }

    private function transformArticleCategoryRow(ArticleCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'sort_order' => (int) $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'show_in_nav' => (bool) $category->show_in_nav,
            'articles_count' => (int) ($category->articles_count ?? 0),
            'updated_at' => $category->updated_at?->toIso8601String(),
            'edit_url' => route('admin.content.categories.edit', $category),
            'destroy_url' => route('admin.content.categories.destroy', $category),
        ];
    }

    private function transformTagRow(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'sort_order' => (int) $tag->sort_order,
            'is_active' => (bool) $tag->is_active,
            'articles_count' => (int) ($tag->articles_count ?? 0),
            'updated_at' => $tag->updated_at?->toIso8601String(),
            'edit_url' => route('admin.content.tags.edit', $tag),
            'destroy_url' => route('admin.content.tags.destroy', $tag),
        ];
    }

    private function articleFormPayload(Article $article): array
    {
        $article->loadMissing(['tags:id,name']);

        return [
            'id' => $article->id,
            'title' => $article->title ?? '',
            'slug' => $article->slug ?? '',
            'excerpt' => $article->excerpt ?? '',
            'content_html' => $article->content_html ?? '',
            'cover_image_path' => $article->cover_image_path,
            'cover_url' => filled($article->cover_image_path) ? Storage::disk('public')->url($article->cover_image_path) : null,
            'meta_title' => $article->meta_title ?? '',
            'meta_description' => $article->meta_description ?? '',
            'category_id' => $article->category_id ? (string) $article->category_id : '__none',
            'tag_ids' => $article->tags->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'is_published' => (bool) ($article->is_published ?? false),
            'published_at' => $article->published_at?->format('Y-m-d\\TH:i'),
            'preview_url' => $article->exists ? route('articles.show', $article->slug) . '?preview=1' : null,
        ];
    }

    private function articleCategorySelectOptions(): array
    {
        return ArticleCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ArticleCategory $category) => [
                'value' => (string) $category->id,
                'label' => $category->name,
            ])
            ->values()
            ->all();
    }

    private function tagSelectOptions(): array
    {
        return Tag::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Tag $tag) => [
                'value' => (string) $tag->id,
                'label' => $tag->name,
            ])
            ->values()
            ->all();
    }

    private function persistArticle(Article $article, array $validated, Request $request): void
    {
        $coverPath = $article->cover_image_path;
        $oldCoverPath = $article->cover_image_path;
        $newCoverPath = null;

        if ($request->hasFile('cover_image')) {
            $newCoverPath = $request->file('cover_image')->store('articles', 'public');
            $coverPath = $newCoverPath;
        }

        $isPublished = (bool) ($validated['is_published'] ?? false);
        $publishedAt = $validated['published_at'] ?? null;
        if ($isPublished && blank($publishedAt)) {
            $publishedAt = now();
        }

        if (! $isPublished) {
            $publishedAt = null;
        }

        try {
            DB::transaction(function () use ($article, $validated, $coverPath, $isPublished, $publishedAt): void {
                $article->fill([
                    'title' => $validated['title'],
                    'slug' => $validated['slug'],
                    'excerpt' => $validated['excerpt'] ?? null,
                    'content_html' => $validated['content_html'],
                    'cover_image_path' => $coverPath,
                    'meta_title' => $validated['meta_title'] ?? null,
                    'meta_description' => $validated['meta_description'] ?? null,
                    'category_id' => $validated['category_id'] ?: null,
                    'is_published' => $isPublished,
                    'published_at' => $publishedAt,
                ]);
                $article->save();
                $article->tags()->sync($validated['tag_ids'] ?? []);
            });
        } catch (\Throwable $exception) {
            if ($newCoverPath && Storage::disk('public')->exists($newCoverPath)) {
                Storage::disk('public')->delete($newCoverPath);
            }

            throw $exception;
        }

        if (
            $newCoverPath
            && filled($oldCoverPath)
            && $oldCoverPath !== $newCoverPath
            && Storage::disk('public')->exists($oldCoverPath)
        ) {
            Storage::disk('public')->delete($oldCoverPath);
        }
    }

    private function resolveArticleEditorialStatus(Article $article): string
    {
        if (! $article->is_published) {
            return 'draft';
        }

        if ($article->published_at?->isFuture()) {
            return 'scheduled';
        }

        return 'published';
    }

    private function resolveArticleEditorialStatusLabel(Article $article): string
    {
        return match ($this->resolveArticleEditorialStatus($article)) {
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            default => 'Published',
        };
    }

    private function nextSortOrder($query): int
    {
        return ((int) $query->max('sort_order')) + 1;
    }

    private function syncSortOrder($query, array $ids): void
    {
        DB::transaction(function () use ($query, $ids): void {
            foreach (array_values($ids) as $index => $id) {
                (clone $query)
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

}
