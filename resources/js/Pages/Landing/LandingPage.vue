<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'

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

const props = defineProps({
  features: { type: Array, default: () => [] },
  faqs: { type: Array, default: () => [] },
  recentArticles: { type: Array, default: () => [] },
  testimonials: { type: Array, default: () => [] },
  heroBackgroundUrl: { type: String, default: '' },
  platformPreviewImages: { type: Array, default: () => [] },
})

const scrollToTop = () => {
  window.scrollTo({ top: 0, behavior: 'smooth' })
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
  activeSlide.value = (activeSlide.value + 1) % slides.value.length
}

const prevSlide = () => {
  activeSlide.value = (activeSlide.value - 1 + slides.value.length) % slides.value.length
}

const nextTestimonial = () => {
  if (!props.testimonials?.length) return
  testimonialIndex.value = (testimonialIndex.value + 1) % props.testimonials.length
}

const goTestimonial = (index) => {
  testimonialIndex.value = index
}

onMounted(() => {
  slideTimer = window.setInterval(nextSlide, 5000)
  testimonialTimer = window.setInterval(nextTestimonial, 6500)
})

onBeforeUnmount(() => {
  if (slideTimer) window.clearInterval(slideTimer)
  if (testimonialTimer) window.clearInterval(testimonialTimer)
})
</script>

<template>
  <div class="landing-shell bg-[#f5efe6] text-slate-900 selection:bg-slate-900 selection:text-white">
    <LandingNavbar />

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

    <Button @click="scrollToTop" variant="outline" size="icon" class="fixed bottom-8 right-8 z-50 rounded-full bg-white shadow-lg hover:bg-slate-100">
      <ArrowUp class="h-5 w-5 text-slate-900" />
    </Button>

    <LandingFooter />
  </div>
</template>
