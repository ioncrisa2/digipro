<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminContentWorkspaceService
{
    public function articlesIndexPayload(array $filters, int $perPage): array
    {
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
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (Article $article) => $this->transformArticleRow($article));

        return [
            'filters' => $filters,
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'draft', 'label' => 'Draft'],
            ],
            'categoryOptions' => $this->articleCategorySelectOptions(),
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
        ];
    }

    public function articleFormPagePayload(Article $article, string $mode): array
    {
        return [
            'mode' => $mode,
            'record' => $this->articleFormPayload($article),
            'categoryOptions' => $this->articleCategorySelectOptions(),
            'tagOptions' => $this->tagSelectOptions(),
            'imageUploadUrl' => route('admin.content.articles.images.store'),
            'indexUrl' => route('admin.content.articles.index'),
            'submitUrl' => $mode === 'create'
                ? route('admin.content.articles.store')
                : route('admin.content.articles.update', $article),
        ];
    }

    public function storeInlineImage(UploadedFile $image, ?string $alt = null): array
    {
        $path = $image->store('articles/inline', 'public');

        return [
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
            'alt' => $alt,
        ];
    }

    public function saveArticle(Article $article, array $validated, Request $request): void
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

    public function destroyArticle(Article $article): void
    {
        if (filled($article->cover_image_path) && Storage::disk('public')->exists($article->cover_image_path)) {
            Storage::disk('public')->delete($article->cover_image_path);
        }

        $article->tags()->detach();
        $article->delete();
    }

    public function articleCategoriesIndexPayload(array $filters): array
    {
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
            ->values()
            ->all();

        return [
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => ArticleCategory::query()->count(),
                'active' => ArticleCategory::query()->where('is_active', true)->count(),
                'show_in_nav' => ArticleCategory::query()->where('show_in_nav', true)->count(),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.categories.create'),
            'reorderUrl' => route('admin.content.categories.reorder'),
            'articlesUrl' => route('admin.content.articles.index'),
        ];
    }

    public function articleCategoryFormPayload(?ArticleCategory $articleCategory = null): array
    {
        return [
            'id' => $articleCategory?->id,
            'name' => $articleCategory?->name ?? '',
            'slug' => $articleCategory?->slug ?? '',
            'description' => $articleCategory?->description ?? '',
            'sort_order' => (int) ($articleCategory?->sort_order ?? 0),
            'is_active' => (bool) ($articleCategory?->is_active ?? true),
            'show_in_nav' => (bool) ($articleCategory?->show_in_nav ?? false),
        ];
    }

    public function reorderArticleCategories(array $ids): void
    {
        $this->syncSortOrder(ArticleCategory::query(), $ids);
    }

    public function tagsIndexPayload(array $filters): array
    {
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
            ->values()
            ->all();

        return [
            'filters' => $filters,
            'statusOptions' => $this->simpleStatusOptions(),
            'summary' => [
                'total' => Tag::query()->count(),
                'active' => Tag::query()->where('is_active', true)->count(),
                'articles' => Tag::query()->withCount('articles')->get()->sum('articles_count'),
            ],
            'records' => $records,
            'createUrl' => route('admin.content.tags.create'),
            'reorderUrl' => route('admin.content.tags.reorder'),
            'articlesUrl' => route('admin.content.articles.index'),
        ];
    }

    public function tagFormPayload(?Tag $tag = null): array
    {
        return [
            'id' => $tag?->id,
            'name' => $tag?->name ?? '',
            'slug' => $tag?->slug ?? '',
            'is_active' => (bool) ($tag?->is_active ?? true),
            'sort_order' => (int) ($tag?->sort_order ?? 0),
        ];
    }

    public function createTag(array $validated): void
    {
        Tag::query()->create([
            ...$validated,
            'sort_order' => $this->nextSortOrder(Tag::query()),
        ]);
    }

    public function reorderTags(array $ids): void
    {
        $this->syncSortOrder(Tag::query(), $ids);
    }

    private function transformArticleRow(Article $article): array
    {
        $article->loadMissing(['category:id,name', 'tags:id,name']);

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'cover_url' => $this->publicUrl($article->cover_image_path),
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
            'cover_url' => $this->publicUrl($article->cover_image_path),
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

    private function nextSortOrder(Builder $query): int
    {
        return ((int) $query->max('sort_order')) + 1;
    }

    private function syncSortOrder(Builder $query, array $ids): void
    {
        DB::transaction(function () use ($query, $ids): void {
            foreach (array_values($ids) as $index => $id) {
                (clone $query)
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    private function simpleStatusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua Status'],
            ['value' => 'active', 'label' => 'Aktif'],
            ['value' => 'inactive', 'label' => 'Nonaktif'],
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }
}
