<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import LandingNavbar from '@/layouts/LandingNavbar.vue'
import LandingFooter from '@/layouts/LandingFooter.vue'
import { Card, CardContent } from '@/components/ui/card'

const page = usePage()
const termsDocument = computed(() => page.props.termsDocument || null)

const formatMonthYear = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(date)
}

const meta = computed(() => {
  const doc = termsDocument.value
  return {
    title: doc?.title || 'Ketentuan Layanan',
    company: doc?.company || 'DigiPro by KJPP HJAR',
    version: doc?.version || '-',
    effectiveSince: formatMonthYear(doc?.effective_since),
  }
})

const contentHtml = computed(() => {
  return termsDocument.value?.content_html
    || '<p>Ketentuan layanan sedang diperbarui. Silakan hubungi admin untuk informasi terbaru.</p>'
})
</script>

<template>
  <div class="landing-shell text-slate-900 min-h-dvh flex flex-col selection:bg-slate-900 selection:text-white">
    <LandingNavbar />

    <main id="content" class="flex-1 py-10 md:py-12 px-6">
      <div class="max-w-4xl mx-auto space-y-6">
        <section class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white/70 p-6 md:p-8 shadow-sm">
          <div class="absolute -top-16 right-0 h-40 w-40 rounded-full bg-amber-200/40 blur-3xl"></div>
          <div class="absolute -bottom-14 left-0 h-36 w-36 rounded-full bg-sky-200/40 blur-3xl"></div>
          <div class="relative">
            <div class="text-xs uppercase text-slate-500">Legal</div>
            <h1 class="mt-1.5 text-balance text-2xl font-semibold text-slate-900 md:text-3xl">{{ meta.title }}</h1>
            <div class="mt-2 text-xs md:text-sm text-slate-600">
              <span class="font-medium text-slate-700">{{ meta.company }}</span>
              <span class="mx-2 text-slate-400">|</span>
              <span>Versi {{ meta.version }}</span>
              <span class="mx-2 text-slate-400">|</span>
              <span>Berlaku sejak {{ meta.effectiveSince }}</span>
            </div>
          </div>
        </section>

        <Card class="border-slate-200 bg-white/80 shadow-sm">
          <CardContent class="p-6 md:p-8">
            <div class="terms-content text-sm text-slate-600" v-html="contentHtml"></div>
          </CardContent>
        </Card>
      </div>
    </main>

    <LandingFooter />
  </div>
</template>

<style scoped>
.terms-content :deep(h3) {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
  margin-top: 14px;
}

.terms-content :deep(h1) {
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  margin-top: 20px;
}

.terms-content :deep(h2) {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin-top: 18px;
}

.terms-content :deep(p) {
  line-height: 1.65;
  margin-top: 6px;
}

.terms-content :deep(ul) {
  margin-top: 6px;
  padding-left: 16px;
  display: grid;
  gap: 4px;
}

.terms-content :deep(li) {
  list-style: disc;
}

.terms-content :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin-top: 12px;
}

.terms-content :deep(th),
.terms-content :deep(td) {
  border: 1px solid #e2e8f0;
  padding: 10px 12px;
  text-align: left;
}

.terms-content :deep(th) {
  background: #f8fafc;
  font-weight: 700;
}
</style>
