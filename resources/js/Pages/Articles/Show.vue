<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import BlogPageLayout from '@/layouts/BlogPageLayout.vue'
import ArticleMeta from '@/components/blog/ArticleMeta.vue'
import ArticleCover from '@/components/blog/ArticleCover.vue'

const page = usePage()
const article = computed(() => page.props.article)

const metaTitle = computed(() => article.value?.meta_title || article.value?.title || 'Artikel')
const metaDescription = computed(() => article.value?.meta_description || article.value?.excerpt || '')
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
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          <div class="article-content prose prose-lg max-w-none" v-html="article.content_html"></div>
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
  margin: 2rem 0;
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

/* Selection */
::selection {
  background-color: #0f172a;
  color: #ffffff;
}
</style>
