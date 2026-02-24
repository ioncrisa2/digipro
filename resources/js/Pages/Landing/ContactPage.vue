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
  <div class="landing-shell text-slate-900 bg-[#f7f4ef] min-h-screen flex flex-col selection:bg-slate-900 selection:text-white">
    <LandingNavbar />

    <main class="flex-1 py-12 px-6">
      <div class="max-w-6xl mx-auto space-y-12">
        <section class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white/70 p-8 md:p-12 shadow-sm">
          <div class="absolute -top-16 right-0 h-56 w-56 rounded-full bg-amber-200/40 blur-3xl"></div>
          <div class="absolute -bottom-10 left-0 h-48 w-48 rounded-full bg-sky-200/40 blur-3xl"></div>

          <div class="relative grid grid-cols-1 lg:grid-cols-[1.1fr_0.9fr] gap-10 items-center">
            <div class="space-y-5">
              <div class="inline-flex items-center rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-sm font-medium text-slate-600">
                Hubungi DigiPro
              </div>
              <h1 class="text-3xl md:text-4xl font-semibold tracking-tight text-slate-900">
                Butuh bantuan atau ingin demo langsung?
              </h1>
              <p class="text-lg text-slate-600">
                Tim DigiPro siap menjawab pertanyaan appraisal, akses laporan, atau kerja sama enterprise.
              </p>
              <div class="flex flex-wrap items-center gap-4 text-sm text-slate-600">
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                  Respons cepat tim support
                </div>
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                  Konsultasi penilaian properti
                </div>
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                  Jadwal demo fleksibel
                </div>
              </div>
            </div>

            <div class="relative">
              <div class="rounded-2xl border border-slate-200 bg-white/85 p-6 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Highlight</div>
                <div class="text-lg font-semibold text-slate-900 mt-2">Jam operasional</div>
                <p class="text-sm text-slate-600 mt-2">Senin-Jumat, 08:00-17:00 WIB</p>
                <div class="mt-4 text-sm text-slate-500">Rata-rata respon kurang dari 2 jam kerja.</div>
              </div>
              <img
                src="/images/valuation-hero.svg"
                alt="DigiPro Illustration"
                class="w-full max-w-[320px] mx-auto mt-6 drop-shadow-lg"
              />
            </div>
          </div>
        </section>

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
                      <Input id="name" v-model="form.name" placeholder="Nama Anda" />
                    </div>
                    <div class="space-y-2">
                      <Label for="email">
                        Email <span class="text-red-500">*</span>
                      </Label>
                      <Input id="email" type="email" v-model="form.email" placeholder="nama@email.com" />
                    </div>
                  </div>

                  <div class="space-y-2">
                    <Label for="subject">Subjek</Label>
                    <Input id="subject" v-model="form.subject" placeholder="Contoh: Pertanyaan tentang laporan" />
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
                    />
                  </div>

                  <div class="flex justify-end">
                    <Button type="submit" class="bg-slate-900 hover:bg-slate-800" :disabled="form.processing">
                      <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
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
