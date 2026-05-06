<script setup>
import { Link } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'

import ArticleCover from '@/components/blog/ArticleCover.vue'
import ArticleMeta from '@/components/blog/ArticleMeta.vue'

defineProps({
  articles: { type: Array, default: () => [] },
})
</script>

<template>
  <section class="border-t border-black/5 bg-[var(--landing-muted)] px-6 py-20 md:px-10 md:py-24">
    <div class="mx-auto max-w-7xl">
      <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-2xl">
          <p class="text-[11px] font-semibold uppercase text-slate-500">Artikel terbaru</p>
          <h2 class="mt-3 text-balance text-3xl font-semibold text-slate-950 md:text-4xl">
            Artikel terbaru dari DigiPro by KJPP HJAR
          </h2>
          <p class="mt-3 text-pretty text-base leading-7 text-slate-600 md:text-lg">
            Bacaan singkat seputar penilaian properti, pasar, dan hal-hal penting yang relevan untuk pengajuan Anda.
          </p>
        </div>

        <Link
          :href="route('articles.index')"
          class="inline-flex items-center gap-2 text-sm font-semibold text-slate-900 transition hover:text-slate-700 sm:self-start"
        >
          Lihat lebih lanjut
          <ArrowRight class="h-4 w-4" />
        </Link>
      </div>

      <div
        v-if="articles.length"
        class="article-scroll mt-10 grid snap-x snap-mandatory grid-flow-col gap-6 overflow-x-auto pb-3 auto-cols-[85%] sm:auto-cols-[48%] lg:auto-cols-[calc((100%-3rem)/3)] xl:auto-cols-[calc((100%-4.5rem)/4)]"
      >
        <Link
          v-for="article in articles"
          :key="article.slug"
          :href="route('articles.show', article.slug)"
          class="group snap-start overflow-hidden rounded-[1.75rem] border border-black/5 bg-white/90 shadow-[0_18px_48px_rgba(15,23,42,0.07)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_24px_60px_rgba(15,23,42,0.1)] motion-reduce:transition-none motion-reduce:hover:translate-y-0"
        >
          <ArticleCover
            :cover-path="article.cover_image_path"
            :alt="article.title"
            fallback-text="DigiPro by KJPP HJAR"
            wrapper-class="aspect-[4/3] overflow-hidden bg-slate-200/70"
            image-class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
            fallback-class="flex h-full w-full items-center justify-center bg-slate-950 text-xs font-semibold uppercase text-white/75"
          />

          <div class="flex min-h-64 flex-col p-5">
            <ArticleMeta
              :published-at="article.published_at"
              :read-source="article.excerpt"
              :category="article.category"
              container-class="flex flex-wrap items-center gap-2 text-xs text-slate-500"
              category-class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold uppercase text-slate-700"
            />

            <h3 class="mt-4 text-balance text-xl font-semibold leading-tight text-slate-950 transition group-hover:text-emerald-800">
              {{ article.title }}
            </h3>

            <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">
              {{ article.excerpt || 'Baca artikel lengkap untuk melihat konteks, pembahasan, dan insight utama dari topik ini.' }}
            </p>

            <div class="mt-auto pt-6 text-sm font-semibold text-slate-900">
              Baca artikel
            </div>
          </div>
        </Link>
      </div>

      <div
        v-else
        class="mt-10 rounded-[1.75rem] border border-dashed border-black/10 bg-white/70 px-6 py-16 text-center text-slate-600"
      >
        Artikel terbaru akan tampil di sini setelah dipublikasikan.
      </div>
    </div>
  </section>
</template>

<style scoped>
.article-scroll {
  scrollbar-width: none;
}

.article-scroll::-webkit-scrollbar {
  display: none;
}
</style>
