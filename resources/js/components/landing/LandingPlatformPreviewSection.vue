<script setup>
defineProps({
  slides: { type: Array, required: true },
})
</script>

<template>
  <section id="showcase" class="scroll-mt-24 bg-[var(--landing-muted)] px-6 py-20 md:py-24">
    <div class="mx-auto max-w-7xl">
      <div class="grid gap-8 lg:grid-cols-[0.85fr_1.15fr] lg:items-end">
        <div class="space-y-4">
          <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--landing-gold-dark)]">
            Platform detail
          </p>
          <h2 class="text-balance text-3xl font-bold leading-tight text-[var(--landing-navy)] md:text-5xl">
            Tampilan produk nyata untuk pekerjaan appraisal sehari-hari.
          </h2>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.045)]">
          <div class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Catatan implementasi</div>
          <p class="mt-2 text-base leading-7 text-slate-700">
            Pratinjau menggunakan screenshot langsung dari DIGIPRO: dashboard, pengajuan, dan tracking status, sehingga materi visualnya berasal dari produk yang digunakan.
          </p>
        </div>
      </div>

      <div class="mt-12 space-y-8">
        <article
          v-if="slides[0]"
          class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_rgba(15,23,42,0.07)]"
        >
          <div class="grid gap-0 lg:grid-cols-[0.48fr_0.52fr] lg:items-stretch">
            <div class="flex flex-col justify-between gap-8 p-6 md:p-8">
              <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--landing-gold-dark)]">
                  {{ slides[0].eyebrow }}
                </p>
                <h3 class="mt-4 text-2xl font-bold leading-tight text-[var(--landing-navy)] md:text-3xl">
                  {{ slides[0].title }}
                </h3>
                <p class="mt-4 text-base leading-8 text-slate-600">
                  {{ slides[0].description }}
                </p>
              </div>

              <div class="grid grid-cols-2 gap-4 border-t border-slate-200 pt-5">
                <div>
                  <div class="text-3xl font-bold text-[var(--landing-navy)]">{{ slides[0].metric }}</div>
                  <div class="mt-1 text-sm leading-6 text-slate-500">{{ slides[0].metricLabel }}</div>
                </div>
                <div>
                  <div class="text-3xl font-bold text-[var(--landing-navy)]">Portal</div>
                  <div class="mt-1 text-sm leading-6 text-slate-500">untuk pemberi tugas dan tim internal</div>
                </div>
              </div>
            </div>

            <div class="border-t border-slate-200 bg-slate-100 p-3 lg:border-l lg:border-t-0">
              <img
                :src="slides[0].image"
                :alt="slides[0].imageAlt"
                class="h-full min-h-[360px] w-full rounded-xl object-cover object-left-top"
              />
            </div>
          </div>
        </article>

        <div class="grid gap-8 lg:grid-cols-2">
          <article
            v-for="slide in slides.slice(1, 3)"
            :key="slide.title"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_14px_34px_rgba(15,23,42,0.06)]"
          >
            <div class="border-b border-slate-200 bg-slate-100 p-3">
              <img
                :src="slide.image"
                :alt="slide.imageAlt"
                class="aspect-[16/10] w-full rounded-xl object-cover object-left-top"
              />
            </div>
            <div class="p-6">
              <div class="flex items-center justify-between gap-4">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--landing-gold-dark)]">
                  {{ slide.eyebrow }}
                </p>
                <div class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                  {{ slide.metric }}
                </div>
              </div>
              <h3 class="mt-4 text-2xl font-bold leading-tight text-[var(--landing-navy)]">
                {{ slide.title }}
              </h3>
              <p class="mt-3 text-sm leading-7 text-slate-600 md:text-base">
                {{ slide.description }}
              </p>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>
</template>
