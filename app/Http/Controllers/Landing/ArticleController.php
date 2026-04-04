<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landing\ArticleIndexRequest;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(ArticleIndexRequest $request)
    {
        $filters = $request->filters();
        $categorySlug = $filters['category'];
        $search = $filters['q'];
        $scope = $filters['scope'];

        $articles = Article::query()
            ->with(['category:id,name,slug', 'tags:id,name'])
            ->published()
            ->when($categorySlug, function ($query) use ($categorySlug) {
                $query->whereHas('category', function ($q) use ($categorySlug) {
                    $q->where('slug', $categorySlug)->where('is_active', true);
                });
            })
            ->when($search, function ($query) use ($search, $scope) {
                $like = '%' . $search . '%';

                if ($scope === 'category') {
                    $query->whereHas('category', function ($q) use ($like) {
                        $q->where('name', 'like', $like)->where('is_active', true);
                    });

                    return;
                }

                if ($scope === 'tag') {
                    $query->whereHas('tags', function ($q) use ($like) {
                        $q->where('name', 'like', $like);
                    });

                    return;
                }

                $query->where(function ($q) use ($like) {
                    $q->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('content_html', 'like', $like);
                });
            })
            ->orderByDesc('published_at')
            ->paginate(9)
            ->appends($request->appendableFilters())
            ->through(function (Article $article) {
                return [
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'cover_image_path' => $article->cover_image_path,
                    'published_at' => $article->published_at?->toDateString(),
                    'category' => $article->category?->name,
                    'category_slug' => $article->category?->slug,
                    'tags' => $article->tags->pluck('name'),
                ];
            });

        $activeCategory = null;
        if ($categorySlug) {
            $activeCategory = ArticleCategory::query()
                ->active()
                ->where('slug', $categorySlug)
                ->first(['name', 'slug']);
        }

        $categories = ArticleCategory::query()
            ->active()
            ->withCount([
                'articles as articles_count' => function ($query) {
                    $query
                        ->where('is_published', true)
                        ->where(function ($innerQuery): void {
                            $innerQuery->whereNull('published_at')->orWhere('published_at', '<=', now());
                        });
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $tags = Tag::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return inertia('Articles/Index', [
            'articles' => $articles,
            'filters' => $filters,
            'activeCategory' => $activeCategory,
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $article = Article::query()
            ->with(['category:id,name,slug', 'tags:id,name'])
            ->where('slug', $slug)
            ->firstOrFail();

        $isPreview = $request->boolean('preview');
        $canPreviewUnpublished = $request->user()?->hasAdminAccess() ?? false;

        if ((! $article->is_published || $article->published_at?->isFuture()) && ! ($isPreview && $canPreviewUnpublished)) {
            abort(404);
        }

        if (! $isPreview) {
            $sessionKey = 'article_viewed.' . $article->id;
            if (! $request->session()->has($sessionKey)) {
                $article->increment('views');
                $request->session()->put($sessionKey, true);
                $article->refresh();
            }
        }

        $tagIds = $article->tags->pluck('id')->all();

        $relatedArticles = Article::query()
            ->with(['category:id,name,slug', 'tags:id,name'])
            ->published()
            ->whereKeyNot($article->id)
            ->select('articles.*')
            ->selectRaw(
                'case when category_id = ? then 1 else 0 end as category_match',
                [$article->category_id]
            )
            ->withCount([
                'tags as matching_tags_count' => fn ($query) => $query->whereIn('tags.id', $tagIds ?: [0]),
            ])
            ->when($article->category_id || $tagIds !== [], function ($query) use ($article, $tagIds) {
                $query->where(function ($nested) use ($article, $tagIds) {
                    if ($article->category_id) {
                        $nested->where('category_id', $article->category_id);
                    }

                    if ($tagIds !== []) {
                        $nested->orWhereHas('tags', fn ($tagQuery) => $tagQuery->whereIn('tags.id', $tagIds));
                    }
                });
            })
            ->orderByDesc('category_match')
            ->orderByDesc('matching_tags_count')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Article $relatedArticle) => $this->transformPublicArticle($relatedArticle))
            ->values();

        return inertia('Articles/Show', [
            'article' => [
                ...$this->transformPublicArticle($article),
                'content_html' => $article->content_html,
                'meta_title' => $article->meta_title,
                'meta_description' => $article->meta_description,
                'views' => $article->views ?? 0,
            ],
            'relatedArticles' => $relatedArticles,
        ]);
    }

    private function transformPublicArticle(Article $article): array
    {
        return [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'cover_image_path' => $article->cover_image_path,
            'published_at' => $article->published_at?->toDateString(),
            'category' => $article->category?->name,
            'category_slug' => $article->category?->slug,
            'tags' => $article->tags->pluck('name')->values()->all(),
        ];
    }
}
