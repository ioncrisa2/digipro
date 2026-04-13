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
use App\Services\Admin\AdminContentWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class ContentController extends Controller
{
    public function __construct(
        private readonly AdminContentWorkspaceService $contentWorkspaceService,
    ) {
    }

    public function articlesIndex(ArticleIndexRequest $request): Response
    {
        return inertia('Admin/Articles/Index', $this->contentWorkspaceService
            ->articlesIndexPayload($request->filters(), $request->perPage()));
    }

    public function articlesCreate(): Response
    {
        return inertia('Admin/Articles/Form', $this->contentWorkspaceService
            ->articleFormPagePayload(new Article(), 'create'));
    }

    public function articlesUploadImage(StoreArticleInlineImageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $this->contentWorkspaceService->storeInlineImage($request->file('image'), $validated['alt'] ?? null)
        );
    }

    public function articlesStore(StoreArticleRequest $request): RedirectResponse
    {
        $this->contentWorkspaceService->saveArticle(new Article(), $request->validated(), $request);

        return redirect()
            ->route('admin.content.articles.index')
            ->with('success', 'Artikel berhasil ditambahkan.');
    }

    public function articlesEdit(Article $article): Response
    {
        return inertia('Admin/Articles/Form', $this->contentWorkspaceService
            ->articleFormPagePayload($article, 'edit'));
    }

    public function articlesUpdate(StoreArticleRequest $request, Article $article): RedirectResponse
    {
        $this->contentWorkspaceService->saveArticle($article, $request->validated(), $request);

        return redirect()
            ->route('admin.content.articles.index')
            ->with('success', 'Artikel berhasil diperbarui.');
    }

    public function articlesDestroy(Article $article): RedirectResponse
    {
        $this->contentWorkspaceService->destroyArticle($article);

        return redirect()
            ->route('admin.content.articles.index')
            ->with('success', 'Artikel berhasil dihapus.');
    }

    public function articleCategoriesIndex(SimpleStatusIndexRequest $request): Response
    {
        return inertia('Admin/ArticleCategories/Index', $this->contentWorkspaceService
            ->articleCategoriesIndexPayload($request->filters()));
    }

    public function articleCategoriesCreate(): Response
    {
        return inertia('Admin/ArticleCategories/Form', $this->contentWorkspaceService
            ->articleCategoryFormPagePayload(null, 'create'));
    }

    public function articleCategoriesStore(StoreArticleCategoryRequest $request): RedirectResponse
    {
        $this->contentWorkspaceService->saveArticleCategory($request->validated());

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Kategori artikel berhasil ditambahkan.');
    }

    public function articleCategoriesEdit(ArticleCategory $articleCategory): Response
    {
        return inertia('Admin/ArticleCategories/Form', $this->contentWorkspaceService
            ->articleCategoryFormPagePayload($articleCategory, 'edit'));
    }

    public function articleCategoriesUpdate(
        StoreArticleCategoryRequest $request,
        ArticleCategory $articleCategory
    ): RedirectResponse {
        $this->contentWorkspaceService->saveArticleCategory($request->validated(), $articleCategory);

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Kategori artikel berhasil diperbarui.');
    }

    public function articleCategoriesDestroy(ArticleCategory $articleCategory): RedirectResponse
    {
        $this->contentWorkspaceService->deleteArticleCategory($articleCategory);

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Kategori artikel berhasil dihapus.');
    }

    public function articleCategoriesReorder(ReorderArticleCategoryRequest $request): RedirectResponse
    {
        $this->contentWorkspaceService->reorderArticleCategories($request->validated()['ids']);

        return redirect()
            ->route('admin.content.categories.index')
            ->with('success', 'Urutan kategori artikel berhasil diperbarui.');
    }

    public function tagsIndex(SimpleStatusIndexRequest $request): Response
    {
        return inertia('Admin/Tags/Index', $this->contentWorkspaceService
            ->tagsIndexPayload($request->filters()));
    }

    public function tagsCreate(): Response
    {
        return inertia('Admin/Tags/Form', $this->contentWorkspaceService
            ->tagFormPagePayload(null, 'create'));
    }

    public function tagsStore(StoreTagRequest $request): RedirectResponse
    {
        $this->contentWorkspaceService->saveTag($request->validated());

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Tag artikel berhasil ditambahkan.');
    }

    public function tagsEdit(Tag $tag): Response
    {
        return inertia('Admin/Tags/Form', $this->contentWorkspaceService
            ->tagFormPagePayload($tag, 'edit'));
    }

    public function tagsUpdate(StoreTagRequest $request, Tag $tag): RedirectResponse
    {
        $this->contentWorkspaceService->saveTag($request->validated(), $tag);

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Tag artikel berhasil diperbarui.');
    }

    public function tagsDestroy(Tag $tag): RedirectResponse
    {
        $this->contentWorkspaceService->deleteTag($tag);

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Tag artikel berhasil dihapus.');
    }

    public function tagsReorder(ReorderTagRequest $request): RedirectResponse
    {
        $this->contentWorkspaceService->reorderTags($request->validated()['ids']);

        return redirect()
            ->route('admin.content.tags.index')
            ->with('success', 'Urutan tag artikel berhasil diperbarui.');
    }
}
