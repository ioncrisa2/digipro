<script setup>
import { Head } from '@inertiajs/vue3'
import BlogNavbar from '@/layouts/BlogNavbar.vue'
import BlogFooter from '@/layouts/BlogFooter.vue'

defineProps({
  title: { type: String, default: 'Artikel DigiPro by KJPP HJAR' },
  description: { type: String, default: '' },
})
</script>

<template>
  <div class="blog-shell relative min-h-dvh overflow-hidden text-slate-900">
    <Head :title="title">
      <meta v-if="description" name="description" :content="description" />
      <slot name="head" />
    </Head>

    <a
      href="#content"
      class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-900 focus:shadow focus:ring-1 focus:ring-slate-200 focus-visible:outline-none"
    >
      Lewati ke konten
    </a>

    <div class="relative flex min-h-dvh flex-col">
      <BlogNavbar />

      <main id="content" class="flex-1">
        <slot />
      </main>

      <BlogFooter />
    </div>
  </div>
</template>
