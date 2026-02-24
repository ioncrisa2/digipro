<script setup>
import { Link } from '@inertiajs/vue3'
import { onBeforeMount, onBeforeUnmount, ref } from 'vue'
import { useLoginStore } from '@/stores/loginStore'

import AuthLayout from '@/layouts/AuthLayout.vue'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Loader2, Mail, Lock, Eye, EyeOff, LogIn, ShieldCheck } from 'lucide-vue-next'

const loginStore = useLoginStore()
const showPassword = ref(false)

onBeforeMount(() => {
  loginStore.reset()
})

onBeforeUnmount(() => {
  loginStore.reset()
})
</script>

<template>
  <AuthLayout>
    <div class="flex flex-col items-center text-center space-y-2 mb-8">
      <div class="p-3 bg-slate-900 text-white rounded-2xl mb-2 shadow-lg shadow-slate-200">
        <LogIn class="w-6 h-6" />
      </div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Masuk ke DigiPro</h1>
      <p class="text-sm text-slate-500 max-w-[300px]">
        Gunakan email terdaftar untuk mengakses dashboard appraisal Anda.
      </p>
    </div>

    <form @submit.prevent="loginStore.handleLogin" class="space-y-5">
      <div class="grid gap-2">
        <Label for="email" class="text-slate-700">Email</Label>
        <div class="relative">
          <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <Input
            id="email"
            type="email"
            placeholder="nama@perusahaan.com"
            v-model="loginStore.form.email"
            class="pl-10 h-11 bg-slate-50 border-slate-200"
            autocomplete="email"
            required
          />
        </div>
        <p v-if="loginStore.errors.email" class="text-[11px] font-medium text-red-500">
          {{ loginStore.errors.email }}
        </p>
      </div>

      <div class="grid gap-2">
        <div class="flex items-center justify-between">
          <Label for="password" class="text-slate-700">Password</Label>
          <Link
            href="/forgot-password"
            class="text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors"
          >
            Lupa password?
          </Link>
        </div>

        <div class="relative">
          <Lock class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <Input
            id="password"
            :type="showPassword ? 'text' : 'password'"
            v-model="loginStore.form.password"
            class="pl-10 pr-10 h-11 bg-slate-50 border-slate-200"
            placeholder="********"
            autocomplete="current-password"
            required
          />
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
          >
            <Eye v-if="!showPassword" class="w-4 h-4" />
            <EyeOff v-else class="w-4 h-4" />
          </button>
        </div>

        <p v-if="loginStore.errors.password" class="text-[11px] font-medium text-red-500">
          {{ loginStore.errors.password }}
        </p>
      </div>

      <div class="flex items-center space-x-2 py-1">
        <Checkbox id="remember" v-model:checked="loginStore.form.remember" />
        <label for="remember" class="text-sm font-medium text-slate-600 cursor-pointer select-none">
          Ingat saya di perangkat ini
        </label>
      </div>

      <Button
        type="submit"
        class="w-full h-11 bg-slate-900 hover:bg-slate-800 text-white transition-all shadow-md"
        :disabled="loginStore.isProcessing"
      >
        <Loader2 v-if="loginStore.isProcessing" class="mr-2 h-4 w-4 animate-spin" />
        {{ loginStore.isProcessing ? 'Memproses...' : 'Masuk ke Dashboard' }}
      </Button>
    </form>

    <div class="mt-6 text-center">
      <p class="text-sm text-slate-500">
        Belum memiliki akun?
        <Link href="/register" class="font-semibold text-slate-900 hover:text-slate-700 transition-colors">
          Daftar sekarang
        </Link>
      </p>
    </div>
  </AuthLayout>
</template>
