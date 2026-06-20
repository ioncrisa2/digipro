<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'

import ArticleCover from '@/components/blog/ArticleCover.vue'
import ArticleMeta from '@/components/blog/ArticleMeta.vue'

const props = defineProps({
  articles: { type: Array, default: () => [] },
})

const visibleArticles = computed(() => {
  return props.articles
    .filter((article) => {
      const searchable = [
        article.title,
        article.excerpt,
        article.category,
      ].filter(Boolean).join(' ').toLowerCase()

      return !/(^|\s)(ai|artificial intelligence)(\s|$)|big data|futuristik|futuristic/.test(searchable)
    })
    .slice(0, 3)
})
</script>

<template>
  <section class="border-t border-slate-200 bg-[var(--landing-muted)] px-6 py-20 md:px-10 md:py-24">
    <div class="mx-auto max-w-7xl">
      <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-2xl">
          <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--landing-gold-dark)]">Artikel terbaru</p>
          <h2 class="mt-3 text-balance text-3xl font-bold leading-tight text-[var(--landing-navy)] md:text-5xl">
            Catatan editorial seputar appraisal dan properti.
          </h2>
          <p class="mt-3 text-pretty text-base leading-7 text-slate-600 md:text-lg">
            Pilihan bacaan singkat untuk memahami dokumen, konteks pasar, dan proses penilaian properti.
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
        v-if="visibleArticles.length"
        class="mt-10 grid gap-6"
        :class="visibleArticles.length === 1 ? 'lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]' : 'md:grid-cols-3'"
      >
        <Link
          v-for="article in visibleArticles"
          :key="article.slug"
          :href="route('articles.show', article.slug)"
          class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_10px_28px_rgba(15,23,42,0.045)] transition-colors duration-150 hover:border-[var(--landing-gold)]/55 motion-reduce:transition-none"
        >
          <ArticleCover
            :cover-path="article.cover_image_path"
            :alt="article.title"
            fallback-text="DigiPro by KJPP HJAR"
            wrapper-class="aspect-[4/3] overflow-hidden bg-slate-200/70"
            image-class="h-full w-full object-cover"
            fallback-class="flex h-full w-full items-center justify-center bg-[var(--landing-navy)] text-xs font-semibold uppercase text-white/75"
          />

          <div class="flex min-h-64 flex-col p-5">
            <ArticleMeta
              :published-at="article.published_at"
              :read-source="article.excerpt"
              :category="article.category"
              container-class="flex flex-wrap items-center gap-2 text-xs text-slate-500"
              category-class="rounded-lg bg-[var(--landing-gold-soft)] px-2.5 py-1 text-[10px] font-semibold uppercase text-[var(--landing-gold-dark)]"
            />

            <h3 class="mt-4 text-balance text-xl font-bold leading-tight text-[var(--landing-navy)]">
              {{ article.title }}
            </h3>

            <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">
              {{ article.excerpt || 'Baca artikel lengkap untuk melihat konteks, pembahasan, dan insight utama dari topik ini.' }}
            </p>

            <div class="mt-auto pt-6 text-sm font-semibold text-[var(--landing-navy)]">
              Baca artikel
            </div>
          </div>
        </Link>

        <aside
          v-if="visibleArticles.length === 1"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_10px_28px_rgba(15,23,42,0.045)]"
        >
          <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--landing-gold-dark)]">
            Fokus editorial
          </p>
          <h3 class="mt-4 max-w-xl text-2xl font-bold leading-tight text-[var(--landing-navy)]">
            Materi bacaan dipilih agar selaras dengan konteks institusi dan penilaian properti.
          </h3>
          <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div class="text-sm font-semibold text-[var(--landing-navy)]">Regulasi</div>
              <div class="mt-2 text-sm leading-6 text-slate-600">Perubahan ketentuan yang memengaruhi proses appraisal.</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div class="text-sm font-semibold text-[var(--landing-navy)]">Dokumen</div>
              <div class="mt-2 text-sm leading-6 text-slate-600">Kelengkapan data sebelum permohonan masuk review.</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div class="text-sm font-semibold text-[var(--landing-navy)]">Proses</div>
              <div class="mt-2 text-sm leading-6 text-slate-600">Tahap kerja yang perlu dipahami pemberi tugas.</div>
            </div>
          </div>
        </aside>
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
