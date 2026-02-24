<script setup>
import { router } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'

import LandingNavbar from '@/layouts/LandingNavbar.vue'
import LandingFooter from '@/layouts/LandingFooter.vue'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion'
import {
  CheckCircle2,
  Zap,
  ShieldCheck,
  Smartphone,
  TrendingUp,
  ArrowRight,
  ArrowUp,
  ArrowLeft,
  ArrowRightCircle,
} from 'lucide-vue-next'

const scrollToTop = () => {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const props = defineProps({
  features: Array,
  faqs: Array,
  testimonials: Array,
})

const iconMap = {
  TrendingUp,
  Zap,
  ShieldCheck,
  Smartphone,
}

const slides = [
  {
    title: 'Request & Offer dalam hitungan jam',
    description: 'Buat permintaan, unggah dokumen, dan dapatkan penawaran tanpa proses manual berulang.',
    points: ['Form digital terstruktur', 'Kelengkapan otomatis', 'Estimasi cepat'],
    image: '/images/preview-request.svg',
    imageAlt: 'Ilustrasi permintaan dan penawaran',
  },
  {
    title: 'Desk appraisal dengan hasil kilat',
    description: 'Penilaian tanpa inspeksi lapangan mempercepat proses dari dokumen masuk hingga opini nilai.',
    points: ['Tanpa jadwal survei lapangan', 'Validasi dokumen cepat', 'Turnaround laporan lebih singkat'],
    image: '/images/preview-desk-appraisal.svg',
    imageAlt: 'Ilustrasi desk appraisal dengan proses cepat',
  },
  {
    title: 'Laporan legal-ready, mudah diunduh',
    description: 'Laporan penilaian, invoice, dan dokumen pendukung siap diakses kapan saja.',
    points: ['Format resmi KJPP', 'Audit trail', 'Download aman'],
    image: '/images/preview-report.svg',
    imageAlt: 'Ilustrasi laporan penilaian',
  },
]

const activeSlide = ref(0)
let slideTimer = null

const currentSlide = computed(() => slides[activeSlide.value])

const testimonialIndex = ref(0)
let testimonialTimer = null
const currentTestimonial = computed(() => {
  if (!props.testimonials?.length) return null
  return props.testimonials[testimonialIndex.value % props.testimonials.length]
})

const goSlide = (index) => {
  activeSlide.value = index
}

const nextSlide = () => {
  activeSlide.value = (activeSlide.value + 1) % slides.length
}

const prevSlide = () => {
  activeSlide.value = (activeSlide.value - 1 + slides.length) % slides.length
}

const nextTestimonial = () => {
  if (!props.testimonials?.length) return
  testimonialIndex.value = (testimonialIndex.value + 1) % props.testimonials.length
}

const goTestimonial = (index) => {
  testimonialIndex.value = index
}

onMounted(() => {
  slideTimer = setInterval(() => {
    nextSlide()
  }, 5000)

  testimonialTimer = setInterval(() => {
    nextTestimonial()
  }, 6000)
})

onBeforeUnmount(() => {
  if (slideTimer) clearInterval(slideTimer)
  if (testimonialTimer) clearInterval(testimonialTimer)
})
</script>

<template>
  <div class="landing-shell text-slate-900 bg-[#f7f4ef] selection:bg-slate-900 selection:text-white">
    <LandingNavbar />

    <!-- HERO -->
    <section class="relative pt-16 pb-20 md:pt-20 md:pb-24 px-6 overflow-hidden">
      <div class="absolute inset-0 -z-10">
        <div class="absolute -top-32 right-0 h-[460px] w-[460px] rounded-full bg-amber-200/30 blur-3xl"></div>
        <div class="absolute -bottom-24 left-0 h-[420px] w-[420px] rounded-full bg-sky-200/40 blur-3xl"></div>
      </div>

      <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-[1.1fr_0.9fr] gap-12 items-center">
        <div class="landing-fade-up space-y-6">
          <div class="inline-flex items-center rounded-full border border-slate-200 bg-white/70 px-3 py-1 text-sm font-medium text-slate-600 shadow-sm">
            <span class="flex h-2 w-2 rounded-full bg-emerald-500 mr-2"></span>
            Platform penilaian properti untuk KJPP & institusi finansial
          </div>

          <h1 class="text-4xl md:text-6xl font-semibold tracking-tight leading-[1.05]">
            DigiPro membantu Anda <span class="text-slate-500">mengelola penilaian</span> lebih cepat dan akurat.
          </h1>

          <p class="text-lg md:text-xl text-slate-600 max-w-xl">
            Workflow appraisal end-to-end, dari permintaan hingga laporan legal-ready, dengan kontrol penuh dan jejak audit.
          </p>

          <div class="flex flex-col sm:flex-row gap-4">
            <Button size="lg" class="bg-slate-900 hover:bg-slate-800 h-12 px-8 text-base" @click="router.visit('/login')">
              Mulai Penilaian
              <ArrowRight class="ml-2 w-4 h-4" />
            </Button>
            <Button variant="outline" size="lg" class="h-12 px-8 text-base" @click="router.visit('/contact')">
              More Information
            </Button>
          </div>

          <div class="flex flex-wrap items-center gap-6 text-sm text-slate-600">
            <div class="flex items-center gap-2">
              <CheckCircle2 class="w-4 h-4 text-emerald-600" />
              Legal-ready report
            </div>
            <div class="flex items-center gap-2">
              <CheckCircle2 class="w-4 h-4 text-emerald-600" />
              Monitoring progres
            </div>
            <div class="flex items-center gap-2">
              <CheckCircle2 class="w-4 h-4 text-emerald-600" />
              Dokumen terpusat
            </div>
          </div>
        </div>

        <div class="relative">
          <img
            src="/images/valuation-hero.svg"
            alt="DigiPro Illustration"
            class="w-full max-w-[520px] mx-auto drop-shadow-xl"
          />
          <div class="absolute -bottom-6 left-1/2 -translate-x-1/2 w-[82%] bg-white/90 border border-slate-200 rounded-2xl shadow-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-xs text-slate-500">Laporan siap diunduh</div>
                <div class="text-lg font-semibold text-slate-900">REQ-2026-0045</div>
              </div>
              <div class="h-10 w-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                <CheckCircle2 class="h-5 w-5" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SHOWCASE / SLIDESHOW -->
    <section id="showcase" class="scroll-mt-24 py-24 px-6">
      <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-[0.9fr_1.1fr] gap-12 items-center">
        <div class="space-y-6">
          <h2 class="text-3xl md:text-4xl font-semibold tracking-tight">Platform preview</h2>
          <p class="text-slate-600 text-lg">
            Lihat bagaimana DigiPro menyederhanakan setiap tahap appraisal, dari permintaan hingga laporan.
          </p>

          <div class="flex items-center gap-3">
            <Button variant="outline" size="icon" @click="prevSlide">
              <ArrowLeft class="h-4 w-4" />
            </Button>
            <Button variant="outline" size="icon" @click="nextSlide">
              <ArrowRightCircle class="h-4 w-4" />
            </Button>
            <div class="flex items-center gap-2">
              <button
                v-for="(slide, idx) in slides"
                :key="slide.title"
                class="h-2.5 w-2.5 rounded-full"
                :class="idx === activeSlide ? 'bg-slate-900' : 'bg-slate-300'"
                @click="goSlide(idx)"
              ></button>
            </div>
          </div>
        </div>

        <div class="relative">
          <div class="rounded-3xl border border-slate-200 bg-white/80 shadow-xl p-8">
            <transition name="slide-fade" mode="out-in">
              <div :key="currentSlide.title" class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white/70 p-3">
                  <img
                    :src="currentSlide.image"
                    :alt="currentSlide.imageAlt"
                    class="w-full h-48 object-cover rounded-xl"
                    loading="lazy"
                  />
                </div>
                <div>
                  <div class="text-xs uppercase tracking-wide text-slate-500">Highlight</div>
                  <h3 class="text-2xl font-semibold text-slate-900 mt-2">{{ currentSlide.title }}</h3>
                  <p class="text-slate-600 mt-2">{{ currentSlide.description }}</p>
                </div>
                <ul class="space-y-2 text-sm text-slate-600">
                  <li v-for="point in currentSlide.points" :key="point" class="flex items-center gap-2">
                    <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                    {{ point }}
                  </li>
                </ul>
              </div>
            </transition>
          </div>
        </div>
      </div>
    </section>

    <!-- FEATURES -->
    <section id="features" class="scroll-mt-24 py-24 px-6 bg-white/70 border-y border-slate-200/60">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-14">
          <h2 class="text-3xl font-semibold tracking-tight">Kenapa DigiPro?</h2>
          <p class="text-slate-500 mt-3 max-w-2xl mx-auto">
            Menggabungkan pengalaman appraisal dengan workflow digital yang cepat, aman, dan terstruktur.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card v-for="(feature, i) in features" :key="i" class="border-slate-200 shadow-sm hover:shadow-md transition-all">
            <CardHeader>
              <div class="w-12 h-12 bg-slate-900/10 rounded-lg flex items-center justify-center mb-4">
                <component :is="iconMap[feature.icon]" v-if="iconMap[feature.icon]" class="w-6 h-6 text-slate-900" />
              </div>
              <CardTitle class="text-lg">{{ feature.title }}</CardTitle>
            </CardHeader>
            <CardContent>
              <CardDescription class="text-base text-slate-600 leading-relaxed">
                {{ feature.description }}
              </CardDescription>
            </CardContent>
          </Card>
        </div>
      </div>
    </section>

    <!-- PROCESS -->
    <section id="process" class="scroll-mt-24 py-24 px-6">
      <div class="max-w-7xl mx-auto">
        <h2 class="text-3xl font-semibold tracking-tight text-center mb-14">Alur Kerja Ringkas</h2>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div v-for="(step, i) in ['Ajukan Permohonan', 'Verifikasi Dokumen', 'Desk Appraisal', 'Laporan Siap']" :key="i" class="rounded-2xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <div class="text-xs text-slate-500">Step {{ i + 1 }}</div>
            <div class="text-lg font-semibold text-slate-900 mt-3">{{ step }}</div>
            <p class="text-sm text-slate-600 mt-2">
              {{
                i === 0
                  ? 'Isi form dan unggah dokumen secara digital.'
                  : i === 1
                    ? 'Sistem memverifikasi kelengkapan dokumen secara cepat.'
                    : i === 2
                      ? 'Tim valuasi menyusun opini nilai tanpa inspeksi lapangan.'
                      : 'Laporan resmi tersedia untuk diunduh.'
              }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- TESTIMONIALS -->
    <section id="testimonials" class="scroll-mt-24 py-24 px-6 bg-slate-900 text-white">
      <div class="max-w-6xl mx-auto text-center">
        <h2 class="text-3xl font-semibold tracking-tight mb-4">Dipercaya Profesional</h2>
        <p class="text-slate-300 mb-12">Ulasan singkat dari pengguna dan partner kami.</p>

        <div class="relative max-w-3xl mx-auto">
          <Card class="bg-slate-800 border-slate-700 text-slate-100 shadow-none">
            <CardContent class="pt-8 pb-10 px-8">
              <transition name="fade-slide" mode="out-in">
                <div v-if="currentTestimonial" :key="currentTestimonial.name + currentTestimonial.quote" class="space-y-6">
                  <div class="flex justify-center text-slate-400">
                    <CheckCircle2 class="w-10 h-10" />
                  </div>
                  <p class="text-lg leading-relaxed">"{{ currentTestimonial.quote }}"</p>
                  <div>
                    <div class="font-semibold text-lg">{{ currentTestimonial.name }}</div>
                    <div class="text-sm text-slate-400">{{ currentTestimonial.role }}</div>
                  </div>
                </div>
              </transition>
            </CardContent>
          </Card>

          <div class="flex items-center justify-center gap-2 mt-6">
            <button
              v-for="(t, idx) in testimonials"
              :key="t.name + idx"
              class="h-2.5 w-2.5 rounded-full"
              :class="idx === testimonialIndex ? 'bg-white' : 'bg-white/40'"
              @click="goTestimonial(idx)"
            ></button>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="scroll-mt-24 py-24 px-6 bg-white/70">
      <div class="max-w-3xl mx-auto">
        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 text-center mb-12">Pertanyaan Umum</h2>

        <Accordion type="single" collapsible class="w-full bg-white rounded-2xl border border-slate-200 px-6 py-2 shadow-sm">
          <AccordionItem v-for="faq in faqs" :key="faq.value" :value="faq.value">
            <AccordionTrigger class="text-left text-base font-semibold text-slate-800 hover:no-underline">
              {{ faq.question }}
            </AccordionTrigger>
            <AccordionContent class="text-slate-600 leading-relaxed text-base pb-4">
              {{ faq.answer }}
            </AccordionContent>
          </AccordionItem>
        </Accordion>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-24 px-6 text-center">
      <div class="max-w-2xl mx-auto">
        <h2 class="text-3xl md:text-4xl font-semibold tracking-tight text-slate-900 mb-6">Siap beralih ke DigiPro?</h2>
        <p class="text-lg text-slate-600 mb-10">
          Tim kami siap membantu penilaian properti Anda dengan proses modern dan transparan.
        </p>
        <Button size="lg" class="bg-slate-900 hover:bg-slate-800 h-14 px-8 text-lg" @click="router.visit('/login')">
          Mulai Sekarang
        </Button>
      </div>
    </section>

    <Button @click="scrollToTop" variant="outline" size="icon" class="fixed bottom-8 right-8 rounded-full shadow-lg bg-white z-50 hover:bg-slate-100">
      <ArrowUp class="w-5 h-5 text-slate-900" />
    </Button>

    <LandingFooter />
  </div>
</template>

<style scoped>
@keyframes fade-up {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.landing-fade-up {
  animation: fade-up 0.9s ease both;
}

.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: opacity 0.5s ease, transform 0.5s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(6px);
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.5s ease, transform 0.5s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
</style>
