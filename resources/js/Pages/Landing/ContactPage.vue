<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import LandingNavbar from '@/layouts/LandingNavbar.vue'
import LandingFooter from '@/layouts/LandingFooter.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { MapPin, Phone, Mail, Loader2, Send, CheckCircle2 } from 'lucide-vue-next'
import { useNotification } from '@/composables/useNotification'

const { notify } = useNotification()

const form = useForm({
  name: '',
  email: '',
  subject: '',
  message: '',
})

const submitForm = () => {
  form.post('contact', {
    preserveScroll: true,
    onSuccess: () => {
      notify('success', 'Pesan terkirim. Kami akan membalas secepatnya.')
      form.reset()
    },
    onError: (errors) => {
      if (errors.name || errors.email || errors.message) {
        notify('error', 'Mohon lengkapi data wajib terlebih dahulu.')
      } else {
        notify('error', 'Mohon periksa kembali form Anda.')
      }
    },
  })
}
</script>

<template>
  <div class="landing-shell text-slate-900 min-h-dvh flex flex-col selection:bg-slate-900 selection:text-white">
    <LandingNavbar />

    <main id="content" class="flex-1 py-12 px-6">
      <div class="max-w-6xl mx-auto space-y-12">

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div class="lg:col-span-1 space-y-6">
            <Card class="border-slate-200 bg-white/80 shadow-sm">
              <CardHeader>
                <CardTitle>Informasi Kontak</CardTitle>
                <CardDescription>Hubungi kami melalui kanal berikut.</CardDescription>
              </CardHeader>
              <CardContent class="space-y-8">
                <div class="flex items-start gap-4">
                  <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 text-slate-700">
                    <MapPin class="w-5 h-5" />
                  </div>
                  <div>
                    <h4 class="font-medium text-slate-900">Kantor Utama</h4>
                    <p class="text-slate-500 text-sm mt-1">
                      Jalan Siaran Komplek Ruko Terminal Sako No. 18, Lebong Gajah, Kec. Sematang Borang, Kota Palembang, Sumatera Selatan 30163
                    </p>
                  </div>
                </div>

                <div class="flex items-start gap-4">
                  <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 text-slate-700">
                    <Phone class="w-5 h-5" />
                  </div>
                  <div>
                    <h4 class="font-medium text-slate-900">Telepon dan WhatsApp</h4>
                    <p class="text-slate-500 text-sm mt-1">+62 811 7101066</p>
                    <p class="text-slate-500 text-sm">Senin-Jumat 08:00-17:00 WIB</p>
                  </div>
                </div>

                <div class="flex items-start gap-4">
                  <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 text-slate-700">
                    <Mail class="w-5 h-5" />
                  </div>
                  <div>
                    <h4 class="font-medium text-slate-900">Email</h4>
                    <p class="text-slate-500 text-sm mt-1">henricusja@yahoo.com</p>
                    <p class="text-slate-500 text-sm">support@digipro.tech</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <div class="lg:col-span-2">
            <Card class="border-slate-200 bg-white/80 shadow-sm">
              <CardHeader>
                <CardTitle>Kirim Pesan</CardTitle>
                <CardDescription>Isi formulir berikut dan kami akan segera menghubungi Anda.</CardDescription>
              </CardHeader>
              <CardContent>
                <form @submit.prevent="submitForm" class="space-y-6">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                      <Label for="name">
                        Nama <span class="text-red-500">*</span>
                      </Label>
                      <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Nama Anda"
                        :aria-invalid="form.errors.name ? 'true' : undefined"
                        :aria-describedby="form.errors.name ? 'name-error' : undefined"
                      />
                      <p v-if="form.errors.name" id="name-error" class="text-sm text-red-600">
                        {{ form.errors.name }}
                      </p>
                    </div>
                    <div class="space-y-2">
                      <Label for="email">
                        Email <span class="text-red-500">*</span>
                      </Label>
                      <Input
                        id="email"
                        type="email"
                        v-model="form.email"
                        placeholder="nama@email.com"
                        :aria-invalid="form.errors.email ? 'true' : undefined"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                      />
                      <p v-if="form.errors.email" id="email-error" class="text-sm text-red-600">
                        {{ form.errors.email }}
                      </p>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <Label for="subject">Subjek</Label>
                    <Input
                      id="subject"
                      v-model="form.subject"
                      placeholder="Contoh: Pertanyaan tentang laporan"
                      :aria-invalid="form.errors.subject ? 'true' : undefined"
                      :aria-describedby="form.errors.subject ? 'subject-error' : undefined"
                    />
                    <p v-if="form.errors.subject" id="subject-error" class="text-sm text-red-600">
                      {{ form.errors.subject }}
                    </p>
                  </div>

                  <div class="space-y-2">
                    <Label for="message">
                      Pesan <span class="text-red-500">*</span>
                    </Label>
                    <Textarea
                      id="message"
                      v-model="form.message"
                      placeholder="Tuliskan kebutuhan Anda di sini."
                      rows="5"
                      class="resize-none"
                      :aria-invalid="form.errors.message ? 'true' : undefined"
                      :aria-describedby="form.errors.message ? 'message-error' : undefined"
                    />
                    <p v-if="form.errors.message" id="message-error" class="text-sm text-red-600">
                      {{ form.errors.message }}
                    </p>
                  </div>

                  <div class="flex justify-end">
                    <Button type="submit" class="bg-slate-900 hover:bg-slate-800" :disabled="form.processing">
                      <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin motion-reduce:animate-none" />
                      <Send v-else class="mr-2 h-4 w-4" />
                      Kirim Pesan
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        </section>
      </div>
    </main>

    <LandingFooter />
  </div>
</template>
