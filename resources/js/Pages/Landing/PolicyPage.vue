<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Card, CardContent } from '@/components/ui/card'
import LandingNavbar from '@/layouts/LandingNavbar.vue'
import LandingFooter from '@/layouts/LandingFooter.vue'

const page = usePage()
const policyDocument = computed(() => page.props.policyDocument || null)

const formatMonthYear = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(date)
}

const meta = computed(() => {
  const doc = policyDocument.value
  return {
    title: doc?.title || 'Kebijakan Privasi',
    company: doc?.company || 'DigiPro',
    version: doc?.version || '-',
    effectiveSince: formatMonthYear(doc?.effective_since),
  }
})

const contentHtml = computed(() => {
  return policyDocument.value?.content_html
    || '<p>Kebijakan privasi sedang diperbarui. Silakan hubungi admin untuk informasi terbaru.</p>'
})
</script>

<template>
  <div class="landing-shell text-slate-900 bg-[#f7f4ef] min-h-screen flex flex-col selection:bg-slate-900 selection:text-white">
    <LandingNavbar />

    <main class="flex-1 py-10 md:py-12 px-6">
      <div class="max-w-4xl mx-auto space-y-6">
        <section class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white/70 p-6 md:p-8 shadow-sm">
          <div class="absolute -top-16 right-0 h-40 w-40 rounded-full bg-emerald-200/40 blur-3xl"></div>
          <div class="absolute -bottom-14 left-0 h-36 w-36 rounded-full bg-amber-200/40 blur-3xl"></div>
          <div class="relative">
            <div class="text-xs uppercase tracking-wide text-slate-500">Legal</div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-slate-900 mt-1.5">{{ meta.title }}</h1>
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
</style>
