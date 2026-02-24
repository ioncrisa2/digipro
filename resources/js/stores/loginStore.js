import {defineStore} from "pinia";
import {computed} from "vue";
import { useForm } from "@inertiajs/vue3";
import { useNotification } from "@/composables/useNotification";

export const useLoginStore = defineStore('login', () => {
  const { notify } = useNotification()

  const form = useForm({
    email: '',
    password: '',
    remember: false,
  })

  const isProcessing = computed(() => form.processing)
  const errors = computed(() => form.errors)

  const handleLogin = () => {
    form.post('/login', {
      preserveScroll: true,
      onSuccess: () => {
        const path = window.location.pathname || ''
        if (path.includes('two-factor-challenge')) {
          notify('info', 'Masukkan kode 2FA untuk melanjutkan.')
        } else {
          notify('success', 'Berhasil Login! Anda diarahkan ke dashboard')
        }
        form.reset('password') // minimal hygiene
      },
      onError: () => {
        notify('error', 'Email or Password is invalid')
      },
    })
  }

  const reset = () => {
    form.reset()
    form.clearErrors()
  }

  return { form, handleLogin, isProcessing, errors, reset }
});
