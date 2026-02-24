<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Menu } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetTrigger, SheetHeader, SheetTitle } from '@/components/ui/sheet'

const navItems = ['Features', 'Showcase', 'Process', 'Testimonials', 'FAQ']

const page = usePage()
const currentPath = computed(() => page.url.split('?')[0] || '/')

const handleNavigation = (section) => {
  const sectionId = section.toLowerCase().replace(/ /g, '-')

  if (currentPath.value === '/') {
    const el = document.getElementById(sectionId)
    if (el) el.scrollIntoView({ behavior: 'smooth' })
  } else {
    router.visit(`/#${sectionId}`)
  }
}

const navigateHome = () => {
  router.visit('/', {
    onSuccess: () => {
      window.scrollTo({ top: 0, behavior: 'smooth' })
    },
  })
}

const goLogin = () => router.visit('/login')
</script>

<template>
  <nav class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 py-3 backdrop-blur">
    <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">
      <div
        @click="navigateHome"
        class="flex items-center gap-3 cursor-pointer"
      >
        <div class="h-10 w-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-semibold">
          DP
        </div>
        <div class="leading-tight">
          <div class="text-xl font-semibold tracking-tight text-slate-900">DigiPro</div>
          <div class="text-xs text-slate-500">Valuation Platform</div>
        </div>
      </div>

      <div class="hidden md:flex items-center gap-6">
        <button
          v-for="item in navItems"
          :key="item"
          @click="handleNavigation(item)"
          class="text-base font-medium text-slate-600 hover:text-slate-900 transition-colors"
        >
          {{ item }}
        </button>

        <div class="h-4 w-px bg-slate-200/70 mx-1" />

        <Link href="/artikel" class="text-base font-medium text-slate-600 hover:text-slate-900">
          Artikel
        </Link>
        <Link href="/contact" class="text-base font-medium text-slate-600 hover:text-slate-900">
          Kontak
        </Link>

        <Button
          variant="default"
          size="sm"
          @click="goLogin"
          class="bg-slate-900 hover:bg-slate-800"
        >
          Login
        </Button>
      </div>

      <div class="md:hidden">
        <Sheet>
          <SheetTrigger as-child>
            <Button variant="ghost" size="icon">
              <Menu class="h-6 w-6 text-slate-900" />
            </Button>
          </SheetTrigger>
          <SheetContent side="right">
            <SheetHeader class="text-center">
              <SheetTitle class="text-center font-semibold text-lg">Menu</SheetTitle>
            </SheetHeader>
            <div class="flex flex-col items-center text-center gap-3 mt-8">
              <button
                v-for="item in navItems"
                :key="item"
                @click="handleNavigation(item)"
                class="w-full rounded-lg px-3 py-2 text-lg font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100/80 transition-colors"
              >
                {{ item }}
              </button>

              <hr class="border-slate-100" />

              <Link href="/artikel" class="w-full rounded-lg px-3 py-2 text-lg font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100/80 transition-colors">
                Artikel
              </Link>
              <Link href="/contact" class="w-full rounded-lg px-3 py-2 text-lg font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100/80 transition-colors">
                Kontak
              </Link>

              <Button class="w-full bg-slate-900 mt-4 hover:bg-slate-800 cursor-pointer" @click="goLogin">
                Masuk ke Portal
              </Button>
            </div>
          </SheetContent>
        </Sheet>
      </div>
    </div>
  </nav>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap');

:global(.landing-shell) {
  font-family: 'Instrument Sans', sans-serif;
  background: #f7f4ef;
  color: #0f172a;
}

:global(.landing-shell h1),
:global(.landing-shell h2),
:global(.landing-shell h3),
:global(.landing-shell .landing-title) {
  font-family: 'Space Grotesk', sans-serif;
}
</style>
