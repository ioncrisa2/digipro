<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3'
import AuthLayout from '@/layouts/AuthLayout.vue'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Mail, ArrowLeft } from 'lucide-vue-next'

const page = usePage()

const form = useForm({
  email: '',
})

const submit = () => {
  form.post('/forgot-password', { preserveScroll: true })
}
</script>

<template>
  <AuthLayout>
    <div class="flex flex-col items-center text-center space-y-2 mb-8">
      <div class="p-3 bg-slate-900/10 text-slate-900 rounded-2xl mb-2">
        <Mail class="w-6 h-6" />
      </div>
      <h1 class="text-2xl font-semibold text-slate-900">Lupa Password</h1>
      <p class="text-sm text-slate-500 max-w-[300px]">
        Masukkan email terdaftar. Kami akan kirimkan tautan untuk reset password.
      </p>
    </div>

    <div v-if="page.props.flash?.status" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
      {{ page.props.flash.status }}
    </div>

    <form @submit.prevent="submit" class="grid gap-4 mt-6">
      <div class="grid gap-2">
        <Label for="email">Email</Label>
        <Input id="email" type="email" v-model="form.email" class="h-11 bg-slate-50 border-slate-200" required />
        <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">
          {{ form.errors.email }}
        </p>
      </div>

      <Button type="submit" class="w-full h-11 bg-slate-900 hover:bg-slate-800 text-white" :disabled="form.processing">
        {{ form.processing ? 'Mengirim...' : 'Kirim Link Reset' }}
      </Button>

      <div class="text-center text-sm text-slate-500">
        <Link href="/login" class="inline-flex items-center gap-2 text-slate-700 hover:text-slate-900">
          <ArrowLeft class="w-4 h-4" />
          Kembali ke Login
        </Link>
      </div>
    </form>
  </AuthLayout>
</template>
