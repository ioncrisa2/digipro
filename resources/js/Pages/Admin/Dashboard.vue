<script setup>
import { computed } from 'vue';
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

const props = defineProps({
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

const superAdminSummary = computed(() => {
  if (!props.superAdminWidgets) return [];

  const permissionSummary = props.superAdminWidgets.permission_overview?.summary ?? {};
  const guideline = props.superAdminWidgets.reference_completeness?.active_guideline ?? null;
  const exceptionQueue = props.superAdminWidgets.exception_queue ?? [];

  return [
    {
      key: 'roles-sensitive',
      label: 'Role sensitif',
      value: permissionSummary.roles_with_sensitive_access ?? 0,
      helper: 'Perlu pengawasan akses',
      tone: 'warning',
    },
    {
      key: 'total-roles',
      label: 'Total role',
      value: permissionSummary.total_roles ?? 0,
      helper: 'Struktur izin aktif',
      tone: 'info',
    },
    {
      key: 'active-guideline',
      label: 'Pedoman aktif',
      value: guideline?.year || '-',
      helper: guideline?.name || 'Belum ada pedoman aktif',
      tone: 'primary',
    },
    {
      key: 'exception-count',
      label: 'Exception queue',
      value: exceptionQueue.length,
      helper: 'Titik perlu respons cepat',
      tone: 'danger',
    },
  ];
});

const exceptionQueue = computed(() => props.superAdminWidgets?.exception_queue ?? []);
const systemHealth = computed(() => props.superAdminWidgets?.system_health ?? []);
const roleSummary = computed(() => props.superAdminWidgets?.role_summary ?? []);
const referenceItems = computed(() => props.superAdminWidgets?.reference_completeness?.items ?? []);
const permissionRows = computed(() => props.superAdminWidgets?.permission_overview?.rows ?? []);
const permissionSummary = computed(() => props.superAdminWidgets?.permission_overview?.summary ?? {});
const sensitiveChanges = computed(() => props.superAdminWidgets?.sensitive_changes ?? []);

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

const summaryTone = (tone) => {
  switch (tone) {
    case 'success':
      return 'border-emerald-200 bg-emerald-50 text-emerald-950';
    case 'warning':
      return 'border-amber-200 bg-amber-50 text-amber-950';
    case 'primary':
      return 'border-indigo-200 bg-indigo-50 text-indigo-950';
    case 'info':
      return 'border-sky-200 bg-sky-50 text-sky-950';
    case 'danger':
      return 'border-rose-200 bg-rose-50 text-rose-950';
    default:
      return 'border-slate-200 bg-slate-50 text-slate-950';
  }
};
</script>

<template>
  <Head title="Admin Dashboard" />

  <AdminLayout title="Admin Dashboard">
    <div class="space-y-6">
      <template v-if="isSuperAdmin && props.superAdminWidgets">
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(360px,0.9fr)]">
          <Card class="border-slate-900 bg-slate-950 text-white">
            <CardContent class="space-y-6 p-6">
              <div class="space-y-3">
                <p class="text-xs font-semibold uppercase text-slate-300">Super Admin Mode</p>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                  <div class="space-y-2">
                    <h2 class="text-balance text-3xl font-semibold">Kontrol sistem dalam satu bidang pandang.</h2>
                    <p class="max-w-3xl text-pretty text-sm text-slate-300">
                      Semua indikator tetap ditampilkan, tetapi dipadatkan agar akses sensitif, kesehatan sistem, referensi, dan exception queue bisa discan lebih cepat.
                    </p>
                  </div>
                  <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
                    <div>Total role: {{ permissionSummary.total_roles ?? 0 }}</div>
                    <div>Akses sensitif: {{ permissionSummary.roles_with_sensitive_access ?? 0 }}</div>
                  </div>
                </div>
              </div>

              <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div
                  v-for="item in superAdminSummary"
                  :key="item.key"
                  class="rounded-2xl border px-4 py-4"
                  :class="summaryTone(item.tone)"
                >
                  <p class="text-[11px] font-semibold uppercase opacity-80">{{ item.label }}</p>
                  <p class="mt-2 text-3xl font-semibold tabular-nums">{{ item.value }}</p>
                  <p class="mt-2 text-sm opacity-90">{{ item.helper }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="pb-4">
              <CardTitle>Exception Queue</CardTitle>
              <CardDescription>Titik yang perlu diawasi lebih dulu sebelum menjadi bottleneck sistem.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div
                v-for="item in exceptionQueue"
                :key="item.key"
                class="rounded-2xl border px-4 py-4"
                :class="widgetTone(item.tone)"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase">{{ item.label }}</p>
                    <div class="mt-2 flex items-baseline gap-3">
                      <p class="text-3xl font-semibold tabular-nums">{{ item.value }}</p>
                      <p class="text-sm opacity-90">{{ item.description }}</p>
                    </div>
                  </div>
                  <Button variant="outline" size="sm" as-child>
                    <Link :href="item.url">Buka</Link>
                  </Button>
                </div>
              </div>
              <div
                v-if="!exceptionQueue.length"
                class="rounded-2xl border border-dashed p-4 text-sm text-slate-500"
              >
                Tidak ada exception queue yang aktif.
              </div>
            </CardContent>
          </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.95fr)]">
          <Card>
            <CardHeader class="pb-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="text-xs font-semibold uppercase text-slate-500">System Health Snapshot</p>
                  <CardTitle class="mt-2">Ringkasan kesehatan sistem</CardTitle>
                </div>
              </div>
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="item in systemHealth"
                :key="item.key"
                class="rounded-2xl border p-4"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-[11px] font-semibold uppercase text-slate-500">{{ item.label }}</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-slate-950">{{ item.value }}</p>
                  </div>
                  <div class="rounded-full border px-3 py-1 text-[11px] font-semibold" :class="widgetTone(item.tone)">
                    Live
                  </div>
                </div>
                <p class="mt-3 text-pretty text-sm text-slate-600">{{ item.description }}</p>
                <Button variant="link" class="mt-2 h-auto px-0" as-child>
                  <Link :href="item.url">Lihat detail</Link>
                </Button>
              </div>
            </CardContent>
          </Card>

          <div class="space-y-4">
            <Card>
              <CardHeader class="pb-4">
                <CardTitle>Ringkasan Role & User</CardTitle>
                <CardDescription>Gambaran cepat struktur akses di sistem saat ini.</CardDescription>
              </CardHeader>
              <CardContent class="grid gap-3 sm:grid-cols-2">
                <div
                  v-for="item in roleSummary"
                  :key="item.key"
                  class="rounded-2xl border px-4 py-4"
                  :class="widgetTone(item.tone)"
                >
                  <p class="text-[11px] font-semibold uppercase opacity-80">{{ item.label }}</p>
                  <p class="mt-2 text-3xl font-semibold tabular-nums">{{ item.value }}</p>
                  <p class="mt-2 text-sm opacity-90">{{ item.description }}</p>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader class="pb-4">
                <CardTitle>Reference Completeness</CardTitle>
                <CardDescription>
                  Status kesiapan referensi untuk pedoman aktif
                  <span v-if="props.superAdminWidgets.reference_completeness?.active_guideline">
                    : {{ props.superAdminWidgets.reference_completeness.active_guideline.name }}
                  </span>
                </CardDescription>
              </CardHeader>
              <CardContent class="space-y-3">
                <div
                  v-for="item in referenceItems"
                  :key="item.key"
                  class="rounded-2xl border px-4 py-4"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium text-slate-950">{{ item.label }}</p>
                        <Badge variant="outline" :class="widgetTone(item.tone)">{{ item.status_label }}</Badge>
                      </div>
                      <p class="mt-1 text-sm text-slate-600">{{ item.description }}</p>
                    </div>
                    <div class="text-right">
                      <p class="text-2xl font-semibold tabular-nums text-slate-950">{{ item.value }}</p>
                      <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(item.updated_at) }}</p>
                    </div>
                  </div>
                  <Button variant="link" class="mt-2 h-auto px-0" as-child>
                    <Link :href="item.url">Kelola data</Link>
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>
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
                :rows="permissionRows"
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
                v-for="item in sensitiveChanges"
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
                v-if="!sensitiveChanges.length"
                class="rounded-2xl border border-dashed p-4 text-sm text-slate-500"
              >
                Belum ada perubahan sensitif yang tercatat.
              </div>
            </CardContent>
          </Card>
        </section>
      </template>

      <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.9fr)]">
        <Card class="border-slate-900 bg-slate-950 text-white">
          <CardContent class="space-y-6 p-6">
            <div class="space-y-3">
              <p class="text-xs font-semibold uppercase text-slate-300">Admin Workspace</p>
              <h2 class="text-balance text-3xl font-semibold">Ringkasan operasional admin dalam satu tampilan.</h2>
              <p class="max-w-3xl text-pretty text-sm text-slate-300">
                Fokus utamanya ada pada permohonan yang perlu tindakan, antrean pembayaran, dan indikator live yang paling relevan untuk admin harian.
              </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <div
                v-for="item in stats"
                :key="item.key"
                class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-[11px] font-semibold uppercase text-slate-300">{{ item.label }}</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-white">{{ item.value }}</p>
                  </div>
                  <div class="rounded-full px-3 py-1 text-[11px] font-semibold" :class="statTone(item.tone)">
                    Live
                  </div>
                </div>
                <p class="mt-3 text-sm text-slate-300">{{ item.description }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-4">
            <CardTitle>Menunggu Pembayaran</CardTitle>
            <CardDescription>Antrean kontrak yang sudah ditandatangani tetapi belum diproses lebih lanjut.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-for="item in paymentQueue" :key="item.id" class="rounded-2xl border p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <Button variant="link" class="h-auto px-0 font-medium" as-child>
                    <Link :href="item.show_url">{{ item.request_number }}</Link>
                  </Button>
                  <p class="mt-1 text-xs text-slate-500">{{ item.requester_name }}</p>
                </div>
                <Badge variant="outline" class="border-amber-200 bg-amber-100 text-amber-900">
                  {{ item.offer_validity_days ? `${item.offer_validity_days} hari` : 'Belum diisi' }}
                </Badge>
              </div>
              <div class="mt-3 flex items-center justify-between gap-3 text-sm text-slate-600">
                <span class="tabular-nums">{{ formatCurrency(item.fee_total) }}</span>
                <span>{{ formatDateTime(item.updated_at) }}</span>
              </div>
            </div>
            <div v-if="!paymentQueue.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
              Tidak ada antrean pembayaran.
            </div>
          </CardContent>
        </Card>
      </section>

      <section class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(360px,0.9fr)]">
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
              <CardTitle>Ringkasan Tindak Lanjut</CardTitle>
              <CardDescription>Panel cepat untuk melihat beban kerja admin tanpa membuka modul satu per satu.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div
                v-for="item in stats"
                :key="`regular-${item.key}`"
                class="rounded-2xl border p-4"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-[11px] font-semibold uppercase text-slate-500">{{ item.label }}</p>
                    <p class="mt-2 text-3xl font-semibold tabular-nums text-slate-950">{{ item.value }}</p>
                    <p class="mt-2 text-sm text-slate-600">{{ item.description }}</p>
                  </div>
                  <div class="rounded-full px-3 py-1 text-[11px] font-semibold" :class="statTone(item.tone)">
                    Live
                  </div>
                </div>
              </div>
              <div v-if="!stats.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Belum ada ringkasan operasional untuk ditampilkan.
              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
