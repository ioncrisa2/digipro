<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ArrowLeft, ArrowRight } from 'lucide-vue-next'
import BlogPageLayout from '@/layouts/BlogPageLayout.vue'
import ArticleMeta from '@/components/blog/ArticleMeta.vue'
import ArticleCover from '@/components/blog/ArticleCover.vue'

const page = usePage()
const article = computed(() => page.props.article)
const relatedArticles = computed(() => page.props.relatedArticles ?? [])

const metaTitle = computed(() => article.value?.meta_title || article.value?.title || 'Artikel DigiPro by KJPP HJAR')
const metaDescription = computed(() => article.value?.meta_description || article.value?.excerpt || '')

const slugify = (value) =>
  String(value ?? '')
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
    <Head :title="metaTitle">
      <meta v-if="metaDescription" name="description" :content="metaDescription" />
    </Head>

    <section class="border-b border-black/5">
      <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <Link
          href="/artikel"
          class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 transition hover:text-slate-950"
        >
          <ArrowLeft class="h-4 w-4" />
          Kembali ke Artikel
        </Link>
      </div>
    </section>

    <article>
      <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1.05fr)_420px] lg:items-end">
          <div class="max-w-4xl">
            <ArticleMeta
              :published-at="article.published_at"
              :read-source="article.content_html || article.excerpt"
              :category="article.category"
              :views="article.views"
              :show-views="true"
              container-class="flex flex-wrap items-center gap-3 text-sm text-slate-500"
              category-class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-700"
            />

            <h1 class="mt-6 max-w-4xl font-['Space_Grotesk'] text-4xl font-semibold leading-[1.02] tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">
              {{ article.title }}
            </h1>

            <p v-if="article.excerpt" class="mt-6 max-w-3xl text-lg leading-8 text-slate-600 sm:text-xl">
              {{ article.excerpt }}
            </p>

            <div v-if="article.tags?.length" class="mt-8 flex flex-wrap gap-2">
              <span
                v-for="tag in article.tags"
                :key="tag"
                class="rounded-full bg-[#eef2ef] px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-700"
              >
                #{{ tag }}
              </span>
            </div>
          </div>

          <div v-if="article.cover_image_path" class="lg:justify-self-end">
            <ArticleCover
              :cover-path="article.cover_image_path"
              :alt="article.title"
              wrapper-class="aspect-[4/3] overflow-hidden rounded-[2rem] border border-black/5 bg-[#d7ddd3] shadow-[0_24px_70px_rgba(15,23,42,0.10)]"
              image-class="h-full w-full object-cover"
              fallback-class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#0f172a,#065f46)] text-sm font-semibold uppercase tracking-[0.24em] text-white/75"
            />
          </div>
        </div>
      </section>

      <section class="border-t border-black/5 bg-white/72">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
          <div class="grid gap-10 xl:grid-cols-[minmax(0,1fr)_300px]">
            <div class="min-w-0 xl:max-w-3xl">
              <div
                v-if="contentDocument.toc.length"
                class="mb-10 rounded-[1.75rem] border border-black/5 bg-[#f5f2ea] p-5 xl:hidden"
              >
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Daftar Isi</p>
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
                      <a :href="`#${item.id}`" class="text-sm leading-6 text-slate-700 transition hover:text-slate-950">
                        {{ item.text }}
                      </a>
                    </li>
                  </ul>
                </nav>
              </div>

              <div class="article-content" v-html="contentDocument.html" />
            </div>

            <aside class="space-y-5 xl:sticky xl:top-28 xl:self-start">
              <div v-if="contentDocument.toc.length" class="hidden rounded-[1.75rem] border border-black/5 bg-[#f5f2ea] p-5 xl:block">
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Daftar Isi</p>
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
                      <a :href="`#${item.id}`" class="block text-sm leading-6 text-slate-700 transition hover:text-slate-950">
                        {{ item.text }}
                      </a>
                    </li>
                  </ul>
                </nav>
              </div>

              <div class="rounded-[1.75rem] border border-black/5 bg-white/80 p-5 shadow-[0_16px_48px_rgba(15,23,42,0.06)]">
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Tentang Artikel Ini</p>
                <div class="mt-4 space-y-4 text-sm leading-7 text-slate-600">
                  <p>Ditulis untuk membantu pembacaan pasar, pemahaman alur penilaian, dan konteks kerja properti yang lebih presisi.</p>
                  <Link
                    href="/artikel"
                    class="inline-flex items-center gap-2 font-semibold text-slate-950 transition hover:text-emerald-800"
                  >
                    Lihat artikel lainnya
                    <ArrowRight class="h-4 w-4" />
                  </Link>
                </div>
              </div>
            </aside>
          </div>
        </div>
      </section>
    </article>

    <section v-if="relatedArticles.length" class="border-t border-black/5 bg-[#f0ebe1]">
      <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Artikel Terkait</p>
            <h2 class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold tracking-tight text-slate-950">
              Bacaan lanjutan
            </h2>
          </div>
          <Link href="/artikel" class="text-sm font-semibold text-slate-700 transition hover:text-slate-950">
            Kembali ke indeks artikel
          </Link>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
          <Link
            v-for="related in relatedArticles"
            :key="related.slug"
            :href="route('articles.show', related.slug)"
            class="group overflow-hidden rounded-[1.75rem] border border-black/5 bg-white/85 shadow-[0_16px_48px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_60px_rgba(15,23,42,0.10)]"
          >
            <ArticleCover
              :cover-path="related.cover_image_path"
              :alt="related.title"
              wrapper-class="aspect-[4/3] overflow-hidden bg-[#d8ddd5]"
              image-class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
              fallback-class="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,#0f172a,#1f2937)] text-xs font-semibold uppercase tracking-[0.24em] text-white/75"
            />

            <div class="p-6">
              <ArticleMeta
                :published-at="related.published_at"
                :read-source="related.excerpt"
                :category="related.category"
                container-class="flex flex-wrap items-center gap-3 text-sm text-slate-500"
                category-class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-700"
              />

              <h3 class="mt-4 font-['Space_Grotesk'] text-2xl font-semibold leading-tight tracking-tight text-slate-950 transition group-hover:text-emerald-800">
                {{ related.title }}
              </h3>

              <p v-if="related.excerpt" class="mt-3 line-clamp-3 text-sm leading-7 text-slate-600">
                {{ related.excerpt }}
              </p>
            </div>
          </Link>
        </div>
      </div>
    </section>
  </BlogPageLayout>
</template>

<style scoped>
.article-content {
  color: #334155;
}

.article-content :deep(h1),
.article-content :deep(h2),
.article-content :deep(h3),
.article-content :deep(h4) {
  font-family: 'Space Grotesk', sans-serif;
  color: #0f172a;
  letter-spacing: -0.03em;
}

.article-content :deep(h1) {
  margin-top: 3.5rem;
  margin-bottom: 1.5rem;
  font-size: 2.45rem;
  line-height: 1.04;
}

.article-content :deep(h2) {
  margin-top: 3.2rem;
  margin-bottom: 1.15rem;
  font-size: 2rem;
  line-height: 1.08;
}

.article-content :deep(h3) {
  margin-top: 2.4rem;
  margin-bottom: 0.9rem;
  font-size: 1.5rem;
  line-height: 1.15;
}

.article-content :deep(p),
.article-content :deep(ul),
.article-content :deep(ol),
.article-content :deep(blockquote) {
  margin-bottom: 1.5rem;
  font-size: 1.08rem;
  line-height: 1.95;
}

.article-content :deep(ul),
.article-content :deep(ol) {
  padding-left: 1.5rem;
}

.article-content :deep(li) {
  margin-bottom: 0.7rem;
}

.article-content :deep(strong) {
  color: #0f172a;
  font-weight: 700;
}

.article-content :deep(a) {
  color: #0f172a;
  text-decoration: underline;
  text-decoration-thickness: 1px;
  text-underline-offset: 3px;
}

.article-content :deep(blockquote) {
  border-left: 4px solid #10b981;
  padding-left: 1.25rem;
  color: #475569;
  font-style: italic;
}

.article-content :deep(code) {
  border-radius: 0.45rem;
  background: #eef2ef;
  padding: 0.15rem 0.45rem;
  font-size: 0.92em;
  color: #0f172a;
}

.article-content :deep(pre) {
  overflow-x: auto;
  margin: 2rem 0;
  border-radius: 1.25rem;
  background: #0f172a;
  padding: 1.4rem;
  color: #e2e8f0;
}

.article-content :deep(pre code) {
  background: transparent;
  padding: 0;
  color: inherit;
}

.article-content :deep(img) {
  margin: 2rem 0;
  width: 100%;
  border-radius: 1.5rem;
}

.article-content :deep(hr) {
  margin: 3rem 0;
  border: none;
  border-top: 1px solid rgba(15, 23, 42, 0.12);
}

.article-content :deep(table) {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
}

.article-content :deep(th) {
  background: #f5f2ea;
  padding: 0.85rem 1rem;
  text-align: left;
  font-weight: 600;
  color: #0f172a;
}

.article-content :deep(td) {
  padding: 0.85rem 1rem;
  border-top: 1px solid rgba(15, 23, 42, 0.08);
}

.article-content :deep(.article-table-wrap) {
  overflow-x: auto;
  margin: 2rem 0;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 1.4rem;
  background: white;
}
</style>
