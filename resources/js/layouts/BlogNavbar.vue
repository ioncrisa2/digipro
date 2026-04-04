<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ArrowRight, Menu, Search } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'

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
  <nav
    class="sticky top-0 z-50 transition-all duration-300"
    :class="
      isScrolled
        ? 'border-b border-white/70 bg-[#f7f4ed]/92 shadow-[0_8px_30px_rgba(15,23,42,0.08)] backdrop-blur-xl'
        : 'bg-transparent'
    "
  >
    <div class="mx-auto flex h-20 w-full max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
      <Link href="/" class="group flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-950 text-sm font-bold text-white shadow-[0_10px_24px_rgba(15,23,42,0.18)] transition-transform duration-300 group-hover:scale-[1.03]">
          DP
        </div>
        <div class="leading-tight">
          <div class="text-[11px] font-semibold uppercase tracking-[0.28em] text-emerald-700">
            DigiPro Journal
          </div>
          <div class="font-['Space_Grotesk'] text-lg font-semibold tracking-tight text-slate-950">
            Artikel & Insight
          </div>
        </div>
      </Link>

      <div class="hidden flex-1 items-center justify-center lg:flex">
        <div class="flex items-center gap-1 rounded-full border border-white/70 bg-white/75 p-1 shadow-sm backdrop-blur">
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
        class="hidden items-center gap-2 rounded-full border border-white/70 bg-white/80 px-3 py-2 shadow-sm backdrop-blur xl:flex"
        @submit.prevent="submitSearch"
      >
        <Search class="h-4 w-4 text-slate-400" />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Cari insight penilaian..."
          class="w-44 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400"
        />
        <select
          v-model="searchScope"
          class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 outline-none"
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
            Open DigiPro
            <ArrowRight class="ml-2 h-4 w-4" />
          </Link>
        </Button>
      </div>

      <div class="ml-auto lg:hidden">
        <Sheet>
          <SheetTrigger as-child>
            <Button variant="ghost" size="icon" class="h-11 w-11 rounded-full border border-white/70 bg-white/80 shadow-sm">
              <Menu class="h-5 w-5 text-slate-900" />
            </Button>
          </SheetTrigger>

          <SheetContent side="right" class="w-[22rem] border-l-white/60 bg-[#f7f4ed]">
            <SheetHeader>
              <SheetTitle class="text-left font-['Space_Grotesk'] text-2xl font-semibold tracking-tight text-slate-950">
                DigiPro Journal
              </SheetTitle>
            </SheetHeader>

            <div class="mt-8 space-y-8">
              <form class="space-y-3" @submit.prevent="submitSearch">
                <label class="block text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">
                  Cari Artikel
                </label>
                <div class="rounded-3xl border border-white/70 bg-white/85 p-3 shadow-sm">
                  <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                    <Search class="h-4 w-4 text-slate-400" />
                    <input
                      v-model="searchQuery"
                      type="search"
                      placeholder="Masukkan topik, kategori, atau tag"
                      class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400"
                    />
                  </div>
                  <div class="mt-3 grid grid-cols-3 gap-2">
                    <label
                      v-for="option in searchOptions"
                      :key="option.value"
                      class="cursor-pointer"
                    >
                      <input v-model="searchScope" type="radio" class="sr-only" :value="option.value" />
                      <span
                        class="block rounded-full px-3 py-2 text-center text-xs font-medium transition"
                        :class="
                          searchScope === option.value
                            ? 'bg-slate-950 text-white'
                            : 'bg-slate-100 text-slate-600'
                        "
                      >
                        {{ option.label }}
                      </span>
                    </label>
                  </div>
                </div>
              </form>

              <div class="space-y-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">
                  Navigasi
                </p>
                <div class="space-y-2">
                  <Link
                    :href="route('articles.index')"
                    class="block rounded-2xl px-4 py-3 text-sm font-medium transition"
                    :class="!activeCategory ? 'bg-slate-950 text-white' : 'bg-white/80 text-slate-700'"
                  >
                    Semua Artikel
                  </Link>
                  <Link
                    v-for="category in categories"
                    :key="category.slug"
                    :href="route('articles.index', { category: category.slug })"
                    class="block rounded-2xl px-4 py-3 text-sm font-medium transition"
                    :class="
                      activeCategory === category.slug
                        ? 'bg-slate-950 text-white'
                        : 'bg-white/80 text-slate-700'
                    "
                  >
                    {{ category.label }}
                  </Link>
                </div>
              </div>

              <Button class="w-full rounded-full bg-slate-950 text-white hover:bg-slate-800" as-child>
                <Link href="/">
                  Open DigiPro
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
