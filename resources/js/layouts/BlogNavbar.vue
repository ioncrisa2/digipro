<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ArrowRight, Menu, Search } from 'lucide-vue-next'
import BrandLockup from '@/components/brand/BrandLockup.vue'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import NotificationCenter from '@/components/ui/notification/NotificationCenter.vue'

const page = usePage()
const isScrolled = ref(false)
const searchQuery = ref(page.props.filters?.q || '')
const searchScope = ref(page.props.filters?.scope || 'article')

const filters = computed(() => page.props.filters ?? {})
const categories = computed(() => {
  const nav = page.props.blogNavCategories ?? []

  if (Array.isArray(nav) && nav.length) {
    return nav.map((item) => ({ label: item.name, slug: item.slug }))
  }

  return []
})

const searchOptions = [
  { value: 'article', label: 'Artikel' },
  { value: 'category', label: 'Kategori' },
  { value: 'tag', label: 'Tag' },
]

const submitSearch = () => {
  router.get(
    route('articles.index'),
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

const handleScroll = () => {
  isScrolled.value = window.scrollY > 12
}

const activeCategory = computed(() => filters.value.category || null)

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
  handleScroll()
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
  <NotificationCenter />

  <nav
    class="sticky top-0 z-50 transition-[background-color,border-color,box-shadow,opacity,transform] duration-200 motion-reduce:transition-none"
    :class="
      isScrolled
        ? 'border-b border-slate-200/80 bg-white/95 shadow-[0_8px_30px_rgba(15,23,42,0.08)]'
        : 'bg-transparent'
    "
  >
    <div class="mx-auto flex h-20 w-full max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
      <Link href="/" class="group flex items-center">
        <BrandLockup
          wrapper-class="border border-black/5 bg-white/95 shadow-[0_10px_24px_rgba(15,23,42,0.08)]"
          logo-class="w-[180px] sm:w-[208px]"
        />
      </Link>

      <div class="hidden flex-1 items-center justify-center lg:flex">
        <div class="flex items-center gap-1 rounded-full border border-slate-200 bg-white/90 p-1 shadow-sm">
          <Link
            :href="route('articles.index')"
            class="rounded-full px-4 py-2 text-sm font-medium transition"
            :class="!activeCategory ? 'bg-slate-950 text-white' : 'text-slate-600 hover:text-slate-950'"
          >
            Semua
          </Link>
          <Link
            v-for="category in categories.slice(0, 4)"
            :key="category.slug"
            :href="route('articles.index', { category: category.slug })"
            class="rounded-full px-4 py-2 text-sm font-medium transition"
            :class="
              activeCategory === category.slug
                ? 'bg-slate-950 text-white'
                : 'text-slate-600 hover:text-slate-950'
            "
          >
            {{ category.label }}
          </Link>
        </div>
      </div>

      <form
        class="hidden items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-2 shadow-sm focus-within:ring-2 focus-within:ring-slate-900/20 focus-within:ring-offset-2 focus-within:ring-offset-white xl:flex"
        @submit.prevent="submitSearch"
      >
        <Search class="h-4 w-4 text-slate-400" />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Cari insight penilaian…"
          class="w-44 bg-transparent text-base text-slate-700 outline-none placeholder:text-slate-400 md:text-sm"
        />
        <select
          v-model="searchScope"
          class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2"
        >
          <option v-for="option in searchOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </form>

      <div class="ml-auto hidden md:block">
        <Button
          class="rounded-full bg-slate-950 px-5 text-white hover:bg-slate-800"
          as-child
        >
          <Link href="/">
            Buka DigiPro by KJPP HJAR
            <ArrowRight class="ml-2 h-4 w-4" />
          </Link>
        </Button>
      </div>

      <div class="ml-auto lg:hidden">
        <Sheet>
          <SheetTrigger as-child>
            <Button aria-label="Buka menu" variant="ghost" size="icon" class="h-11 w-11 rounded-full border border-slate-200 bg-white/80 shadow-sm">
              <Menu class="h-5 w-5 text-slate-900" />
            </Button>
          </SheetTrigger>

          <SheetContent
            side="right"
            class="border-l border-slate-200 bg-white px-6 pt-[calc(env(safe-area-inset-top)+1.75rem)] pb-[calc(env(safe-area-inset-bottom)+1.75rem)] overflow-y-auto overscroll-contain sm:px-7"
          >
            <SheetHeader class="pr-12">
              <SheetTitle class="text-left">
                <BrandLockup
                  wrapper-class="border border-black/5 bg-white"
                  logo-class="w-[204px]"
                />
              </SheetTitle>
            </SheetHeader>

            <div class="mt-6 space-y-6">
              <form class="space-y-3" @submit.prevent="submitSearch">
                <label class="block text-[11px] font-semibold uppercase text-slate-500">
                  Cari Artikel
                </label>
                <div class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm focus-within:ring-2 focus-within:ring-slate-900/20 focus-within:ring-offset-2 focus-within:ring-offset-white">
                  <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                    <Search class="h-4 w-4 text-slate-400" />
                    <input
                      v-model="searchQuery"
                      type="search"
                      placeholder="Masukkan topik, kategori, atau tag"
                      class="w-full bg-transparent text-base text-slate-700 outline-none placeholder:text-slate-400"
                    />
                  </div>
                  <div class="mt-3 rounded-2xl bg-slate-100 p-1">
                    <div class="grid grid-cols-3 gap-1" role="radiogroup" aria-label="Cakupan pencarian">
                    <label
                      v-for="option in searchOptions"
                      :key="option.value"
                      class="cursor-pointer"
                    >
                      <input v-model="searchScope" type="radio" class="sr-only" :value="option.value" />
                      <span
                        class="block min-h-11 rounded-xl px-3 py-2 text-center text-xs font-semibold transition motion-reduce:transition-none"
                        :class="
                          searchScope === option.value
                            ? 'bg-white text-slate-950 shadow-sm'
                            : 'text-slate-600 hover:text-slate-950'
                        "
                      >
                        {{ option.label }}
                      </span>
                    </label>
                    </div>
                  </div>
                </div>
              </form>

              <div class="space-y-3">
                <p class="text-[11px] font-semibold uppercase text-slate-500">
                  Navigasi
                </p>
                <nav class="space-y-2" aria-label="Navigasi artikel">
                  <Link
                    :href="route('articles.index')"
                    class="flex min-h-11 items-center rounded-xl px-4 py-3 text-sm font-semibold transition motion-reduce:transition-none"
                    :class="!activeCategory ? 'bg-slate-950 text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
                    :aria-current="!activeCategory ? 'page' : undefined"
                  >
                    Semua Artikel
                  </Link>
                  <Link
                    v-for="category in categories"
                    :key="category.slug"
                    :href="route('articles.index', { category: category.slug })"
                    class="flex min-h-11 items-center rounded-xl px-4 py-3 text-sm font-semibold transition motion-reduce:transition-none"
                    :class="
                      activeCategory === category.slug
                        ? 'bg-slate-950 text-white'
                        : 'bg-slate-50 text-slate-700 hover:bg-slate-100'
                    "
                    :aria-current="activeCategory === category.slug ? 'page' : undefined"
                  >
                    {{ category.label }}
                  </Link>
                </nav>
              </div>

              <Button class="w-full rounded-full bg-slate-950 text-white hover:bg-slate-800" as-child>
                <Link href="/">
                  Buka DigiPro by KJPP HJAR
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Link>
              </Button>
            </div>
          </SheetContent>
        </Sheet>
      </div>
    </div>
  </nav>
</template>
