<script setup>
import { computed, ref, onBeforeMount } from 'vue'
import { Link } from '@inertiajs/vue3'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useRegisterStore } from '@/stores/registerStore'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Loader2, UserPlus, User, Mail, Lock, Eye, EyeOff, ArrowRight, CheckCircle2, XCircle } from 'lucide-vue-next'

import TermsConditionsModal from '@/components/TermsConditionsModal.vue'

const register = useRegisterStore()
const termsOpen = ref(false)
const showPassword = ref(false)

onBeforeMount(() => register.reset())

const startedTyping = computed(() => (register.form.password || '').length > 0)

const passwordRules = computed(() => {
  const p = register.form.password || ''
  return {
    min8: p.length >= 8,
    lower: /[a-z]/.test(p),
    upper: /[A-Z]/.test(p),
    number: /[0-9]/.test(p),
    symbol: /[^A-Za-z0-9]/.test(p),
  }
})

const isStrong = computed(() => Object.values(passwordRules.value).every(Boolean))
const isMatch = computed(() => {
  return !!register.form.password && register.form.password === register.form.password_confirmation
})

const ruleItems = computed(() => [
  { key: 'min8', label: 'Minimal 8 karakter', ok: passwordRules.value.min8 },
  { key: 'upper', label: 'Minimal 1 huruf besar (A-Z)', ok: passwordRules.value.upper },
  { key: 'lower', label: 'Minimal 1 huruf kecil (a-z)', ok: passwordRules.value.lower },
  { key: 'number', label: 'Minimal 1 angka (0-9)', ok: passwordRules.value.number },
  { key: 'symbol', label: 'Minimal 1 karakter spesial (!@#...)', ok: passwordRules.value.symbol },
])

const canSubmit = computed(() => {
  return (
    !!register.form.name &&
    !!register.form.email &&
    !!register.form.password &&
    !!register.form.password_confirmation &&
    !!register.form.terms &&
    isStrong.value &&
    isMatch.value &&
    !register.form.processing
  )
})
</script>

<template>
  <AuthLayout>
    <div class="flex flex-col items-center text-center space-y-2 mb-8">
      <div class="p-3 bg-slate-900/10 text-slate-900 rounded-2xl mb-2">
        <UserPlus class="w-6 h-6" />
      </div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Buat Akun Baru</h1>
      <p class="text-sm text-slate-500 max-w-[300px]">
        Daftar untuk mulai mengajukan penilaian properti secara profesional.
      </p>
    </div>

    <form @submit.prevent="register.handleRegister" class="space-y-5">
      <div class="grid gap-2">
        <Label for="name" class="text-slate-700">Nama Lengkap</Label>
        <div class="relative">
          <User class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <Input id="name" v-model="register.form.name" placeholder="Nama lengkap" class="pl-10 h-11 bg-slate-50 border-slate-200" required />
        </div>
        <p v-if="register.form.errors.name" class="text-[11px] font-medium text-red-500">
          {{ register.form.errors.name }}
        </p>
      </div>

      <div class="grid gap-2">
        <Label for="email" class="text-slate-700">Email</Label>
        <div class="relative">
          <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <Input id="email" type="email" v-model="register.form.email" placeholder="nama@email.com" class="pl-10 h-11 bg-slate-50 border-slate-200" required />
        </div>
        <p v-if="register.form.errors.email" class="text-[11px] font-medium text-red-500">
          {{ register.form.errors.email }}
        </p>
      </div>

      <div class="space-y-5">
        <div class="grid gap-2">
          <Label for="password" class="text-slate-700">Password</Label>
          <div class="relative">
            <Lock class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <Input
              id="password"
              :type="showPassword ? 'text' : 'password'"
              v-model="register.form.password"
              class="pl-10 pr-10 h-11 bg-slate-50 border-slate-200"
              placeholder="********"
              required
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
            >
              <Eye v-if="!showPassword" class="w-4 h-4" />
              <EyeOff v-else class="w-4 h-4" />
            </button>
          </div>

          <div
            v-if="startedTyping"
            class="rounded-xl border border-slate-100 bg-slate-50/70 p-3 text-[12px] text-slate-600"
          >
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div v-for="item in ruleItems" :key="item.key" class="flex items-center gap-2">
                <CheckCircle2 v-if="item.ok" class="w-4 h-4 text-emerald-600" />
                <XCircle v-else class="w-4 h-4 text-slate-400" />
                <span :class="item.ok ? 'text-slate-700' : 'text-slate-500'">{{ item.label }}</span>
              </div>
            </div>
          </div>

          <p v-if="register.form.errors.password" class="text-[11px] font-medium text-red-500">
            {{ register.form.errors.password }}
          </p>
        </div>

        <div class="grid gap-2">
          <Label for="confirm" class="text-slate-700">Konfirmasi Password</Label>
          <Input
            id="confirm"
            :type="showPassword ? 'text' : 'password'"
            v-model="register.form.password_confirmation"
            placeholder="********"
            class="h-11 bg-slate-50 border-slate-200"
            :class="{ 'border-red-500': register.form.password_confirmation && !isMatch }"
            required
          />
          <p v-if="register.form.password_confirmation && !isMatch" class="text-[11px] font-medium text-red-500">
            Konfirmasi password tidak cocok.
          </p>
        </div>
      </div>

      <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/70 space-y-3">
        <div class="flex items-start gap-3">
          <Checkbox id="terms" v-model:checked="register.form.terms" class="mt-1" />
          <label for="terms" class="text-xs leading-relaxed text-slate-600 cursor-pointer">
            Saya telah membaca dan menyetujui
            <button type="button" @click="termsOpen = true" class="text-slate-900 font-semibold hover:underline">
              Syarat dan Ketentuan
            </button>
            yang berlaku di DigiPro by KJPP HJAR.
          </label>
        </div>
        <p v-if="register.form.errors.terms" class="text-[10px] text-red-500 font-medium ml-7">
          {{ register.form.errors.terms }}
        </p>
      </div>

      <TermsConditionsModal :open="termsOpen" @update:open="termsOpen = $event" @agree="register.setTerms(true)" />

      <Button type="submit" class="w-full h-11 bg-slate-900 hover:bg-slate-800 text-white" :disabled="!canSubmit">
        <Loader2 v-if="register.form.processing" class="mr-2 h-4 w-4 animate-spin" />
        {{ register.form.processing ? 'Mendaftarkan...' : 'Daftar Sekarang' }}
        <ArrowRight v-if="!register.form.processing" class="ml-2 w-4 h-4" />
      </Button>
    </form>

    <div class="mt-6 text-center">
      <p class="text-sm text-slate-500">
        Sudah memiliki akun?
        <Link href="/login" class="font-semibold text-slate-900 hover:text-slate-700 transition-colors">
          Masuk di sini
        </Link>
      </p>
    </div>
  </AuthLayout>
</template>
