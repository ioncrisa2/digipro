<script setup>
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'

defineProps({
  faqs: { type: Array, required: true },
})
</script>

<template>
  <section id="faq" class="scroll-mt-24 bg-white px-6 py-20 md:py-24">
    <div class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[0.72fr_1.28fr]">
      <div class="space-y-4">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--landing-gold-dark)]">
          FAQ
        </p>
        <h2 class="text-balance text-3xl font-bold leading-tight text-[var(--landing-navy)] md:text-5xl">
          Pertanyaan umum sebelum mengajukan appraisal.
        </h2>
        <p class="text-base leading-8 text-slate-600">
          Ringkasan ini membantu pemberi tugas memahami dokumen, proses review, dan status pekerjaan di DIGIPRO.
        </p>
      </div>

      <Accordion type="single" collapsible class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-2 shadow-[0_10px_28px_rgba(15,23,42,0.045)]">
        <AccordionItem v-for="faq in faqs" :key="faq.value" :value="faq.value">
          <AccordionTrigger class="text-left text-base font-semibold text-slate-800 hover:no-underline">
            {{ faq.question }}
          </AccordionTrigger>
          <AccordionContent class="pb-4 text-pretty text-base leading-relaxed text-slate-600">
            {{ faq.answer }}
          </AccordionContent>
        </AccordionItem>
      </Accordion>
    </div>
  </section>
</template>
