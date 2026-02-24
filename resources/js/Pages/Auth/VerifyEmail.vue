<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

import AuthLayout from '@/layouts/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import { MailCheck, LogOut } from 'lucide-vue-next'

const page = usePage()
const status = computed(() => page.props.flash?.status)

const resend = () => {
  router.post('/email/verification-notification')
}
</script>

<template>
  <AuthLayout>
    <div class="flex flex-col items-center text-center space-y-2 mb-8">
      <div class="p-3 bg-slate-900/10 text-slate-900 rounded-2xl mb-2">
        <MailCheck class="w-6 h-6" />
      </div>
      <h1 class="text-2xl font-semibold text-slate-900">Verifikasi Email</h1>
      <p class="text-sm text-slate-500 max-w-[320px]">
        Kami sudah mengirim tautan verifikasi ke email Anda. Buka email lalu klik tautan untuk mengaktifkan akun.
      </p>
    </div>

    <div v-if="status === 'verification-link-sent'" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
      Tautan verifikasi baru sudah dikirim. Silakan cek inbox atau spam.
    </div>

    <div class="mt-6 grid gap-3">
      <Button type="button" class="w-full bg-slate-900 hover:bg-slate-800 text-white" @click="resend">
        Kirim ulang email verifikasi
      </Button>

      <Link
        href="/logout"
        method="post"
        as="button"
        class="inline-flex items-center justify-center gap-2 text-sm text-slate-600 hover:text-slate-900"
      >
        <LogOut class="w-4 h-4" />
        Logout
      </Link>
    </div>
  </AuthLayout>
</template>
