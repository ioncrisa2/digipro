<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'

import AuthLayout from '@/layouts/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Loader2, ShieldCheck, KeyRound, RefreshCw } from 'lucide-vue-next'

const props = defineProps({
  email: { type: String, default: '' },
})

const useRecoveryCode = ref(false)
const form = useForm({
  code: '',
  recovery_code: '',
})

const submit = () => {
  form.post(route('two-factor.login.store'), {
    preserveScroll: true,
  })
}

const errorMessage = computed(() => {
  return form.errors.code || form.errors.recovery_code || ''
})
</script>

<template>
  <AuthLayout>
    <div class="flex flex-col items-center text-center space-y-2 mb-8">
      <div class="p-3 bg-slate-900 text-white rounded-2xl mb-2 shadow-lg shadow-slate-200">
        <ShieldCheck class="w-6 h-6" />
      </div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Verifikasi 2FA</h1>
      <p class="text-sm text-slate-500">Masukkan kode dari aplikasi autentikator Anda.</p>
      <p v-if="email" class="text-xs text-slate-400">Akun: {{ email }}</p>
    </div>

    <form @submit.prevent="submit" class="space-y-5">
      <div v-if="!useRecoveryCode" class="grid gap-2">
        <Label for="code" class="text-slate-700">Kode 6 Digit</Label>
        <div class="relative">
          <KeyRound class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <Input
            id="code"
            inputmode="numeric"
            maxlength="6"
            placeholder="123456"
            v-model="form.code"
            class="pl-10 h-11 bg-slate-50 border-slate-200"
            autocomplete="one-time-code"
            required
          />
        </div>
        <p v-if="form.errors.code" class="text-[11px] font-medium text-red-500">
          {{ form.errors.code }}
        </p>
      </div>

      <div v-else class="grid gap-2">
        <Label for="recovery_code" class="text-slate-700">Recovery Code</Label>
        <Input
          id="recovery_code"
          v-model="form.recovery_code"
          placeholder="ABCD-EFGH-IJKL"
          class="h-11 bg-slate-50 border-slate-200"
          autocomplete="one-time-code"
          required
        />
        <p v-if="form.errors.recovery_code" class="text-[11px] font-medium text-red-500">
          {{ form.errors.recovery_code }}
        </p>
      </div>

      <div v-if="errorMessage && !form.errors.code && !form.errors.recovery_code" class="text-[11px] text-red-500">
        {{ errorMessage }}
      </div>

      <Button
        type="submit"
        class="w-full h-11 bg-slate-900 hover:bg-slate-800 text-white transition-all shadow-md"
        :disabled="form.processing"
      >
        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
        Verifikasi
      </Button>

      <div class="text-xs text-center text-slate-500">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-slate-900 font-semibold hover:text-slate-700"
          @click="useRecoveryCode = !useRecoveryCode"
        >
          <RefreshCw class="w-4 h-4" />
          {{ useRecoveryCode ? 'Gunakan kode 6 digit' : 'Gunakan recovery code' }}
        </button>
      </div>
    </form>

    <div class="mt-4 text-center">
      <Link href="/login" class="text-sm text-slate-500 hover:text-slate-900">Kembali ke login</Link>
    </div>
  </AuthLayout>
</template>
