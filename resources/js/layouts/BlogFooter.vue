<script setup>
import { Link } from '@inertiajs/vue3'
import NotificationCenter from '@/components/ui/notification/NotificationCenter.vue'

const currentYear = new Date().getFullYear()

const footerLinks = {
  product: [
    { label: 'Fitur', href: '/features' },
    { label: 'Harga', href: '/pricing' },
    { label: 'FAQ', href: '/faq' },
  ],
  company: [
    { label: 'Tentang Kami', href: '/about' },
    { label: 'Blog', href: '/artikel' },
    { label: 'Kontak', href: '/contact' },
  ],
  legal: [
    { label: 'Kebijakan Privasi', href: '/policy' },
    { label: 'Syarat & Ketentuan', href: '/terms' },
  ],
}

const socialLinks = [
  {
    name: 'Twitter',
    href: '#',
    icon: 'M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84'
  },
  {
    name: 'LinkedIn',
    href: '#',
    icon: 'M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z'
  },
  {
    name: 'Instagram',
    href: '#',
    icon: 'M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z'
  },
]
</script>

<template>
  <div>
    <NotificationCenter />

    <!-- Main Footer -->
    <footer class="border-t border-slate-200 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Top Section -->
        <div class="py-12 grid grid-cols-2 md:grid-cols-4 gap-8">
          <!-- Brand -->
          <div class="col-span-2 md:col-span-1">
            <div class="flex items-center gap-2.5 mb-4">
              <div class="h-8 w-8 rounded-md bg-gradient-to-br from-slate-900 to-slate-700 text-white flex items-center justify-center text-xs font-bold">
                DP
              </div>
              <div class="text-base font-bold text-slate-900">DigiPro</div>
            </div>
            <p class="text-sm text-slate-600 leading-relaxed mb-4">
              Platform penilaian properti terpercaya untuk profesional appraisal.
            </p>
            <!-- Social Links -->
            <div class="flex items-center gap-3">
              <a
                v-for="social in socialLinks"
                :key="social.name"
                :href="social.href"
                :aria-label="social.name"
                class="text-slate-400 hover:text-slate-900 transition-colors"
              >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path :d="social.icon" />
                </svg>
              </a>
            </div>
          </div>

          <!-- Product Links -->
          <div>
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Produk</h3>
            <ul class="space-y-3">
              <li v-for="link in footerLinks.product" :key="link.label">
                <Link
                  :href="link.href"
                  class="text-sm text-slate-600 hover:text-slate-900 transition-colors"
                >
                  {{ link.label }}
                </Link>
              </li>
            </ul>
          </div>

          <!-- Company Links -->
          <div>
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Perusahaan</h3>
            <ul class="space-y-3">
              <li v-for="link in footerLinks.company" :key="link.label">
                <Link
                  :href="link.href"
                  class="text-sm text-slate-600 hover:text-slate-900 transition-colors"
                >
                  {{ link.label }}
                </Link>
              </li>
            </ul>
          </div>

          <!-- Legal Links -->
          <div>
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Legal</h3>
            <ul class="space-y-3">
              <li v-for="link in footerLinks.legal" :key="link.label">
                <Link
                  :href="link.href"
                  class="text-sm text-slate-600 hover:text-slate-900 transition-colors"
                >
                  {{ link.label }}
                </Link>
              </li>
            </ul>
          </div>
        </div>

        <!-- Bottom Section -->
        <div class="py-6 border-t border-slate-200">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <p class="text-sm text-slate-500">
              © {{ currentYear }} DigiPro. All rights reserved.
            </p>
            <div class="flex items-center gap-4 text-xs text-slate-500">
              <span>Made with ❤️ in Indonesia</span>
            </div>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
/* Hover effects for social icons */
a svg {
  transition: transform 0.2s ease;
}

a:hover svg {
  transform: translateY(-2px);
}
</style>
