<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick, watch } from 'vue'

import LandingNavbar from '@/layouts/LandingNavbar.vue'
import LandingFooter from '@/layouts/LandingFooter.vue'

import LandingHeroSection from '@/components/landing/LandingHeroSection.vue'
import LandingPlatformPreviewSection from '@/components/landing/LandingPlatformPreviewSection.vue'
import LandingFeaturesSection from '@/components/landing/LandingFeaturesSection.vue'
import LandingWorkflowSection from '@/components/landing/LandingWorkflowSection.vue'
import LandingTestimonialsSection from '@/components/landing/LandingTestimonialsSection.vue'
import LandingFaqSection from '@/components/landing/LandingFaqSection.vue'
import LandingRecentArticlesSection from '@/components/landing/LandingRecentArticlesSection.vue'
import LandingFinalCtaSection from '@/components/landing/LandingFinalCtaSection.vue'
import {
  buildLandingFeatureCards,
  buildLandingSlides,
  landingHeroFallback,
  landingProcessSteps,
  landingTestimonialAvatarFallback,
} from '@/components/landing/landingPlaceholders'

import { Button } from '@/components/ui/button'
import { ArrowUp } from 'lucide-vue-next'
import { useReducedMotion } from '@/composables/useReducedMotion'

const props = defineProps({
  features: { type: Array, default: () => [] },
  faqs: { type: Array, default: () => [] },
  recentArticles: { type: Array, default: () => [] },
  testimonials: { type: Array, default: () => [] },
  heroBackgroundUrl: { type: String, default: '' },
  platformPreviewImages: { type: Array, default: () => [] },
})

const { prefersReducedMotion } = useReducedMotion()

const scrollBehavior = () => (prefersReducedMotion.value ? 'auto' : 'smooth')

const scrollToTop = () => {
  window.scrollTo({ top: 0, behavior: scrollBehavior() })
}

const resolvedHeroBackground = computed(() => props.heroBackgroundUrl || landingHeroFallback)
const slides = computed(() => buildLandingSlides(props.platformPreviewImages))
const featureCards = computed(() => buildLandingFeatureCards(props.features ?? []))
const processSteps = landingProcessSteps

const activeSlide = ref(0)
let slideTimer = null
const currentSlide = computed(() => slides.value[activeSlide.value])

const testimonialIndex = ref(0)
let testimonialTimer = null
const currentTestimonial = computed(() => {
  if (!props.testimonials?.length) return null

  const item = props.testimonials[testimonialIndex.value % props.testimonials.length]

  return {
    ...item,
    avatar: item.photo_url || landingTestimonialAvatarFallback,
  }
})

const goSlide = (index) => {
  activeSlide.value = index
}

const nextSlide = () => {
  if (slides.value.length <= 1) return
  activeSlide.value = (activeSlide.value + 1) % slides.value.length
}

const prevSlide = () => {
  if (slides.value.length <= 1) return
  activeSlide.value = (activeSlide.value - 1 + slides.value.length) % slides.value.length
}

const nextTestimonial = () => {
  if (!props.testimonials?.length) return
  if (props.testimonials.length <= 1) return
  testimonialIndex.value = (testimonialIndex.value + 1) % props.testimonials.length
}

const goTestimonial = (index) => {
  testimonialIndex.value = index
}

const stopTimers = () => {
  if (slideTimer) window.clearInterval(slideTimer)
  if (testimonialTimer) window.clearInterval(testimonialTimer)
  slideTimer = null
  testimonialTimer = null
}

const startTimers = () => {
  stopTimers()
  if (prefersReducedMotion.value) return

  if (slides.value.length > 1) slideTimer = window.setInterval(nextSlide, 5000)
  if (props.testimonials?.length > 1) testimonialTimer = window.setInterval(nextTestimonial, 6500)
}

const scrollToHash = async () => {
  const raw = window.location.hash || ''
  const id = raw.startsWith('#') ? raw.slice(1) : raw
  if (!id) return

  await nextTick()
  const el = document.getElementById(decodeURIComponent(id))
  if (!el) return

  el.scrollIntoView({ behavior: scrollBehavior(), block: 'start' })
}

const handleVisibilityChange = () => {
  if (document.hidden) {
    stopTimers()
    return
  }

  startTimers()
}

onMounted(() => {
  scrollToHash()
  startTimers()
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  stopTimers()
})

watch(prefersReducedMotion, () => {
  startTimers()
})
</script>

<template>
  <div class="landing-shell text-slate-900 selection:bg-slate-900 selection:text-white">
    <LandingNavbar />

    <main id="content">
      <LandingHeroSection :background-url="resolvedHeroBackground" />

      <LandingPlatformPreviewSection
        :slides="slides"
        :active-slide="activeSlide"
        :current-slide="currentSlide"
        @previous="prevSlide"
        @next="nextSlide"
        @go="goSlide"
      />

      <LandingFeaturesSection :feature-cards="featureCards" />

      <LandingWorkflowSection :process-steps="processSteps" />

      <LandingRecentArticlesSection :articles="props.recentArticles" />

      <LandingTestimonialsSection
        :testimonials="props.testimonials"
        :testimonial-index="testimonialIndex"
        :current-testimonial="currentTestimonial"
        @go="goTestimonial"
      />

      <LandingFaqSection :faqs="props.faqs" />

      <LandingFinalCtaSection />
    </main>

    <Button
      aria-label="Kembali ke atas"
      @click="scrollToTop"
      variant="outline"
      size="icon"
      class="fixed bottom-8 right-8 z-50 rounded-full bg-white shadow-lg hover:bg-slate-100"
    >
      <ArrowUp class="h-5 w-5 text-slate-900" />
    </Button>

    <LandingFooter />
  </div>
</template>
