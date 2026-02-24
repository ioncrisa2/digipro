<script setup>
import { Head } from '@inertiajs/vue3'
import BlogNavbar from '@/layouts/BlogNavbar.vue'
import BlogFooter from '@/layouts/BlogFooter.vue'

const props = defineProps({
  title: { type: String, default: 'Blog - DigiPro' },
  description: { type: String, default: '' },
})
</script>

<template>
  <div class="blog-shell text-slate-900 bg-white min-h-screen flex flex-col">
    <Head :title="title">
      <meta v-if="description" name="description" :content="description" />
      <slot name="head" />
    </Head>

    <BlogNavbar />

    <main class="flex-1">
      <slot />
    </main>

    <BlogFooter />
  </div>
</template>
