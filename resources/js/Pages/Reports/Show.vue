<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import {
  ArrowLeft,
  Calendar,
  ExternalLink,
  FileArchive,
  FileText,
  Image as ImageIcon,
  MapPin,
  ReceiptText,
  ShieldCheck,
} from "lucide-vue-next";

const props = defineProps({
  report: { type: Object, required: true },
});

const statusTone = (statusKey) => {
  const status = String(statusKey || "").toLowerCase();

  if (["completed", "report_ready"].includes(status)) return "default";
  if (["cancelled"].includes(status)) return "destructive";
  if (["contract_signed", "valuation_in_progress", "valuation_completed", "preview_ready", "report_preparation"].includes(status)) return "secondary";
  return "outline";
};

const formatDateTime = (value) => {
  if (!value) return "-";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
};

const formatBytes = (bytes) => {
  const n = Number(bytes);
  if (!Number.isFinite(n) || n <= 0) return "-";

  const units = ["B", "KB", "MB", "GB"];
  const idx = Math.min(units.length - 1, Math.floor(Math.log(n) / Math.log(1024)));
  const value = n / Math.pow(1024, idx);

  return `${value.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
};

const readinessFlags = computed(() => [
  { key: "contract", label: "Kontrak", ready: Boolean(props.report.summary?.ready_contract) },
  { key: "invoice", label: "Invoice", ready: Boolean(props.report.summary?.ready_invoice) },
  { key: "report", label: "Laporan", ready: Boolean(props.report.summary?.ready_report) },
  { key: "legal", label: "Legal Final", ready: Boolean(props.report.summary?.ready_legal_documents) },
]);

const groupedSections = computed(() => [
  {
    key: "system",
    title: "Dokumen Sistem",
    description: "Dokumen yang disiapkan oleh workflow DigiPro selama proses permohonan berjalan.",
    items: props.report.system_documents || [],
    empty: "Belum ada dokumen sistem yang siap diakses untuk request ini.",
    icon: FileArchive,
  },
  {
    key: "legal",
    title: "Dokumen Legal Final",
    description: "Agreement, disclaimer, dan surat representatif final akan muncul setelah kontrak ditandatangani dan pembayaran terverifikasi.",
    items: props.report.legal_documents || [],
    empty: "Dokumen legal final belum tersedia.",
    icon: ShieldCheck,
  },
  {
    key: "billing",
    title: "Billing",
    description: "Invoice pembayaran disimpan sebagai arsip final setelah transaksi berhasil dibayar.",
    items: props.report.billing_documents || [],
    empty: "Invoice belum tersedia karena pembayaran belum terverifikasi.",
    icon: ReceiptText,
  },
  {
    key: "customer",
    title: "Dokumen Customer",
    description: "Dokumen request level yang Anda unggah untuk melengkapi permohonan.",
    items: props.report.request_upload_documents || [],
    empty: "Belum ada dokumen request level yang tersimpan.",
    icon: FileText,
  },
]);
</script>

<template>
  <UserDashboardLayout>
    <template #title>Dokumen</template>

    <div class="mx-auto max-w-6xl space-y-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-3">
          <Link :href="route('reports.index')" class="pt-1 text-slate-600 transition hover:text-slate-900">
            <ArrowLeft class="h-5 w-5" />
          </Link>

          <div>
            <h1 class="text-2xl font-semibold text-slate-900">Dokumen Request</h1>
            <p class="text-sm text-slate-500">
              {{ report.request_number }} · {{ report.client }}
            </p>
          </div>
        </div>

        <Badge :variant="statusTone(report.status_key)">{{ report.status }}</Badge>
      </div>

      <Card class="shadow-sm">
        <CardHeader class="space-y-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <CardTitle class="text-lg">{{ report.request_number }}</CardTitle>
              <CardDescription>{{ report.report_type }}</CardDescription>
            </div>
          </div>

          <div class="grid gap-3 text-sm text-slate-600 md:grid-cols-3">
            <div class="flex items-start gap-2">
              <MapPin class="mt-0.5 h-4 w-4 text-slate-400" />
              <span>{{ report.address || "-" }}</span>
            </div>
            <div class="flex items-center gap-2">
              <Calendar class="h-4 w-4 text-slate-400" />
              <span>Update {{ formatDateTime(report.updated_at) }}</span>
            </div>
            <div class="flex items-center gap-2">
              <FileArchive class="h-4 w-4 text-slate-400" />
              <span>{{ report.summary?.total_documents_count || 0 }} file tersedia</span>
            </div>
          </div>
        </CardHeader>

        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border bg-slate-50 p-3">
              <div class="text-xs uppercase tracking-wide text-slate-500">Upload Customer</div>
              <div class="mt-2 text-2xl font-semibold text-slate-900">
                {{ report.summary?.customer_documents_count || 0 }}
              </div>
            </div>

            <div class="rounded-xl border bg-slate-50 p-3">
              <div class="text-xs uppercase tracking-wide text-slate-500">Foto Aset</div>
              <div class="mt-2 text-2xl font-semibold text-slate-900">
                {{ report.summary?.customer_photos_count || 0 }}
              </div>
            </div>

            <div class="rounded-xl border bg-slate-50 p-3">
              <div class="text-xs uppercase tracking-wide text-slate-500">Dokumen Sistem</div>
              <div class="mt-2 text-2xl font-semibold text-slate-900">
                {{ report.summary?.system_documents_count || 0 }}
              </div>
            </div>

            <div class="rounded-xl border bg-slate-50 p-3">
              <div class="text-xs uppercase tracking-wide text-slate-500">Total Arsip</div>
              <div class="mt-2 text-2xl font-semibold text-slate-900">
                {{ report.summary?.total_documents_count || 0 }}
              </div>
            </div>
          </div>

          <Separator />

          <div class="flex flex-wrap gap-2">
            <Badge
              v-for="flag in readinessFlags"
              :key="flag.key"
              :variant="flag.ready ? 'default' : 'outline'"
            >
              {{ flag.label }} {{ flag.ready ? "Siap" : "Belum" }}
            </Badge>
          </div>
        </CardContent>
      </Card>

      <Card
        v-for="section in groupedSections"
        :key="section.key"
        class="shadow-sm"
      >
        <CardHeader>
          <div class="flex items-start gap-3">
            <div class="rounded-xl border bg-slate-50 p-2 text-slate-700">
              <component :is="section.icon" class="h-4 w-4" />
            </div>
            <div>
              <CardTitle class="text-base">{{ section.title }}</CardTitle>
              <CardDescription>{{ section.description }}</CardDescription>
            </div>
          </div>
        </CardHeader>

        <CardContent>
          <div v-if="!section.items.length" class="rounded-xl border border-dashed p-4 text-sm text-slate-500">
            {{ section.empty }}
          </div>

          <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div
              v-for="file in section.items"
              :key="`${section.key}-${file.id}-${file.type}`"
              class="rounded-xl border p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div class="text-xs uppercase tracking-wide text-slate-500">{{ file.label }}</div>
                  <div class="mt-1 font-medium text-slate-900">{{ file.original_name || "-" }}</div>
                </div>
                <Badge variant="outline">{{ file.type }}</Badge>
              </div>

              <div class="mt-3 grid gap-2 text-sm text-slate-600">
                <div>Ukuran: {{ formatBytes(file.size) }}</div>
                <div>Dibuat: {{ formatDateTime(file.created_at) }}</div>
              </div>

              <div class="mt-4">
                <Button v-if="file.url" as-child variant="outline" size="sm">
                  <a :href="file.url" target="_blank" rel="noreferrer">
                    <ExternalLink class="mr-2 h-4 w-4" />
                    Buka Dokumen
                  </a>
                </Button>
                <div v-else class="text-sm text-slate-500">File belum memiliki URL unduh.</div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card class="shadow-sm">
        <CardHeader>
          <div class="flex items-start gap-3">
            <div class="rounded-xl border bg-slate-50 p-2 text-slate-700">
              <ImageIcon class="h-4 w-4" />
            </div>
            <div>
              <CardTitle class="text-base">Foto Aset & Dokumen Per Aset</CardTitle>
              <CardDescription>
                Arsip file aktif per aset, termasuk dokumen legalitas aset dan dokumentasi foto.
              </CardDescription>
            </div>
          </div>
        </CardHeader>

        <CardContent class="space-y-4">
          <div v-if="!report.asset_sections?.length" class="rounded-xl border border-dashed p-4 text-sm text-slate-500">
            Belum ada data aset yang memiliki file aktif.
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="section in report.asset_sections"
              :key="section.id"
              class="rounded-2xl border p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h2 class="text-base font-semibold text-slate-900">{{ section.title }}</h2>
                  <p class="mt-1 text-sm text-slate-500">{{ section.address }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                  <Badge variant="outline">{{ section.documents.length }} dokumen</Badge>
                  <Badge variant="outline">{{ section.photos.length }} foto</Badge>
                </div>
              </div>

              <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="space-y-3">
                  <div class="text-sm font-medium text-slate-900">Dokumen</div>
                  <div v-if="!section.documents.length" class="rounded-xl border border-dashed p-4 text-sm text-slate-500">
                    Belum ada dokumen aset aktif.
                  </div>
                  <div v-else class="space-y-3">
                    <div
                      v-for="file in section.documents"
                      :key="`asset-doc-${file.id}`"
                      class="rounded-xl border p-3"
                    >
                      <div class="text-xs uppercase tracking-wide text-slate-500">{{ file.label }}</div>
                      <div class="mt-1 font-medium text-slate-900">{{ file.original_name || "-" }}</div>
                      <div class="mt-2 text-sm text-slate-600">
                        {{ formatBytes(file.size) }} · {{ formatDateTime(file.created_at) }}
                      </div>
                      <Button v-if="file.url" as-child variant="ghost" size="sm" class="mt-3 px-0 text-slate-700">
                        <a :href="file.url" target="_blank" rel="noreferrer">
                          <ExternalLink class="mr-2 h-4 w-4" />
                          Buka Dokumen
                        </a>
                      </Button>
                    </div>
                  </div>
                </div>

                <div class="space-y-3">
                  <div class="text-sm font-medium text-slate-900">Foto</div>
                  <div v-if="!section.photos.length" class="rounded-xl border border-dashed p-4 text-sm text-slate-500">
                    Belum ada foto aset aktif.
                  </div>
                  <div v-else class="space-y-3">
                    <div
                      v-for="file in section.photos"
                      :key="`asset-photo-${file.id}`"
                      class="rounded-xl border p-3"
                    >
                      <div class="text-xs uppercase tracking-wide text-slate-500">{{ file.label }}</div>
                      <div class="mt-1 font-medium text-slate-900">{{ file.original_name || "-" }}</div>
                      <div class="mt-2 text-sm text-slate-600">
                        {{ formatBytes(file.size) }} · {{ formatDateTime(file.created_at) }}
                      </div>
                      <Button v-if="file.url" as-child variant="ghost" size="sm" class="mt-3 px-0 text-slate-700">
                        <a :href="file.url" target="_blank" rel="noreferrer">
                          <ExternalLink class="mr-2 h-4 w-4" />
                          Buka Foto
                        </a>
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </UserDashboardLayout>
</template>
