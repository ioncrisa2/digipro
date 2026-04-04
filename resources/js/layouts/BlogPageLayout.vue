<script setup>
import { Head } from '@inertiajs/vue3'
import BlogNavbar from '@/layouts/BlogNavbar.vue'
import BlogFooter from '@/layouts/BlogFooter.vue'

defineProps({
  title: { type: String, default: 'Artikel DigiPro' },
  description: { type: String, default: '' },
})
</script>

<template>
  <div class="blog-shell relative min-h-screen overflow-hidden bg-[#f7f4ed] text-slate-900">
    <Head :title="title">
      <meta v-if="description" name="description" :content="description" />
      <slot name="head" />
    </Head>

    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.10),_transparent_24%),radial-gradient(circle_at_bottom_right,_rgba(15,23,42,0.10),_transparent_28%)]" />
    <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(15,23,42,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(15,23,42,0.04)_1px,transparent_1px)] bg-[size:34px_34px] opacity-35" />

    <div class="relative flex min-h-screen flex-col">
      <BlogNavbar />

      <main class="flex-1">
        <slot />
      </main>

      <BlogFooter />
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap');

:global(.blog-shell) {
  font-family: 'Instrument Sans', sans-serif;
  color: #0f172a;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

:global(.blog-shell h1),
:global(.blog-shell h2),
:global(.blog-shell h3),
:global(.blog-shell h4) {
  font-family: 'Space Grotesk', sans-serif;
}
</style>
