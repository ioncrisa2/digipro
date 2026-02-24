<script setup>
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Eye, EyeOff, LockKeyhole, ArrowLeft, CheckCircle2, XCircle } from 'lucide-vue-next'

const props = defineProps({
  token: String,
  email: String,
})

const showPassword = ref(false)

const form = useForm({
  token: props.token,
  email: props.email || '',
  password: '',
  password_confirmation: '',
})

const rules = computed(() => {
  const p = form.password || ''
  return {
    min8: p.length >= 8,
    lower: /[a-z]/.test(p),
    upper: /[A-Z]/.test(p),
    number: /[0-9]/.test(p),
    symbol: /[^A-Za-z0-9]/.test(p),
  }
})

const isStrong = computed(() => Object.values(rules.value).every(Boolean))
const isMatch = computed(() => form.password && form.password === form.password_confirmation)

const startedTyping = computed(() => (form.password || '').length > 0)

const canSubmit = computed(() => {
  return !form.processing && isStrong.value && isMatch.value
})

const ruleItems = computed(() => [
  { key: 'min8', label: 'Minimal 8 karakter', ok: rules.value.min8 },
  { key: 'upper', label: 'Minimal 1 huruf besar (A-Z)', ok: rules.value.upper },
  { key: 'lower', label: 'Minimal 1 huruf kecil (a-z)', ok: rules.value.lower },
  { key: 'number', label: 'Minimal 1 angka (0-9)', ok: rules.value.number },
  { key: 'symbol', label: 'Minimal 1 karakter spesial (!@#...)', ok: rules.value.symbol },
])

const submit = () => {
  form.post('/reset-password', {
    preserveScroll: true,
    onSuccess: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <AuthLayout>
    <div class="flex flex-col items-center text-center space-y-2 mb-8">
      <div class="p-3 bg-slate-900/10 text-slate-900 rounded-2xl mb-2">
        <LockKeyhole class="w-6 h-6" />
      </div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Setel Ulang Password</h1>
      <p class="text-sm text-slate-500 max-w-[280px]">
        Gunakan password yang kuat agar akun tetap aman.
      </p>
    </div>

    <form @submit.prevent="submit" class="space-y-5">
      <div class="p-3 rounded-lg border border-slate-100 bg-slate-50/70 flex items-center gap-3">
        <div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
        <div class="text-xs">
          <span class="text-slate-500 block">Akun</span>
          <span class="font-medium text-slate-700">{{ form.email }}</span>
        </div>
      </div>

      <div class="grid gap-2">
        <Label for="password">Password Baru</Label>

        <div class="relative">
          <Input
            id="password"
            :type="showPassword ? 'text' : 'password'"
            v-model="form.password"
            placeholder="********"
            class="pr-10 h-11 bg-slate-50 border-slate-200"
            :class="{
              'border-red-500': form.errors.password || (startedTyping && !isStrong),
            }"
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

        <div v-if="startedTyping" class="rounded-lg border border-slate-100 bg-slate-50/70 p-3 text-[12px] text-slate-600">
          <div class="grid sm:grid-cols-2 gap-2">
            <div v-for="item in ruleItems" :key="item.key" class="flex items-center gap-2">
              <CheckCircle2 v-if="item.ok" class="w-4 h-4 text-emerald-600" />
              <XCircle v-else class="w-4 h-4 text-slate-400" />
              <span :class="item.ok ? 'text-slate-700' : 'text-slate-500'">
                {{ item.label }}
              </span>
            </div>
          </div>
        </div>

        <p v-if="form.errors.password" class="text-[11px] font-medium text-red-500">
          {{ form.errors.password }}
        </p>
      </div>

      <div class="grid gap-2">
        <Label for="password_confirmation">Konfirmasi Password Baru</Label>

        <Input
          id="password_confirmation"
          :type="showPassword ? 'text' : 'password'"
          v-model="form.password_confirmation"
          placeholder="********"
          class="h-11 bg-slate-50 border-slate-200"
          :class="{
            'border-red-500': form.password_confirmation && !isMatch,
          }"
          required
        />

        <p v-if="form.password_confirmation && !isMatch" class="text-[11px] font-medium text-red-500">
          Konfirmasi password tidak cocok.
        </p>
      </div>

      <Button type="submit" class="w-full h-11 bg-slate-900 hover:bg-slate-800 text-white" :disabled="!canSubmit">
        <span v-if="form.processing">Memperbarui...</span>
        <span v-else>Simpan Password Baru</span>
      </Button>

      <div class="pt-2">
        <Link href="/login" class="flex items-center justify-center gap-2 text-sm text-slate-500 hover:text-slate-900">
          <ArrowLeft class="w-4 h-4" />
          Kembali ke Login
        </Link>
      </div>
    </form>
  </AuthLayout>
</template>
