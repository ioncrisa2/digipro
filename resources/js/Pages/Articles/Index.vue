<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ArrowRight, Search } from 'lucide-vue-next'
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
const featuredArticle = computed(() => rows.value[0] ?? null)
const regularArticles = computed(() => rows.value.slice(1))
const activeCategory = computed(() => props.filters?.category || null)

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
      category: activeCategory.value || undefined,
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
  <BlogPageLayout title="Artikel DigiPro by KJPP HJAR" description="Insight, panduan, dan pembacaan pasar properti dari DigiPro by KJPP HJAR.">
    <section class="border-b border-black/5 bg-slate-950 text-white">
      <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-end">
          <div class="max-w-3xl space-y-6">
            <p class="text-xs font-semibold uppercase text-emerald-200">
              DigiPro by KJPP HJAR
            </p>
            <h1 class="text-balance text-4xl font-semibold leading-[1.02] sm:text-5xl lg:text-6xl">
              Perspektif properti yang lebih tajam, operasional, dan bisa dipakai.
            </h1>
            <p class="max-w-2xl text-pretty text-base leading-7 text-slate-200 sm:text-lg">
              Kumpulan artikel DigiPro by KJPP HJAR untuk membahas valuasi, data pasar, regulasi, dan keputusan praktis di sekitar proses penilaian properti.
            </p>

            <div class="flex flex-col gap-3 sm:flex-row">
              <Link
                href="#article-feed"
                class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100 motion-reduce:transition-none"
              >
                Jelajahi Artikel
              </Link>
              <Link
                href="/"
                class="inline-flex items-center justify-center rounded-full border border-white/20 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10 motion-reduce:transition-none"
              >
                Buka DigiPro by KJPP HJAR
              </Link>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5">
              <p class="text-[11px] font-semibold uppercase text-emerald-200">Ruang baca</p>
              <p class="mt-3 text-3xl font-semibold tabular-nums text-white">
                {{ rows.length }}
              </p>
              <p class="mt-2 text-pretty text-sm leading-6 text-slate-200">
                Artikel pada halaman ini siap dibaca untuk mendukung konteks pasar dan penilaian.
              </p>
            </div>

            <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5">
              <p class="text-[11px] font-semibold uppercase text-emerald-200">Fokus</p>
              <p class="mt-3 text-2xl font-semibold text-white">
                Valuasi, pasar, dan regulasi
              </p>
              <p class="mt-2 text-pretty text-sm leading-6 text-slate-200">
                Disusun agar relevan untuk pengguna DigiPro by KJPP HJAR, bukan sekadar artikel umum properti.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="article-feed" class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
      <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="space-y-12">
          <section v-if="featuredArticle" class="space-y-5">
            <div class="flex items-end justify-between gap-4">
              <div>
                <p class="text-[11px] font-semibold uppercase text-emerald-700">Pilihan editor</p>
                <h2 class="mt-2 text-balance text-3xl font-semibold text-slate-950">
                  Sorotan utama pekan ini
                </h2>
              </div>
            </div>

              <Link
                :href="route('articles.show', featuredArticle.slug)"
                class="group grid overflow-hidden rounded-[2rem] border border-black/5 bg-white/80 shadow-[0_20px_60px_rgba(15,23,42,0.08)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_28px_80px_rgba(15,23,42,0.10)] motion-reduce:transition-none motion-reduce:hover:translate-y-0 lg:grid-cols-[1.05fr_0.95fr]"
              >
                <ArticleCover
                  :cover-path="featuredArticle.cover_image_path"
                  :alt="featuredArticle.title"
                  fallback-text="DigiPro by KJPP HJAR"
                  wrapper-class="aspect-[4/3] overflow-hidden bg-slate-200/70 lg:aspect-auto lg:min-h-[28rem]"
                  image-class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.04] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                  fallback-class="flex h-full w-full items-center justify-center bg-slate-950 text-sm font-semibold uppercase text-white/80"
                />

              <div class="flex flex-col justify-between p-7 sm:p-9">
                <div class="space-y-5">
                  <ArticleMeta
                    :published-at="featuredArticle.published_at"
                    :read-source="featuredArticle.excerpt"
                    :category="featuredArticle.category"
                    container-class="flex flex-wrap items-center gap-3 text-sm text-slate-500"
                    category-class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold uppercase text-emerald-700"
                  />

                  <div class="space-y-4">
                    <h3 class="text-balance text-3xl font-semibold leading-tight text-slate-950 sm:text-4xl">
                      {{ featuredArticle.title }}
                    </h3>
                    <p class="max-w-xl text-pretty text-base leading-7 text-slate-600 sm:text-lg">
                      {{ featuredArticle.excerpt || 'Baca artikel lengkap untuk memahami konteks, metode, dan pembacaan pasar yang lebih utuh.' }}
                    </p>
                  </div>
                </div>

                <div class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-slate-950">
                  Baca artikel utama
                  <ArrowRight class="h-4 w-4 transition-transform duration-150 group-hover:translate-x-1 motion-reduce:transition-none motion-reduce:group-hover:translate-x-0" />
                </div>
              </div>
            </Link>
          </section>

          <section class="space-y-5">
            <div class="flex items-end justify-between gap-4">
              <div>
                <p class="text-[11px] font-semibold uppercase text-slate-500">Arsip artikel</p>
                <h2 class="mt-2 text-balance text-3xl font-semibold text-slate-950">
                  Bacaan terbaru
                </h2>
              </div>
              <p class="hidden text-sm text-slate-500 md:block">
                {{ rows.length }} artikel pada halaman ini
              </p>
            </div>

            <div v-if="regularArticles.length" class="divide-y divide-black/6 rounded-[2rem] border border-black/5 bg-white/78 shadow-[0_16px_48px_rgba(15,23,42,0.06)]">
              <Link
                v-for="article in regularArticles"
                :key="article.slug"
                :href="route('articles.show', article.slug)"
                class="group grid gap-5 px-5 py-5 transition first:rounded-t-[2rem] last:rounded-b-[2rem] hover:bg-slate-50/80 motion-reduce:transition-none sm:grid-cols-[220px_minmax(0,1fr)] sm:px-6"
              >
                <ArticleCover
                  :cover-path="article.cover_image_path"
                  :alt="article.title"
                  fallback-text="DigiPro by KJPP HJAR"
                  wrapper-class="aspect-[4/3] overflow-hidden rounded-[1.5rem] bg-slate-200/70"
                  image-class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                  fallback-class="flex h-full w-full items-center justify-center bg-slate-950 text-xs font-semibold uppercase text-white/75"
                />

                <div class="min-w-0 py-1">
                  <ArticleMeta
                    :published-at="article.published_at"
                    :read-source="article.excerpt"
                    :category="article.category"
                    container-class="flex flex-wrap items-center gap-3 text-sm text-slate-500"
                    category-class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase text-slate-700"
                  />

                  <h3 class="mt-3 text-balance text-2xl font-semibold leading-tight text-slate-950 transition group-hover:text-emerald-800 motion-reduce:transition-none">
                    {{ article.title }}
                  </h3>

                  <p class="mt-3 max-w-2xl text-pretty text-sm leading-7 text-slate-600 sm:text-base">
                    {{ article.excerpt || 'Baca artikel lengkap untuk melihat konteks, uraian, dan temuan utama dari topik ini.' }}
                  </p>

                  <div v-if="article.tags?.length" class="mt-4 flex flex-wrap gap-2">
                    <span
                      v-for="tag in article.tags.slice(0, 3)"
                      :key="tag"
                      class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-medium text-slate-600"
                    >
                      #{{ tag }}
                    </span>
                  </div>
                </div>
              </Link>
            </div>

            <div
              v-else-if="!featuredArticle"
              class="rounded-[2rem] border border-dashed border-black/10 bg-white/70 px-6 py-20 text-center"
            >
              <h3 class="text-balance text-3xl font-semibold text-slate-950">
                Belum ada
              </h3>
            </div>

            <div v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-2 pt-2">
              <Link
                v-for="(link, idx) in links"
                :key="idx"
                :href="link.url || '#'"
                class="inline-flex min-h-11 min-w-[44px] items-center justify-center rounded-full px-4 text-sm font-medium transition motion-reduce:transition-none"
                :class="
                  link.active
                    ? 'bg-slate-950 text-white'
                    : 'border border-black/8 bg-white/80 text-slate-700 hover:bg-white'
                "
                v-html="link.label"
              />
            </div>
          </section>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-28 lg:self-start">
          <section class="rounded-[2rem] border border-black/5 bg-white/80 p-5 shadow-[0_16px_48px_rgba(15,23,42,0.06)]">
            <p class="text-[11px] font-semibold uppercase text-slate-500">Pencarian</p>
            <form class="mt-4 space-y-4" @submit.prevent="submitSearch">
              <div class="rounded-3xl border border-slate-200 bg-slate-50/80 px-4 py-3 focus-within:ring-2 focus-within:ring-slate-900/20 focus-within:ring-offset-2 focus-within:ring-offset-white">
                <div class="flex items-center gap-2">
                  <Search class="h-4 w-4 text-slate-400" />
                  <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Cari topik atau istilah..."
                    class="w-full bg-transparent text-base text-slate-700 outline-none placeholder:text-slate-400 md:text-sm"
                  />
                </div>
              </div>

              <div class="grid grid-cols-3 gap-2">
                <label v-for="option in searchScopeOptions" :key="option.value" class="cursor-pointer">
                  <input v-model="searchScope" type="radio" class="sr-only" :value="option.value" />
                  <span
                    class="block rounded-full px-3 py-2 text-center text-[11px] font-semibold uppercase transition motion-reduce:transition-none"
                    :class="
                      searchScope === option.value
                        ? 'bg-slate-950 text-white'
                        : 'bg-slate-100 text-slate-600'
                    "
                  >
                    {{ option.label }}
                  </span>
                </label>
              </div>

              <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-full bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
              >
                Jalankan Pencarian
              </button>
            </form>
          </section>

          <section class="rounded-[2rem] border border-black/5 bg-white/80 p-5 shadow-[0_16px_48px_rgba(15,23,42,0.06)]">
            <div class="flex items-end justify-between gap-3">
              <div>
                <p class="text-[11px] font-semibold uppercase text-slate-500">Kategori</p>
                <h3 class="mt-2 text-balance text-2xl font-semibold text-slate-950">
                  Topik utama
                </h3>
              </div>
              <span class="text-xs text-slate-500">{{ categories.length }}</span>
            </div>

            <div class="mt-5 space-y-2">
              <Link
                :href="route('articles.index')"
                class="flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium transition motion-reduce:transition-none"
                :class="!activeCategory ? 'bg-slate-950 text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
              >
                <span>Semua artikel</span>
                <span class="text-xs opacity-70">All</span>
              </Link>

              <Link
                v-for="category in categories"
                :key="category.slug"
                :href="route('articles.index', { category: category.slug })"
                class="flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium transition motion-reduce:transition-none"
                :class="
                  category.slug === activeCategory
                    ? 'bg-slate-950 text-white'
                    : 'bg-slate-50 text-slate-700 hover:bg-slate-100'
                "
              >
                <span>{{ category.name }}</span>
                <span class="text-xs opacity-70">{{ category.articles_count ?? 0 }}</span>
              </Link>
            </div>
          </section>

          <section v-if="tags.length" class="rounded-[2rem] border border-black/5 bg-white/80 p-5 shadow-[0_16px_48px_rgba(15,23,42,0.06)]">
            <p class="text-[11px] font-semibold uppercase text-slate-500">Tag</p>
            <div class="mt-4 flex flex-wrap gap-2">
              <Link
                v-for="tag in tags"
                :key="tag.slug"
                :href="route('articles.index', { scope: 'tag', q: tag.name })"
                class="rounded-full border border-black/8 bg-slate-100 px-3 py-2 text-[11px] font-semibold uppercase text-slate-700 transition hover:bg-slate-200/60 motion-reduce:transition-none"
              >
                #{{ tag.name }}
              </Link>
            </div>
          </section>
        </aside>
      </div>
    </section>
  </BlogPageLayout>
</template>
