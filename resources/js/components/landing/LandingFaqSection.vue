<script setup>
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'

defineProps({
  faqs: { type: Array, required: true },
})
</script>

<template>
  <section id="faq" class="scroll-mt-24 bg-white/70 px-6 py-24">
    <div class="mx-auto max-w-3xl">
      <h2 class="mb-12 text-balance text-center text-3xl font-semibold text-slate-900">Pertanyaan umum</h2>

      <Accordion type="single" collapsible class="w-full rounded-[2rem] border border-slate-200 bg-white px-6 py-2 shadow-[0_16px_50px_rgba(15,23,42,0.05)]">
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
