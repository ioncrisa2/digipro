<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Menu } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetTrigger, SheetHeader, SheetTitle } from '@/components/ui/sheet'

const isScrolled = ref(false)
const page = usePage()

const filters = computed(() => page.props.filters ?? {})
const searchQuery = ref(filters.value.q || '')
const searchScope = ref(filters.value.scope || 'article')
const searchOptions = [
  { value: 'article', label: 'Artikel' },
  { value: 'category', label: 'Kategori' },
  { value: 'tag', label: 'Tag' },
]

const categories = computed(() => {
  const nav = page.props.blogNavCategories ?? []
  if (Array.isArray(nav) && nav.length) {
    return nav.map((item) => ({ label: item.name, slug: item.slug }))
  }

  return [
    { label: 'Market Insight', slug: 'market-insight' },
    { label: 'Regulasi', slug: 'regulasi' },
    { label: 'Studi Kasus', slug: 'studi-kasus' },
    { label: 'Tips Appraisal', slug: 'tips-appraisal' },
  ]
})

const handleScroll = () => {
  isScrolled.value = window.scrollY > 8
}

const submitSearch = () => {
  router.get(
    '/artikel',
    {
      category: filters.value.category || undefined,
      q: searchQuery.value || undefined,
      scope: searchScope.value || undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    },
  )
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleScroll)
})

const goDigiPro = () => router.visit('/')
</script>

<template>
  <nav
    class="sticky top-0 z-50 bg-white/95 backdrop-blur-md transition-all"
    :class="isScrolled ? 'shadow-sm border-b border-slate-200/60' : 'border-b border-transparent'"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center gap-4 h-16 w-full relative">
        <!-- Logo -->
        <Link href="/artikel" class="flex items-center gap-2.5 shrink-0 group">
          <div class="h-8 w-8 rounded-md bg-gradient-to-br from-slate-900 to-slate-700 text-white flex items-center justify-center text-xs font-bold shadow-sm">
            DP
          </div>
          <div class="leading-tight">
            <div class="text-base font-bold tracking-tight text-slate-900">DigiPro</div>
            <div class="text-[10px] text-slate-500 font-medium">Blog</div>
          </div>
        </Link>

        <!-- Desktop Navigation (absolutely centered) -->
        <div class="hidden lg:flex items-center gap-1 absolute left-1/2 -translate-x-1/2">
          <Link
            v-for="category in categories"
            :key="category.slug"
            :href="`/artikel?category=${category.slug}`"
            class="px-3 py-2 text-[13px] font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-md transition-all"
          >
            {{ category.label }}
          </Link>
        </div>

        <!-- Right side spacer -->
        <div class="flex-1"></div>

        <!-- DigiPro CTA Button -->
        <Button
          class="hidden md:flex rounded-full bg-slate-900 text-white hover:bg-slate-800 px-5 py-2 text-sm font-semibold shadow-sm transition-all shrink-0"
          @click="goDigiPro"
        >
          Open DigiPro
        </Button>

        <!-- Mobile Menu -->
        <div class="lg:hidden">
          <Sheet>
            <SheetTrigger as-child>
              <Button variant="ghost" size="icon" class="h-9 w-9">
                <Menu class="h-5 w-5 text-slate-900" />
              </Button>
            </SheetTrigger>
            <SheetContent side="right" class="w-72">
              <SheetHeader>
                <SheetTitle class="text-left font-bold text-lg">Menu</SheetTitle>
              </SheetHeader>
              <div class="flex flex-col gap-1 mt-6">
                <!-- Mobile search -->
                <form class="flex flex-col gap-2 mb-4" @submit.prevent="submitSearch">
                  <div class="flex items-center gap-2">
                    <select
                      v-model="searchScope"
                      class="flex-1 text-sm px-3 py-2 rounded-lg border border-slate-200 text-slate-700 bg-slate-50 focus:ring-2 focus:ring-slate-900 focus:outline-none"
                    >
                      <option v-for="opt in searchOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                      </option>
                    </select>
                    <Button type="submit" size="sm" class="rounded-lg bg-slate-900 hover:bg-slate-800 text-white px-3">
                      Cari
                    </Button>
                  </div>
                  <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Cari di blog..."
                    class="w-full text-sm px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-slate-900 focus:outline-none"
                  />
                </form>

                <Link
                  v-for="category in categories"
                  :key="category.slug"
                  :href="`/artikel?category=${category.slug}`"
                  class="px-4 py-3 text-sm font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-50 rounded-lg transition-colors"
                >
                  {{ category.label }}
                </Link>

                <div class="border-t border-slate-200 my-4"></div>

                <Button class="w-full rounded-full bg-slate-900 hover:bg-slate-800 text-white font-semibold" @click="goDigiPro">
                  Open DigiPro
                </Button>
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </div>
    </div>
  </nav>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

:global(.blog-shell) {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: #ffffff;
  color: #0f172a;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

:global(.blog-shell h1),
:global(.blog-shell h2),
:global(.blog-shell h3) {
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  letter-spacing: -0.02em;
}
</style>
