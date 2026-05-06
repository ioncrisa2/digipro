<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CircleAlert } from 'lucide-vue-next';

const props = defineProps({
  item: { type: Object, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const token = ref('');

const toneClasses = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  warning: 'border-amber-200 bg-amber-50 text-amber-800',
  danger: 'border-rose-200 bg-rose-50 text-rose-800',
  muted: 'border-slate-200 bg-slate-50 text-slate-700',
};

const badgeClassFor = (tone) => toneClasses[tone] ?? toneClasses.muted;

const sign = () => {
  const value = token.value.trim();
  if (!value || value.length < 6 || props.item.actions?.can_sign !== true) return;

  router.post(props.item.actions.sign_url, { keyla_token: value }, { preserveScroll: true });
};
</script>

<template>
  <Head :title="`Reviewer - Kontrak ${item.request_number}`" />

  <ReviewerLayout :title="`Kontrak ${item.request_number}`">
    <div class="mx-auto max-w-5xl space-y-6">
      <section class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ item.request_number }}</h1>
            <Badge variant="outline" :class="badgeClassFor(item.queue_status?.tone)">
              {{ item.queue_status?.label || 'Menunggu' }}
            </Badge>
          </div>
          <p class="mt-2 text-sm text-slate-600">
            Review singkat sebelum signing: cek status customer, kesiapan Peruri/KEYLA Anda, dan dokumen kontrak yang akan dibubuhi tanda tangan.
          </p>
        </div>
        <Button variant="outline" as-child>
          <Link :href="item.actions.back_url">Kembali ke queue</Link>
        </Button>
      </section>

      <Alert v-if="flash.error" variant="destructive">
        <CircleAlert />
        <AlertTitle>Signing gagal</AlertTitle>
        <AlertDescription>{{ flash.error }}</AlertDescription>
      </Alert>

      <Alert v-else-if="flash.success" class="border-emerald-200 bg-emerald-50 text-emerald-900">
        <CircleAlert />
        <AlertTitle>Signing berhasil</AlertTitle>
        <AlertDescription>{{ flash.success }}</AlertDescription>
      </Alert>

      <section class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <Card class="border-slate-200/80 shadow-sm">
          <CardHeader>
            <CardTitle>Ringkasan Request</CardTitle>
            <CardDescription>Data minimum yang perlu dipastikan sebelum sign.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-5">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-2xl border bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Customer</div>
                <div class="mt-3 text-sm font-medium text-slate-950">{{ item.customer.name }}</div>
                <div class="text-sm text-slate-600">{{ item.customer.email }}</div>
                <div class="mt-2 text-xs text-slate-500">Status: {{ item.customer.status_label }}</div>
                <div class="mt-1 text-xs text-slate-500">Signed at: {{ item.customer.signed_at || '-' }}</div>
              </div>

              <div class="rounded-2xl border bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kontrak</div>
                <div class="mt-3 text-sm font-medium text-slate-950">{{ item.contract_number || '-' }}</div>
                <div class="mt-2 text-xs text-slate-500">Klien: {{ item.client_name }}</div>
                <div class="mt-1 text-xs text-slate-500">Envelope: {{ item.envelope.external_envelope_id || '-' }}</div>
              </div>
            </div>

            <div class="rounded-2xl border p-4">
              <div class="flex flex-wrap items-center gap-2">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kesiapan Penilai Publik</div>
                <Badge variant="outline" :class="badgeClassFor(item.public_appraiser?.readiness?.overall?.tone)">
                  {{ item.public_appraiser?.readiness?.overall?.label || 'Belum Diketahui' }}
                </Badge>
              </div>

              <div class="mt-4 flex flex-wrap gap-2">
                <Badge variant="outline" :class="badgeClassFor(item.public_appraiser?.readiness?.certificate?.tone)">
                  Sertifikat: {{ item.public_appraiser?.readiness?.certificate?.label || 'Belum Diketahui' }}
                </Badge>
                <Badge variant="outline" :class="badgeClassFor(item.public_appraiser?.readiness?.keyla?.tone)">
                  KEYLA: {{ item.public_appraiser?.readiness?.keyla?.label || 'Belum Diketahui' }}
                </Badge>
              </div>

              <p class="mt-3 text-sm text-slate-600">
                {{ item.public_appraiser?.readiness?.overall?.message || 'Status signer belum tersedia.' }}
              </p>
            </div>

            <div class="rounded-2xl border p-4">
              <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Dokumen</div>
              <div class="mt-4 flex flex-wrap gap-2">
                <Button v-if="item.documents.original_pdf_url" variant="outline" size="sm" as-child>
                  <a :href="item.documents.original_pdf_url" target="_blank" rel="noopener noreferrer">Buka PDF Unsigned</a>
                </Button>
                <Button v-if="item.documents.signed_pdf_url" variant="outline" size="sm" as-child>
                  <a :href="item.documents.signed_pdf_url" target="_blank" rel="noopener noreferrer">Buka PDF Signed</a>
                </Button>
              </div>
              <p class="mt-3 text-xs text-slate-500">
                Jika posisi tanda tangan perlu dicek, buka PDF unsigned terlebih dahulu sebelum memasukkan token KEYLA.
              </p>
            </div>

            <div v-if="item.envelope.last_error" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <div class="font-medium">Error terakhir dari flow signing</div>
              <div class="mt-2">{{ item.envelope.last_error }}</div>
            </div>
          </CardContent>
        </Card>

        <Card class="border-slate-200/80 shadow-sm">
          <CardHeader>
            <CardTitle>Aksi Sign</CardTitle>
            <CardDescription>Gunakan token KEYLA milik akun penilai publik yang sedang login.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="rounded-2xl border bg-slate-50 p-4">
              <div class="text-sm font-medium text-slate-950">{{ item.public_appraiser.name }}</div>
              <div class="text-sm text-slate-600">{{ item.public_appraiser.email || '-' }}</div>
              <div class="mt-1 text-xs text-slate-500">Status sign: {{ item.public_appraiser.status_label }}</div>
              <div class="mt-1 text-xs text-slate-500">Signed at: {{ item.public_appraiser.signed_at || '-' }}</div>
            </div>

            <div class="space-y-2">
              <Label for="keyla_token">Token KEYLA</Label>
              <Input id="keyla_token" v-model="token" placeholder="Masukkan token KEYLA aktif" autocomplete="one-time-code" />
            </div>

            <Button class="w-full" :disabled="item.actions.can_sign !== true || token.trim().length < 6" @click="sign">
              Sign Kontrak Sekarang
            </Button>

            <p class="text-xs text-slate-500">
              Untuk mode KEYLA, otorisasi dilakukan dengan token aplikasi user. Tidak ada signing yang diwakilkan oleh admin.
            </p>
          </CardContent>
        </Card>
      </section>
    </div>
  </ReviewerLayout>
</template>
