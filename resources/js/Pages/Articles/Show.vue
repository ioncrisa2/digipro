<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import BlogPageLayout from '@/layouts/BlogPageLayout.vue'
import ArticleMeta from '@/components/blog/ArticleMeta.vue'
import ArticleCover from '@/components/blog/ArticleCover.vue'

const page = usePage()
const article = computed(() => page.props.article)
const relatedArticles = computed(() => page.props.relatedArticles ?? [])

const metaTitle = computed(() => article.value?.meta_title || article.value?.title || 'Artikel')
const metaDescription = computed(() => article.value?.meta_description || article.value?.excerpt || '')

const slugify = (value) => String(value ?? '')
  .toLowerCase()
  .trim()
  .replace(/<[^>]+>/g, '')
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '')

const contentDocument = computed(() => {
  const raw = article.value?.content_html || ''

  if (typeof window === 'undefined' || raw.trim() === '') {
    return {
      html: raw,
      toc: [],
    }
  }

  const parser = new DOMParser()
  const document = parser.parseFromString(raw, 'text/html')
  const seenIds = new Map()

  const toc = Array.from(document.body.querySelectorAll('h1, h2, h3'))
    .map((heading, index) => {
      const text = heading.textContent?.trim() || `Section ${index + 1}`
      const level = heading.tagName.toLowerCase()
      const baseId = slugify(text) || `section-${index + 1}`
      const usedCount = seenIds.get(baseId) ?? 0
      seenIds.set(baseId, usedCount + 1)
      const id = usedCount === 0 ? baseId : `${baseId}-${usedCount + 1}`

      heading.id = id

      return {
        id,
        text,
        level,
      }
    })

  Array.from(document.body.querySelectorAll('table')).forEach((table) => {
    const wrapper = document.createElement('div')
    wrapper.className = 'article-table-wrap'
    table.parentNode?.insertBefore(wrapper, table)
    wrapper.appendChild(table)
  })

  return {
    html: document.body.innerHTML,
    toc,
  }
})
</script>

<template>
  <BlogPageLayout :title="metaTitle" :description="metaDescription">
      <!-- Article Header -->
      <div class="bg-gradient-to-b from-slate-50 to-white border-b border-slate-100">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8">
          <!-- Back Button -->
          <Link
            href="/artikel"
            class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors mb-8 group"
          >
            <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Kembali ke Blog
          </Link>

          <!-- Category & Meta -->
          <ArticleMeta
            :published-at="article.published_at"
            :read-source="article.content_html || article.excerpt"
            read-suffix="read"
            :category="article.category"
            :views="article.views"
            :show-views="true"
            category-class="px-3 py-1.5 rounded-full bg-slate-900 text-white text-xs font-semibold tracking-wide"
            container-class="flex flex-wrap items-center gap-3 text-sm mb-6 text-slate-500"
          />

          <!-- Title -->
          <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold tracking-tight text-slate-900 mb-6 leading-tight">
            {{ article.title }}
          </h1>

          <!-- Excerpt -->
          <p v-if="article.excerpt" class="text-lg md:text-xl text-slate-600 leading-relaxed">
            {{ article.excerpt }}
          </p>

          <!-- Tags -->
          <div v-if="article.tags?.length" class="flex flex-wrap gap-2 mt-6">
            <span
              v-for="tag in article.tags"
              :key="tag"
              class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium hover:bg-slate-200 transition-colors"
            >
              #{{ tag }}
            </span>
          </div>
        </div>
      </div>

      <!-- Featured Image -->
      <div v-if="article.cover_image_path" class="border-b border-slate-100">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <ArticleCover
            :cover-path="article.cover_image_path"
            :alt="article.title"
            wrapper-class="aspect-video bg-slate-100 overflow-hidden rounded-2xl shadow-lg"
            image-class="h-full w-full object-cover"
          />
        </div>
      </div>

      <!-- Article Content -->
      <div class="bg-white">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
          <div class="grid gap-10 xl:grid-cols-[minmax(0,1fr)_280px]">
            <div class="min-w-0 xl:max-w-3xl">
              <div v-if="contentDocument.toc.length" class="mb-10 rounded-2xl border border-slate-200 bg-slate-50/80 p-5 xl:hidden">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Daftar Isi</p>
                <nav class="mt-4">
                  <ul class="space-y-2">
                    <li
                      v-for="item in contentDocument.toc"
                      :key="item.id"
                      :class="[
                        item.level === 'h2' ? 'pl-4' : '',
                        item.level === 'h3' ? 'pl-8' : '',
                      ]"
                    >
                      <a
                        :href="`#${item.id}`"
                        class="text-sm text-slate-700 transition-colors hover:text-slate-950 hover:underline"
                      >
                        {{ item.text }}
                      </a>
                    </li>
                  </ul>
                </nav>
              </div>

              <div class="article-content prose prose-lg max-w-none" v-html="contentDocument.html"></div>
            </div>

            <aside v-if="contentDocument.toc.length" class="hidden xl:block">
              <div class="sticky top-24 rounded-2xl border border-slate-200 bg-slate-50/80 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Daftar Isi</p>
                <nav class="mt-4">
                  <ul class="space-y-2.5">
                    <li
                      v-for="item in contentDocument.toc"
                      :key="item.id"
                      :class="[
                        item.level === 'h2' ? 'pl-4' : '',
                        item.level === 'h3' ? 'pl-8' : '',
                      ]"
                    >
                      <a
                        :href="`#${item.id}`"
                        class="block text-sm leading-6 text-slate-700 transition-colors hover:text-slate-950 hover:underline"
                      >
                        {{ item.text }}
                      </a>
                    </li>
                  </ul>
                </nav>
              </div>
            </aside>
          </div>
        </div>
      </div>

      <div v-if="relatedArticles.length" class="border-t border-slate-100 bg-slate-50/70">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Artikel Terkait</p>
              <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-950">Lanjutkan membaca topik serupa</h2>
            </div>
            <Link
              href="/artikel"
              class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 transition-colors hover:text-slate-950"
            >
              Lihat semua artikel
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
              </svg>
            </Link>
          </div>

          <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <Link
              v-for="related in relatedArticles"
              :key="related.slug"
              :href="route('articles.show', related.slug)"
              class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg"
            >
              <div v-if="related.cover_image_path" class="aspect-[16/10] overflow-hidden bg-slate-100">
                <ArticleCover
                  :cover-path="related.cover_image_path"
                  :alt="related.title"
                  wrapper-class="h-full w-full"
                  image-class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                />
              </div>
              <div class="p-6">
                <ArticleMeta
                  :published-at="related.published_at"
                  :read-source="related.excerpt"
                  :category="related.category"
                  container-class="flex flex-wrap items-center gap-3 text-sm text-slate-500"
                  category-class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                />
                <h3 class="mt-4 text-xl font-semibold leading-tight text-slate-950 transition-colors group-hover:text-slate-700">
                  {{ related.title }}
                </h3>
                <p v-if="related.excerpt" class="mt-3 line-clamp-3 text-sm leading-7 text-slate-600">
                  {{ related.excerpt }}
                </p>
                <div v-if="related.tags?.length" class="mt-4 flex flex-wrap gap-2">
                  <span
                    v-for="tag in related.tags.slice(0, 3)"
                    :key="tag"
                    class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600"
                  >
                    #{{ tag }}
                  </span>
                </div>
              </div>
            </Link>
          </div>
        </div>
      </div>

      <!-- Bottom CTA -->
      <div class="border-t border-slate-100 bg-slate-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center shadow-sm">
            <h3 class="text-2xl font-bold text-slate-900 mb-3">
              Mulai dengan DigiPro
            </h3>
            <p class="text-slate-600 mb-6">
              Platform penilaian properti terpercaya untuk profesional appraisal.
            </p>
            <Link
              href="/"
              class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full bg-slate-900 text-white font-semibold hover:bg-slate-800 transition-all shadow-sm"
            >
              Coba DigiPro Sekarang
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
              </svg>
            </Link>
          </div>
        </div>
      </div>
  </BlogPageLayout>
</template>

<style scoped>
/* Prose Styling for Article Content */
.article-content {
  color: #334155;
  line-height: 1.8;
}

.article-content :deep(h2) {
  font-size: 1.875rem;
  font-weight: 700;
  color: #0f172a;
  margin-top: 3rem;
  margin-bottom: 1.25rem;
  line-height: 1.2;
  letter-spacing: -0.02em;
}

.article-content :deep(h1) {
  font-size: 2.25rem;
  font-weight: 800;
  color: #0f172a;
  margin-top: 3rem;
  margin-bottom: 1.5rem;
  line-height: 1.15;
  letter-spacing: -0.03em;
}

.article-content :deep(h3) {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin-top: 2.5rem;
  margin-bottom: 1rem;
  line-height: 1.3;
  letter-spacing: -0.01em;
}

.article-content :deep(h4) {
  font-size: 1.25rem;
  font-weight: 600;
  color: #0f172a;
  margin-top: 2rem;
  margin-bottom: 0.75rem;
}

.article-content :deep(p) {
  margin-bottom: 1.5rem;
  font-size: 1.125rem;
}

.article-content :deep(ul),
.article-content :deep(ol) {
  margin-bottom: 1.5rem;
  padding-left: 1.75rem;
}

.article-content :deep(li) {
  margin-bottom: 0.75rem;
  line-height: 1.8;
}

.article-content :deep(ul li) {
  list-style-type: disc;
}

.article-content :deep(ol li) {
  list-style-type: decimal;
}

.article-content :deep(a) {
  color: #0f172a;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 2px;
  transition: color 0.2s;
}

.article-content :deep(a:hover) {
  color: #475569;
}

.article-content :deep(blockquote) {
  border-left: 4px solid #0f172a;
  padding-left: 1.5rem;
  margin: 2rem 0;
  font-style: italic;
  color: #475569;
  font-size: 1.25rem;
}

.article-content :deep(code) {
  background: #f1f5f9;
  padding: 0.25rem 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.9em;
  font-family: 'Courier New', monospace;
  color: #0f172a;
}

.article-content :deep(pre) {
  background: #0f172a;
  color: #f1f5f9;
  padding: 1.5rem;
  border-radius: 0.75rem;
  overflow-x: auto;
  margin: 2rem 0;
}

.article-content :deep(pre code) {
  background: transparent;
  padding: 0;
  color: inherit;
}

.article-content :deep(img) {
  border-radius: 0.75rem;
  margin: 2rem 0;
  width: 100%;
  height: auto;
}

.article-content :deep(hr) {
  border: none;
  border-top: 1px solid #e2e8f0;
  margin: 3rem 0;
}

.article-content :deep(strong) {
  font-weight: 600;
  color: #0f172a;
}

.article-content :deep(em) {
  font-style: italic;
}

.article-content :deep(table) {
  width: 100%;
  border-collapse: collapse;
  min-width: 640px;
}

.article-content :deep(th) {
  background: #f8fafc;
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
  border: 1px solid #e2e8f0;
}

.article-content :deep(td) {
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
}

.article-content :deep(.article-table-wrap) {
  overflow-x: auto;
  margin: 2rem 0;
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
}

/* Selection */
::selection {
  background-color: #0f172a;
  color: #ffffff;
}
</style>
