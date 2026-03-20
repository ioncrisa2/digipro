<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { formatArea, formatCurrency, formatDateTime, formatNumber } from '@/utils/reviewer';

defineProps({
  record: {
    type: Object,
    required: true,
  },
  requester: {
    type: Object,
    required: true,
  },
  requestFiles: {
    type: Array,
    default: () => [],
  },
  assets: {
    type: Array,
    default: () => [],
  },
  payments: {
    type: Array,
    default: () => [],
  },
  negotiations: {
    type: Array,
    default: () => [],
  },
  legacyPanelUrl: {
    type: String,
    default: '/legacy-admin',
  },
});

const statusTone = (value) => {
  switch (value) {
    case 'submitted':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'docs_incomplete':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    case 'verified':
    case 'completed':
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

const paymentStatusLabel = (status) => {
  switch (status) {
    case 'paid':
      return 'Dibayar';
    case 'pending':
      return 'Menunggu';
    case 'failed':
      return 'Gagal';
    case 'expired':
      return 'Kedaluwarsa';
    case 'rejected':
      return 'Ditolak';
    case 'refunded':
      return 'Refund';
    default:
      return status || '-';
  }
};
</script>

<template>
  <Head :title="`Admin - ${record.request_number}`" />

  <AdminLayout :title="record.request_number">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Request Detail</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.request_number }}</h1>
          <div class="mt-3 flex flex-wrap gap-2">
            <Badge variant="outline" :class="statusTone(record.status_value)">{{ record.status_label }}</Badge>
            <Badge variant="outline" :class="statusTone(record.contract_status_value)">{{ record.contract_status_label }}</Badge>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child>
            <Link :href="route('admin.appraisal-requests.index')">Kembali ke daftar</Link>
          </Button>
          <Button v-if="record.legacy_url" as-child>
            <a :href="record.legacy_url">Buka di Legacy Admin</a>
          </Button>
          <Button v-else variant="outline" as-child>
            <a :href="legacyPanelUrl">Buka Legacy Admin</a>
          </Button>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Ringkasan Permohonan</CardTitle>
              <CardDescription>Data inti request yang sebelumnya tersebar di form dan infolist Filament.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tujuan</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.purpose_label }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Jenis Laporan</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.report_type_label }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guideline</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.guideline_set }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tanggal Request</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.requested_at) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tanggal Verifikasi</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.verified_at) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Klien</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.client_name }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nomor Kontrak</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.contract_number }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tanggal Kontrak</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatDateTime(record.contract_date) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Durasi Valuasi</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.valuation_duration_days ? `${record.valuation_duration_days} hari` : '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Masa Berlaku Offer</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.offer_validity_days ? `${record.offer_validity_days} hari` : '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Fee</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatCurrency(record.fee_total) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Skema DP</p>
                <p class="mt-2 text-sm text-slate-900">
                  {{ record.fee_has_dp ? `Ya, ${record.fee_dp_percent ?? 0}%` : 'Tidak ada DP' }}
                </p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Harapan Fee Terakhir</p>
                <p class="mt-2 text-sm text-slate-900">{{ formatCurrency(record.latest_expected_fee) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Catatan Negosiasi Terakhir</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.latest_negotiation_reason || '-' }}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Dokumen Request</CardTitle>
              <CardDescription>File level request seperti kontrak bertanda tangan dan lampiran global.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-for="file in requestFiles" :key="file.id" class="rounded-2xl border p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <p class="font-medium text-slate-950">{{ file.original_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ file.type_label }} - {{ file.size_label }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ file.mime || '-' }} - {{ formatDateTime(file.created_at) }}</p>
                  </div>
                  <Button variant="outline" size="sm" as-child>
                    <a :href="file.url" target="_blank" rel="noreferrer">Buka File</a>
                  </Button>
                </div>
              </div>
              <div v-if="!requestFiles.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Belum ada dokumen request.
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Aset Terkait</CardTitle>
              <CardDescription>Ringkasan aset, metadata properti, dokumen, dan foto yang sebelumnya tersebar di relation manager Filament.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div v-for="asset in assets" :key="asset.id" class="rounded-3xl border p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aset #{{ asset.order }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ asset.address }}</h3>
                    <p class="mt-2 text-sm text-slate-600">
                      {{ asset.asset_type_label }}
                      <span v-if="asset.peruntukan_label">- {{ asset.peruntukan_label }}</span>
                      <span v-if="asset.asset_code">- Kode: {{ asset.asset_code }}</span>
                    </p>
                  </div>
                  <div class="grid gap-2 text-right text-sm text-slate-600">
                    <p>Range nilai: {{ formatCurrency(asset.estimated_value_low) }} - {{ formatCurrency(asset.estimated_value_high) }}</p>
                    <p>Market value: {{ formatCurrency(asset.market_value_final) }}</p>
                  </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Lokasi</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>{{ asset.village_name || '-' }}</p>
                      <p>{{ asset.district_name || '-' }}</p>
                      <p>{{ asset.regency_name || '-' }}</p>
                      <p>{{ asset.province_name || '-' }}</p>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Karakteristik Tanah</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>Dokumen: {{ asset.title_document_label || '-' }}</p>
                      <p>Bentuk: {{ asset.land_shape_label || '-' }}</p>
                      <p>Posisi: {{ asset.land_position_label || '-' }}</p>
                      <p>Kondisi: {{ asset.land_condition_label || '-' }}</p>
                      <p>Topografi: {{ asset.topography_label || '-' }}</p>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Ukuran dan Bangunan</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>Luas tanah: {{ formatArea(asset.land_area) }}</p>
                      <p>Luas bangunan: {{ formatArea(asset.building_area) }}</p>
                      <p>Lantai: {{ asset.building_floors ?? '-' }}</p>
                      <p>Tahun bangun: {{ asset.build_year ?? '-' }}</p>
                      <p>Tahun renovasi: {{ asset.renovation_year ?? '-' }}</p>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Akses</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>Lebar muka: {{ asset.frontage_width ? `${formatNumber(asset.frontage_width, 2)} m` : '-' }}</p>
                      <p>Lebar jalan: {{ asset.access_road_width ? `${formatNumber(asset.access_road_width, 2)} m` : '-' }}</p>
                      <p>Latitude: {{ asset.coordinates_lat ?? '-' }}</p>
                      <p>Longitude: {{ asset.coordinates_lng ?? '-' }}</p>
                    </div>
                    <div class="mt-3" v-if="asset.maps_link">
                      <Button variant="outline" size="sm" as-child>
                        <a :href="asset.maps_link" target="_blank" rel="noreferrer">Buka Maps</a>
                      </Button>
                    </div>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Final</p>
                    <div class="mt-2 space-y-1 text-sm text-slate-700">
                      <p>Nilai tanah: {{ formatCurrency(asset.land_value_final) }}</p>
                      <p>Nilai bangunan: {{ formatCurrency(asset.building_value_final) }}</p>
                      <p>Nilai pasar: {{ formatCurrency(asset.market_value_final) }}</p>
                    </div>
                  </div>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-2">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dokumen Aset</p>
                    <div class="mt-3 space-y-3">
                      <div v-for="file in asset.documents" :key="file.id" class="rounded-2xl border bg-slate-50 p-4">
                        <p class="font-medium text-slate-950">{{ file.original_name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ file.type_label }} - {{ file.size_label }}</p>
                        <div class="mt-3">
                          <Button variant="outline" size="sm" as-child>
                            <a :href="file.url" target="_blank" rel="noreferrer">Buka Dokumen</a>
                          </Button>
                        </div>
                      </div>
                      <div v-if="!asset.documents.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                        Tidak ada dokumen aset.
                      </div>
                    </div>
                  </div>

                  <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Foto Aset</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                      <a
                        v-for="photo in asset.photos"
                        :key="photo.id"
                        :href="photo.url"
                        target="_blank"
                        rel="noreferrer"
                        class="overflow-hidden rounded-2xl border bg-slate-50"
                      >
                        <img :src="photo.url" :alt="photo.original_name" class="h-40 w-full object-cover" />
                        <div class="p-3">
                          <p class="text-sm font-medium text-slate-950">{{ photo.type_label }}</p>
                          <p class="mt-1 text-xs text-slate-500">{{ photo.original_name }}</p>
                        </div>
                      </a>
                      <div v-if="!asset.photos.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500 sm:col-span-2">
                        Tidak ada foto aset.
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="!assets.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Belum ada aset.
              </div>
            </CardContent>
          </Card>
        </div>

        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Pemohon</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 text-sm text-slate-700">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nama</p>
                <p class="mt-1">{{ requester.name }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Email</p>
                <p class="mt-1">{{ requester.email }}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Pembayaran</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-for="payment in payments" :key="payment.id" class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="font-medium text-slate-950">{{ formatCurrency(payment.amount) }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ payment.method_label }}
                      <span v-if="payment.gateway">- {{ payment.gateway }}</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ payment.external_payment_id || '-' }}</p>
                  </div>
                  <Badge variant="outline" class="bg-slate-100 text-slate-800 border-slate-200">
                    {{ paymentStatusLabel(payment.status) }}
                  </Badge>
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ formatDateTime(payment.paid_at) }}</p>
              </div>
              <div v-if="!payments.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Belum ada data pembayaran.
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Riwayat Negosiasi</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-for="entry in negotiations" :key="entry.id" class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="font-medium text-slate-950">{{ entry.action_label }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ entry.actor_name }}
                      <span v-if="entry.round">- Putaran {{ entry.round }}</span>
                    </p>
                  </div>
                  <span class="text-xs text-slate-500">{{ formatDateTime(entry.created_at) }}</span>
                </div>
                <div class="mt-3 grid gap-2 text-sm text-slate-700">
                  <p>Offered: {{ formatCurrency(entry.offered_fee) }}</p>
                  <p>Expected: {{ formatCurrency(entry.expected_fee) }}</p>
                  <p>Selected: {{ formatCurrency(entry.selected_fee) }}</p>
                  <p v-if="entry.reason">Catatan: {{ entry.reason }}</p>
                </div>
              </div>
              <div v-if="!negotiations.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
                Belum ada histori negosiasi.
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Catatan</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 text-sm text-slate-700">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Catatan User</p>
                <p class="mt-2 whitespace-pre-line">{{ record.user_request_note || '-' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Catatan Internal</p>
                <p class="mt-2 whitespace-pre-line">{{ record.notes || '-' }}</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
