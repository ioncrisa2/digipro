<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatCurrency, formatDateTime } from '@/utils/reviewer';

defineProps({
  stats: {
    type: Array,
    default: () => [],
  },
  actionItems: {
    type: Array,
    default: () => [],
  },
  paymentQueue: {
    type: Array,
    default: () => [],
  },
  modules: {
    type: Array,
    default: () => [],
  },
  legacyPanelUrl: {
    type: String,
    default: '/legacy-admin',
  },
});

const statTone = (tone) => {
  switch (tone) {
    case 'success':
      return 'bg-emerald-100 text-emerald-900';
    case 'warning':
      return 'bg-amber-100 text-amber-900';
    case 'primary':
      return 'bg-indigo-100 text-indigo-900';
    case 'info':
      return 'bg-sky-100 text-sky-900';
    default:
      return 'bg-slate-100 text-slate-900';
  }
};

const statusTone = (value) => {
  switch (value) {
    case 'submitted':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'docs_incomplete':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    case 'verified':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'waiting_offer':
    case 'waiting_signature':
    case 'contract_signed':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'offer_sent':
      return 'bg-indigo-100 text-indigo-900 border-indigo-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};

const moduleTone = (status) => {
  switch (status) {
    case 'in_progress':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'bridge':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};
</script>

<template>
  <Head title="Admin Dashboard" />

  <AdminLayout title="Admin Dashboard">
    <div class="space-y-6">
      <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Migrasi Admin</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
              Panel admin sudah dipindah ke Vue, Filament tetap hidup sebagai legacy bridge.
            </h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
              Dashboard ini menggantikan entrypoint lama. Resource yang belum dipindah tetap bisa dibuka melalui legacy panel sampai modul Vue-nya selesai.
            </p>
          </div>
          <Button as-child>
            <a :href="legacyPanelUrl">Buka di Legacy Admin</a>
          </Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card v-for="item in stats" :key="item.key">
          <CardContent class="p-5">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ item.label }}</p>
                <p class="mt-3 text-4xl font-semibold text-slate-950">{{ item.value }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ item.description }}</p>
              </div>
              <div class="rounded-2xl px-3 py-1 text-xs font-semibold" :class="statTone(item.tone)">
                Live
              </div>
            </div>
          </CardContent>
        </Card>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.25fr_1fr]">
        <Card>
          <CardHeader class="pb-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <CardTitle>Permohonan Perlu Tindakan</CardTitle>
                <CardDescription>Snapshot request yang sebelumnya muncul di widget Filament utama.</CardDescription>
              </div>
              <Button variant="link" class="h-auto px-0" as-child>
                <Link :href="route('admin.appraisal-requests.index')">Lihat semua</Link>
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div class="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Request</TableHead>
                    <TableHead>Pemohon</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Aset</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="item in actionItems" :key="item.id">
                    <TableCell>
                      <Button variant="link" class="h-auto px-0 font-medium" as-child>
                        <Link :href="item.show_url">{{ item.request_number }}</Link>
                      </Button>
                      <p class="mt-1 text-xs text-slate-500">{{ item.client_name }}</p>
                    </TableCell>
                    <TableCell>{{ item.requester_name }}</TableCell>
                    <TableCell>
                      <Badge variant="outline" :class="statusTone(item.status_value)">{{ item.status_label }}</Badge>
                    </TableCell>
                    <TableCell>{{ item.assets_count }}</TableCell>
                  </TableRow>
                  <TableRow v-if="!actionItems.length">
                    <TableCell :colspan="4" class="text-center text-slate-500">Tidak ada request yang butuh tindakan sekarang.</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>

        <div class="space-y-6">
          <Card>
            <CardHeader class="pb-4">
              <CardTitle>Menunggu Pembayaran</CardTitle>
              <CardDescription>Antrean kontrak yang sudah ditandatangani tetapi belum diproses lebih lanjut.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-for="item in paymentQueue" :key="item.id" class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <Button variant="link" class="h-auto px-0 font-medium" as-child>
                      <Link :href="item.show_url">{{ item.request_number }}</Link>
                    </Button>
                    <p class="mt-1 text-xs text-slate-500">{{ item.requester_name }}</p>
                  </div>
                  <Badge variant="outline" class="bg-amber-100 text-amber-900 border-amber-200">
                    {{ item.offer_validity_days ? `${item.offer_validity_days} hari` : 'Belum diisi' }}
                  </Badge>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 text-sm text-slate-600">
                  <span>{{ formatCurrency(item.fee_total) }}</span>
                  <span>{{ formatDateTime(item.updated_at) }}</span>
                </div>
              </div>
              <div v-if="!paymentQueue.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Tidak ada antrean pembayaran.
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="pb-4">
              <CardTitle>Peta Migrasi Modul</CardTitle>
              <CardDescription>Inventaris admin Filament yang masih harus dipindah ke Vue.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-for="module in modules" :key="module.slug" class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="font-medium text-slate-950">{{ module.title }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ module.description }}</p>
                  </div>
                  <Badge variant="outline" :class="moduleTone(module.status)">{{ module.status_label }}</Badge>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 text-sm text-slate-500">
                  <span>{{ module.resource_count }} resource legacy</span>
                  <Button variant="link" class="h-auto px-0" as-child>
                    <Link :href="module.show_url">Lihat detail</Link>
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
