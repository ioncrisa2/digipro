<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use Illuminate\Http\Request;

/**
 * Renders public article listing and detail pages.
 */
class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $categorySlug = $request->string('category')->trim()->toString();
        $search = $request->string('q')->trim()->toString();
        $scope = $request->string('scope')->trim()->lower()->toString() ?: 'article';
        $allowedScopes = ['article', 'category', 'tag'];
        if (! in_array($scope, $allowedScopes, true)) {
            $scope = 'article';
        }

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

                // default: article title/excerpt/content
                $query->where(function ($q) use ($like) {
                    $q->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('content_html', 'like', $like);
                });
            })
            ->orderByDesc('published_at')
            ->paginate(9)
            ->appends($request->only(['category', 'q', 'scope']))
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
                    $query->where('is_published', true);
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
            'filters' => [
                'category' => $categorySlug,
                'q' => $search,
                'scope' => $scope,
            ],
            'activeCategory' => $activeCategory,
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function show(string $slug)
    {
        $article = Article::query()
            ->with(['category:id,name', 'tags:id,name'])
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $article->is_published && ! auth()->check()) {
            abort(404);
        }

        if (! request()->boolean('preview')) {
            $sessionKey = 'article_viewed.' . $article->id;
            if (! request()->session()->has($sessionKey)) {
                $article->increment('views');
                request()->session()->put($sessionKey, true);
                $article->refresh();
            }
        }

        return inertia('Articles/Show', [
            'article' => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'content_html' => $article->content_html,
                'cover_image_path' => $article->cover_image_path,
                'published_at' => $article->published_at?->toDateString(),
                'meta_title' => $article->meta_title,
                'meta_description' => $article->meta_description,
                'category' => $article->category?->name,
                'tags' => $article->tags->pluck('name'),
                'views' => $article->views ?? 0,
            ],
        ]);
    }
}
