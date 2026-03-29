<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
  isSuperAdmin: {
    type: Boolean,
    default: false,
  },
  superAdminWidgets: {
    type: Object,
    default: null,
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

const actionColumns = [
  { key: 'request', label: 'Request', cellClass: 'min-w-[180px]' },
  { key: 'requester_name', label: 'Pemohon', cellClass: 'min-w-[140px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[120px]' },
  { key: 'assets_count', label: 'Aset', cellClass: 'w-[80px]' },
];

const permissionColumns = [
  { key: 'role', label: 'Role', cellClass: 'min-w-[180px]' },
  { key: 'users_count', label: 'User', cellClass: 'w-[80px]' },
  { key: 'sections_count', label: 'Section', cellClass: 'w-[100px]' },
  { key: 'sensitive_access', label: 'Akses Sensitif', cellClass: 'min-w-[220px]' },
  { key: 'updated_at', label: 'Diubah', cellClass: 'min-w-[140px]' },
];

const widgetTone = (tone) => {
  switch (tone) {
    case 'success':
      return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    case 'warning':
      return 'border-amber-200 bg-amber-50 text-amber-900';
    case 'primary':
      return 'border-indigo-200 bg-indigo-50 text-indigo-900';
    case 'info':
      return 'border-sky-200 bg-sky-50 text-sky-900';
    default:
      return 'border-slate-200 bg-slate-50 text-slate-900';
  }
};

const changeTone = (group) => {
  switch (group) {
    case 'Hak Akses':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    case 'Keuangan':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'Pedoman':
    case 'Valuasi':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'User':
      return 'bg-indigo-100 text-indigo-900 border-indigo-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};
</script>

<template>
  <Head title="Admin Dashboard" />

  <AdminLayout title="Admin Dashboard">
    <div class="space-y-6">
      <template v-if="isSuperAdmin && superAdminWidgets">
        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
          <Card class="border-slate-900 bg-slate-950 text-white">
            <CardContent class="flex h-full flex-col justify-between gap-6 p-6">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-300">Super Admin Mode</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight">Kontrol sistem, bukan sekadar operasional harian.</h2>
                <p class="mt-3 max-w-2xl text-sm text-slate-300">
                  Dashboard ini menyorot kesehatan sistem, akses sensitif, kelengkapan referensi, dan perubahan yang layak diawasi langsung.
                </p>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Role Sensitif</p>
                  <p class="mt-3 text-3xl font-semibold">
                    {{ superAdminWidgets.permission_overview?.summary?.roles_with_sensitive_access ?? 0 }}
                  </p>
                  <p class="mt-2 text-sm text-slate-300">Role yang memegang akses seperti keuangan, user, hak akses, atau konten.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Pedoman Aktif</p>
                  <p class="mt-3 text-lg font-semibold">
                    {{ superAdminWidgets.reference_completeness?.active_guideline?.name || 'Belum ada pedoman aktif' }}
                  </p>
                  <p class="mt-2 text-sm text-slate-300">
                    {{ superAdminWidgets.reference_completeness?.active_guideline?.year || '-' }}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Exception Queue</CardTitle>
              <CardDescription>Titik yang perlu diawasi lebih dulu sebelum menjadi bottleneck sistem.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div
                v-for="item in superAdminWidgets.exception_queue"
                :key="item.key"
                class="rounded-2xl border p-4"
                :class="widgetTone(item.tone)"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em]">{{ item.label }}</p>
                    <p class="mt-2 text-3xl font-semibold">{{ item.value }}</p>
                    <p class="mt-2 text-sm opacity-90">{{ item.description }}</p>
                  </div>
                  <Button variant="outline" size="sm" as-child>
                    <Link :href="item.url">Buka</Link>
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </section>

        <section>
          <div class="mb-4 flex items-end justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">System Health Snapshot</p>
              <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Ringkasan kesehatan sistem</h2>
            </div>
          </div>
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="item in superAdminWidgets.system_health" :key="item.key">
              <CardContent class="p-5">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ item.label }}</p>
                    <p class="mt-3 text-4xl font-semibold text-slate-950">{{ item.value }}</p>
                    <p class="mt-2 text-sm text-slate-600">{{ item.description }}</p>
                  </div>
                  <div class="rounded-2xl border px-3 py-1 text-xs font-semibold" :class="widgetTone(item.tone)">
                    Live
                  </div>
                </div>
                <div class="mt-4">
                  <Button variant="link" class="h-auto px-0" as-child>
                    <Link :href="item.url">Lihat detail</Link>
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
          <Card>
            <CardHeader>
              <CardTitle>Ringkasan Role & User</CardTitle>
              <CardDescription>Gambaran cepat struktur akses di sistem saat ini.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="item in superAdminWidgets.role_summary"
                :key="item.key"
                class="rounded-2xl border p-4"
                :class="widgetTone(item.tone)"
              >
                <p class="text-xs font-semibold uppercase tracking-[0.18em]">{{ item.label }}</p>
                <p class="mt-3 text-3xl font-semibold">{{ item.value }}</p>
                <p class="mt-2 text-sm opacity-90">{{ item.description }}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Reference Completeness</CardTitle>
              <CardDescription>
                Status kesiapan referensi untuk pedoman aktif
                <span v-if="superAdminWidgets.reference_completeness?.active_guideline">
                  : {{ superAdminWidgets.reference_completeness.active_guideline.name }}
                </span>
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div
                v-for="item in superAdminWidgets.reference_completeness?.items ?? []"
                :key="item.key"
                class="rounded-2xl border p-4"
              >
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <div class="flex items-center gap-2">
                      <p class="font-medium text-slate-950">{{ item.label }}</p>
                      <Badge variant="outline" :class="widgetTone(item.tone)">{{ item.status_label }}</Badge>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">{{ item.description }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-2xl font-semibold text-slate-950">{{ item.value }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(item.updated_at) }}</p>
                  </div>
                </div>
                <div class="mt-3">
                  <Button variant="link" class="h-auto px-0" as-child>
                    <Link :href="item.url">Kelola data</Link>
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
          <Card>
            <CardHeader class="pb-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <CardTitle>Permission Drift & Menu Access</CardTitle>
                  <CardDescription>Role dengan cakupan akses sensitif dan jumlah section menu sistem yang aktif.</CardDescription>
                </div>
                <div class="text-right text-sm text-slate-500">
                  <p>Total role: {{ superAdminWidgets.permission_overview?.summary?.total_roles ?? 0 }}</p>
                  <p>Akses sensitif: {{ superAdminWidgets.permission_overview?.summary?.roles_with_sensitive_access ?? 0 }}</p>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <AdminDataTable
                :columns="permissionColumns"
                :rows="superAdminWidgets.permission_overview?.rows ?? []"
                empty-text="Belum ada role yang perlu diawasi."
                :default-per-page="8"
              >
                <template #cell-role="{ row }">
                  <Button variant="link" class="h-auto px-0 font-medium" as-child>
                    <Link :href="row.show_url">{{ row.role }}</Link>
                  </Button>
                </template>

                <template #cell-sensitive_access="{ row }">
                  <div class="flex flex-wrap gap-2">
                    <Badge
                      v-for="label in row.sensitive_access"
                      :key="label"
                      variant="outline"
                      class="border-rose-200 bg-rose-50 text-rose-900"
                    >
                      {{ label }}
                    </Badge>
                    <span v-if="!row.sensitive_access.length" class="text-sm text-slate-500">Tidak ada</span>
                  </div>
                </template>

                <template #cell-updated_at="{ row }">
                  {{ formatDateTime(row.updated_at) }}
                </template>
              </AdminDataTable>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Recent Sensitive Changes</CardTitle>
              <CardDescription>Feed perubahan yang layak dipantau karena berpengaruh ke akses atau konfigurasi inti.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div
                v-for="item in superAdminWidgets.sensitive_changes"
                :key="item.key"
                class="rounded-2xl border p-4"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <Badge variant="outline" :class="changeTone(item.group)">{{ item.group }}</Badge>
                    <p class="mt-3 font-medium text-slate-950">{{ item.title }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ item.description }}</p>
                  </div>
                  <span class="whitespace-nowrap text-xs text-slate-500">{{ formatDateTime(item.changed_at) }}</span>
                </div>
                <div class="mt-3">
                  <Button variant="link" class="h-auto px-0" as-child>
                    <Link :href="item.url">Lihat konteks</Link>
                  </Button>
                </div>
              </div>
              <div
                v-if="!(superAdminWidgets.sensitive_changes?.length ?? 0)"
                class="rounded-2xl border border-dashed p-4 text-sm text-slate-500"
              >
                Belum ada perubahan sensitif yang tercatat.
              </div>
            </CardContent>
          </Card>
        </section>
      </template>

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
                <CardDescription>Snapshot request untuk tindak lanjut cepat di workspace admin.</CardDescription>
              </div>
              <Button variant="link" class="h-auto px-0" as-child>
                <Link :href="route('admin.appraisal-requests.index')">Lihat semua</Link>
              </Button>


            </div>
          </CardHeader>
          <CardContent>
            <AdminDataTable
              :columns="actionColumns"
              :rows="actionItems"
              empty-text="Tidak ada request yang butuh tindakan sekarang."
            >
              <template #cell-request="{ row }">
                <Button variant="link" class="h-auto px-0 font-medium" as-child>
                  <Link :href="row.show_url">{{ row.request_number }}</Link>
                </Button>


                <p class="mt-1 text-xs text-slate-500">{{ row.client_name }}</p>
              </template>

              <template #cell-status="{ row }">
                <Badge variant="outline" :class="statusTone(row.status_value)">{{ row.status_label }}</Badge>
              </template>
            </AdminDataTable>
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
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
