<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import BlogPageLayout from '@/layouts/BlogPageLayout.vue'
import ArticleCover from '@/components/blog/ArticleCover.vue'
import ArticleMeta from '@/components/blog/ArticleMeta.vue'

const props = defineProps({
  articles: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  categories: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
})

const rows = computed(() => props.articles?.data ?? [])
const links = computed(() => props.articles?.links ?? [])
const featuredArticle = computed(() => rows.value[0])
const regularArticles = computed(() => rows.value.slice(1))

const searchQuery = ref(props.filters?.q || '')
const searchScope = ref(props.filters?.scope || 'article')
const searchScopeOptions = [
  { label: 'Artikel', value: 'article' },
  { label: 'Kategori', value: 'category' },
  { label: 'Tag', value: 'tag' },
]

const submitSearch = () => {
  router.get(
    route('articles.index'),
    {
      category: props.filters?.category || undefined,
      q: searchQuery.value || undefined,
      scope: searchScope.value || undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    },
  )
}
</script>

<template>
  <BlogPageLayout title="Blog - DigiPro">
      <!-- Hero Section -->
      <div class="bg-gradient-to-b from-slate-50 to-white border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-16">
          <div class="max-w-3xl">
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight text-slate-900 mb-4">
              Artikel & Wawasan
            </h1>
            <p class="text-lg md:text-xl text-slate-600 leading-relaxed">
              Panduan praktis, insight mendalam, dan update terbaru seputar penilaian properti, regulasi, dan best practices di industri.
            </p>
          </div>
        </div>
      </div>

      <!-- Main Content with Sidebar -->
      <div class="bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
            <!-- Articles Column -->
            <div class="lg:col-span-8 space-y-10">
              <!-- Featured Article (Large Card) -->
              <div v-if="featuredArticle" class="border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
                <Link
                  :href="route('articles.show', featuredArticle.slug)"
                  class="group grid md:grid-cols-2 gap-0 items-stretch"
                >
                  <ArticleCover
                    :cover-path="featuredArticle.cover_image_path"
                    :alt="featuredArticle.title"
                    fallback-text="DigiPro Featured"
                    wrapper-class="aspect-[16/10] md:aspect-auto bg-slate-100 overflow-hidden"
                    image-class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                    fallback-class="h-full w-full flex items-center justify-center text-slate-300 text-sm font-medium"
                  />

                  <!-- Content -->
                  <div class="p-6 md:p-8 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-900 text-white text-xs font-semibold tracking-wide">
                      FEATURED
                    </div>

                    <h2 class="text-2xl md:text-3xl font-bold text-slate-900 group-hover:text-slate-700 transition-colors leading-tight">
                      {{ featuredArticle.title }}
                    </h2>

                    <p class="text-base md:text-lg text-slate-600 line-clamp-3 leading-relaxed">
                      {{ featuredArticle.excerpt || 'Baca artikel lengkap untuk mengetahui lebih detail.' }}
                    </p>

                    <ArticleMeta
                      :published-at="featuredArticle.published_at"
                      :read-source="featuredArticle.excerpt"
                      read-suffix="read"
                      :category="featuredArticle.category"
                      container-class="flex flex-wrap items-center gap-3 text-sm text-slate-500"
                    />
                  </div>
                </Link>
              </div>

              <!-- Article Grid -->
              <div v-if="regularArticles.length" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <Link
                  v-for="article in regularArticles"
                  :key="article.slug"
                  :href="route('articles.show', article.slug)"
                  class="group flex flex-col border border-slate-100 rounded-2xl shadow-sm overflow-hidden"
                >
                  <ArticleCover
                    :cover-path="article.cover_image_path"
                    :alt="article.title"
                    fallback-text="DigiPro"
                    wrapper-class="aspect-[16/10] bg-slate-100 overflow-hidden"
                    image-class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                  />

                  <div class="p-5 space-y-3 flex-1 flex flex-col">
                    <ArticleMeta
                      :published-at="article.published_at"
                      :read-source="article.excerpt"
                      container-class="flex items-center gap-3 text-xs text-slate-500"
                    />

                    <!-- Title -->
                    <h3 class="text-xl font-bold text-slate-900 group-hover:text-slate-700 transition-colors leading-tight">
                      {{ article.title }}
                    </h3>

                    <!-- Excerpt -->
                    <p class="text-sm text-slate-600 line-clamp-2 leading-relaxed">
                      {{ article.excerpt || 'Baca selengkapnya untuk mengetahui detail artikel ini.' }}
                    </p>

                    <!-- Category Badge -->
                    <div class="mt-auto pt-2">
                      <span v-if="article.category" class="inline-block px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium">
                        {{ article.category }}
                      </span>
                    </div>
                  </div>
                </Link>
              </div>

              <div v-else-if="!featuredArticle" class="text-center py-20 border border-dashed border-slate-200 rounded-2xl">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 text-slate-400 mb-4">
                  <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 mb-2">Belum ada artikel</h3>
                <p class="text-slate-600">Artikel akan segera dipublikasikan.</p>
              </div>

              <!-- Pagination -->
              <div v-if="links.length > 3" class="flex items-center justify-center gap-2">
                <Link
                  v-for="(link, idx) in links"
                  :key="idx"
                  :href="link.url || '#'"
                  class="min-w-[40px] h-10 px-4 rounded-lg text-sm font-medium transition-all inline-flex items-center justify-center"
                  :class="link.active
                    ? 'bg-slate-900 text-white shadow-sm'
                    : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200'"
                  v-html="link.label"
                />
              </div>
            </div>

            <!-- Sidebar -->
            <aside class="lg:col-span-4 space-y-6">
              <!-- Search -->
              <div class="border border-slate-100 rounded-2xl shadow-sm p-5 bg-slate-50">
                <h3 class="text-sm font-semibold text-slate-900 mb-3">Search</h3>
                <form class="space-y-3" @submit.prevent="submitSearch">
                  <div class="grid grid-cols-10 gap-2 items-center">
                    <input
                      v-model="searchQuery"
                      type="search"
                      placeholder="Cari di blog DigiPro..."
                      class="col-span-7 w-full min-w-0 text-sm px-4 py-2 rounded-xl border border-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none bg-white"
                    />
                    <button
                      type="submit"
                      class="col-span-3 w-full rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-3 py-2 text-sm font-semibold transition"
                    >
                      Cari
                    </button>
                  </div>

                  <fieldset class="space-y-2">
                    <legend class="text-xs font-medium text-slate-500">Cari berdasarkan</legend>

                    <div class="grid grid-cols-3 gap-2">
                      <label
                        v-for="option in searchScopeOptions"
                        :key="option.value"
                        class="cursor-pointer"
                      >
                        <input
                          v-model="searchScope"
                          type="radio"
                          name="search-scope"
                          :value="option.value"
                          class="sr-only"
                        />
                        <span
                          class="block w-full text-center text-xs font-medium px-3 py-2 rounded-full border transition"
                          :class="searchScope === option.value
                            ? 'bg-slate-900 border-slate-900 text-white shadow-sm'
                            : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'"
                        >
                          {{ option.label }}
                        </span>
                      </label>
                    </div>
                  </fieldset>
                </form>
              </div>

              <!-- Categories -->
              <div class="border border-slate-100 rounded-2xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                  <h3 class="text-sm font-semibold text-slate-900">Categories</h3>
                  <span class="text-xs text-slate-500">{{ categories.length }} items</span>
                </div>
                <div class="space-y-2">
                  <Link
                    v-for="category in categories"
                    :key="category.slug"
                    :href="route('articles.index', { category: category.slug })"
                    class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition"
                    :class="category.slug === props.filters?.category
                      ? 'bg-slate-900 text-white'
                      : 'text-slate-700 hover:bg-slate-50'"
                  >
                    <span>{{ category.name }}</span>
                    <span
                      class="inline-flex items-center justify-center min-w-[1.75rem] px-2 py-0.5 rounded-full text-xs font-semibold"
                      :class="category.slug === props.filters?.category
                        ? 'bg-white/20 text-white'
                        : 'bg-slate-100 text-slate-700'"
                    >
                      {{ category.articles_count ?? 0 }}
                    </span>
                  </Link>
                </div>
              </div>

              <!-- Tags -->
              <div class="border border-slate-100 rounded-2xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                  <h3 class="text-sm font-semibold text-slate-900">Tags</h3>
                  <span class="text-xs text-slate-500">{{ tags.length }} items</span>
                </div>
                <div class="flex flex-wrap gap-2">
                  <Link
                    v-for="tag in tags"
                    :key="tag.slug"
                    :href="route('articles.index', { scope: 'tag', q: tag.name })"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold border border-slate-200 text-slate-700 hover:bg-slate-50 transition"
                  >
                    #{{ tag.name }}
                  </Link>
                </div>
              </div>
            </aside>
          </div>
        </div>
      </div>
  </BlogPageLayout>
</template>

<style scoped>
/* Custom selection color */
::selection {
  background-color: #0f172a;
  color: #ffffff;
}
</style>
