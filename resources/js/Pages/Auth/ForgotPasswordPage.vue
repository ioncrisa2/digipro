<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import AuthLayout from '@/layouts/AuthLayout.vue'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Mail, ArrowLeft } from 'lucide-vue-next'

const page = usePage()
const flashMessage = computed(() => page.props.flash?.success || page.props.flash?.status || null)

const form = useForm({
  email: '',
})

const submit = () => {
  form.post('/forgot-password', { preserveScroll: true })
}
</script>

<template>
  <AuthLayout>
    <div class="mb-8 flex flex-col items-center space-y-2 text-center">
      <div class="mb-2 rounded-2xl bg-slate-900/10 p-3 text-slate-900">
        <Mail class="h-6 w-6" />
      </div>
      <h1 class="text-2xl font-semibold text-slate-900">Lupa Password</h1>
      <p class="max-w-[300px] text-sm text-slate-500">
        Masukkan email terdaftar. Kami akan kirimkan tautan untuk reset password.
      </p>
    </div>

    <div v-if="flashMessage" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
      {{ flashMessage }}
    </div>

    <form @submit.prevent="submit" class="mt-6 grid gap-4">
      <div class="grid gap-2">
        <Label for="email">Email</Label>
        <Input id="email" v-model="form.email" type="email" class="h-11 border-slate-200 bg-slate-50" required />
        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">
          {{ form.errors.email }}
        </p>
      </div>

      <Button type="submit" class="h-11 w-full bg-slate-900 text-white hover:bg-slate-800" :disabled="form.processing">
        {{ form.processing ? 'Mengirim…' : 'Kirim Link Reset' }}
      </Button>

      <div class="text-center text-sm text-slate-500">
        <Link href="/login" class="inline-flex items-center gap-2 text-slate-700 hover:text-slate-900">
          <ArrowLeft class="h-4 w-4" />
          Kembali ke Login
        </Link>
      </div>
    </form>
  </AuthLayout>
</template>
