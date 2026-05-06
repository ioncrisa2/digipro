<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Menu } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetTrigger, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import BrandLockup from '@/components/brand/BrandLockup.vue'
import { useReducedMotion } from '@/composables/useReducedMotion'
import NotificationCenter from '@/components/ui/notification/NotificationCenter.vue'

const navItems = ['Features', 'Showcase', 'Process', 'Testimonials', 'FAQ']

const page = usePage()
const currentPath = computed(() => page.url.split('?')[0] || '/')

const { prefersReducedMotion } = useReducedMotion()

const isModifiedClick = (event) => {
  if (!event) return false
  return event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0
}

const sectionIdFromLabel = (section) => section.toLowerCase().replace(/ /g, '-')
const sectionHref = (section) => `/#${sectionIdFromLabel(section)}`

const scrollBehavior = () => (prefersReducedMotion.value ? 'auto' : 'smooth')

const handleNavigation = (section, event) => {
  if (isModifiedClick(event)) return
  event?.preventDefault()
  const sectionId = section.toLowerCase().replace(/ /g, '-')

  if (currentPath.value === '/') {
    const el = document.getElementById(sectionId)
    if (el) {
      el.scrollIntoView({ behavior: scrollBehavior(), block: 'start' })
      window.history.pushState(null, '', `#${sectionId}`)
    }
  } else {
    router.visit(`/#${sectionId}`)
  }
}

const handleHomeClick = (event) => {
  if (isModifiedClick(event)) return
  if (currentPath.value !== '/') return

  event?.preventDefault()
  window.scrollTo({ top: 0, behavior: scrollBehavior() })
  window.history.pushState(null, '', '/')
}

</script>

<template>
  <a
    href="#content"
    class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-900 focus:shadow focus:ring-1 focus:ring-slate-200 focus-visible:outline-none"
  >
    Lewati ke konten
  </a>

  <NotificationCenter />

  <nav class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 py-3">
    <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">
      <Link
        href="/"
        aria-label="Beranda"
        class="rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2"
        @click="handleHomeClick"
      >
        <BrandLockup
          wrapper-class="border border-slate-200/80 bg-white shadow-sm"
          logo-class="w-[180px] sm:w-[208px]"
        />
      </Link>

      <div class="hidden md:flex items-center gap-6">
        <a
          v-for="item in navItems"
          :key="item"
          :href="sectionHref(item)"
          class="rounded-md text-base font-medium text-slate-600 transition-colors hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2"
          @click="(event) => handleNavigation(item, event)"
        >
          {{ item }}
        </a>

        <div class="h-4 w-px bg-slate-200/70 mx-1" />

        <Link href="/artikel" class="text-base font-medium text-slate-600 hover:text-slate-900">
          Artikel
        </Link>
        <Link href="/contact" class="text-base font-medium text-slate-600 hover:text-slate-900">
          Kontak
        </Link>

        <Button
          as-child
          variant="default"
          size="sm"
          class="bg-slate-900 hover:bg-slate-800"
        >
          <Link href="/login">Login</Link>
        </Button>
      </div>

      <div class="md:hidden">
        <Sheet>
          <SheetTrigger as-child>
            <Button variant="ghost" size="icon" aria-label="Buka menu navigasi">
              <Menu class="h-6 w-6 text-slate-900" />
            </Button>
          </SheetTrigger>
          <SheetContent side="right">
            <SheetHeader class="text-center">
              <SheetTitle class="text-center font-semibold text-lg">Menu</SheetTitle>
            </SheetHeader>
            <div class="flex flex-col items-center text-center gap-3 mt-8">
              <a
                v-for="item in navItems"
                :key="item"
                :href="sectionHref(item)"
                class="w-full rounded-lg px-3 py-2 text-lg font-medium text-slate-700 transition-colors hover:bg-slate-100/80 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2"
                @click="(event) => handleNavigation(item, event)"
              >
                {{ item }}
              </a>

              <hr class="border-slate-100" />

              <Link href="/artikel" class="w-full rounded-lg px-3 py-2 text-lg font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100/80 transition-colors">
                Artikel
              </Link>
              <Link href="/contact" class="w-full rounded-lg px-3 py-2 text-lg font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-100/80 transition-colors">
                Kontak
              </Link>

              <Button as-child class="w-full bg-slate-900 mt-4 hover:bg-slate-800 cursor-pointer">
                <Link href="/login">Masuk ke Portal</Link>
              </Button>
            </div>
          </SheetContent>
        </Sheet>
      </div>
    </div>
  </nav>
</template>
