<script setup>
import { computed, ref } from "vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  ExternalLink,
  FileArchive,
  FileText,
  Filter,
  Image as ImageIcon,
  ReceiptText,
  Search,
  ShieldCheck,
} from "lucide-vue-next";

const props = defineProps({
  workspace: { type: Object, required: true },
  formatBytes: { type: Function, required: true },
});

const activeFilter = ref("all");
const searchTerm = ref("");

const filters = [
  { key: "all", label: "Semua" },
  { key: "system", label: "Sistem" },
  { key: "customer", label: "Customer" },
  { key: "asset", label: "Aset" },
  { key: "photo", label: "Foto" },
  { key: "legal", label: "Legal" },
  { key: "billing", label: "Billing" },
];

const summary = computed(() => props.workspace?.summary ?? {});

const readinessFlags = computed(() => [
  { key: "contract", label: "Kontrak", ready: Boolean(summary.value?.ready_contract) },
  { key: "invoice", label: "Invoice", ready: Boolean(summary.value?.ready_invoice) },
  { key: "report", label: "Laporan", ready: Boolean(summary.value?.ready_report) },
  { key: "legal", label: "Legal Final", ready: Boolean(summary.value?.ready_legal_documents) },
]);

const baseSections = computed(() => {
  const workspace = props.workspace ?? {};
  const normalizeItems = (items, bucket, sectionKey, sectionTitle) =>
    (Array.isArray(items) ? items : []).map((item) => ({
      ...item,
      bucket,
      section_key: sectionKey,
      section_title: sectionTitle,
    }));

  const sections = [
    {
      key: "system",
      title: "Dokumen Sistem",
      description: "Dokumen yang dihasilkan workflow DigiPro by KJPP HJAR selama proses penilaian berjalan.",
      icon: FileArchive,
      items: normalizeItems(workspace.systemDocuments, "system", "system", "Dokumen Sistem"),
    },
    {
      key: "legal",
      title: "Dokumen Legal Final",
      description: "Agreement, disclaimer, dan surat representatif final untuk arsip penugasan.",
      icon: ShieldCheck,
      items: normalizeItems(workspace.legalDocuments, "legal", "legal", "Dokumen Legal Final"),
    },
    {
      key: "billing",
      title: "Billing",
      description: "Invoice dan arsip pembayaran final yang sudah diverifikasi.",
      icon: ReceiptText,
      items: normalizeItems(workspace.billingDocuments, "billing", "billing", "Billing"),
    },
    {
      key: "customer",
      title: "Dokumen Customer",
      description: "Dokumen level request yang Anda unggah untuk melengkapi permohonan.",
      icon: FileText,
      items: normalizeItems(workspace.requestUploadDocuments, "customer", "customer", "Dokumen Customer"),
    },
  ];

  const assetSections = (Array.isArray(workspace.assetSections) ? workspace.assetSections : []).map((section) => ({
    key: `asset-${section.id}`,
    title: section.title,
    description: section.address || "Alamat tidak tersedia",
    icon: ImageIcon,
    items: [
      ...normalizeItems(section.documents, "asset", `asset-${section.id}`, section.title),
      ...normalizeItems(section.photos, "photo", `asset-${section.id}`, section.title),
    ],
  }));

  return [...sections, ...assetSections];
});

const filteredSections = computed(() => {
  const query = searchTerm.value.trim().toLowerCase();
  const filter = activeFilter.value;

  const matchesFilter = (item) => {
    if (filter === "all") return true;
    if (filter === "asset") return item.bucket === "asset" || item.bucket === "photo";
    return item.bucket === filter;
  };

  const matchesQuery = (item) => {
    if (!query) return true;
    const haystack = [
      item.original_name,
      item.label,
      item.type,
      item.section_title,
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();

    return haystack.includes(query);
  };

  return baseSections.value
    .map((section) => ({
      ...section,
      items: section.items
        .filter((item) => matchesFilter(item) && matchesQuery(item))
        .sort((left, right) => String(right.created_at ?? "").localeCompare(String(left.created_at ?? ""))),
    }))
    .filter((section) => section.items.length > 0);
});

const totalFilteredItems = computed(() => filteredSections.value.reduce((sum, section) => sum + section.items.length, 0));

const isImageFile = (file) => {
  const mime = String(file?.mime ?? "").toLowerCase();
  const type = String(file?.type ?? "").toLowerCase();
  return mime.startsWith("image/") || type.startsWith("photo_") || type === "photos";
};

const itemBadgeClass = (bucket) => {
  switch (bucket) {
    case "system":
      return "border-sky-200 bg-sky-50 text-sky-800";
    case "legal":
      return "border-emerald-200 bg-emerald-50 text-emerald-800";
    case "billing":
      return "border-violet-200 bg-violet-50 text-violet-800";
    case "customer":
      return "border-slate-200 bg-slate-50 text-slate-700";
    case "photo":
      return "border-amber-200 bg-amber-50 text-amber-900";
    default:
      return "border-slate-200 bg-white text-slate-700";
  }
};

const emptyStateLabel = computed(() => {
  if (searchTerm.value.trim()) {
    return "Tidak ada dokumen yang cocok dengan pencarian atau filter saat ini.";
  }

  return "Belum ada dokumen yang tersedia untuk permohonan ini.";
});
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border bg-slate-50 px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Total Arsip</p>
        <p class="mt-3 text-3xl font-semibold text-slate-950">{{ summary.total_documents_count || 0 }}</p>
      </div>
      <div class="rounded-2xl border bg-slate-50 px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Upload Customer</p>
        <p class="mt-3 text-3xl font-semibold text-slate-950">{{ summary.customer_documents_count || 0 }}</p>
      </div>
      <div class="rounded-2xl border bg-slate-50 px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Foto Aset</p>
        <p class="mt-3 text-3xl font-semibold text-slate-950">{{ summary.customer_photos_count || 0 }}</p>
      </div>
      <div class="rounded-2xl border bg-slate-50 px-4 py-4">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Dokumen Sistem</p>
        <p class="mt-3 text-3xl font-semibold text-slate-950">{{ summary.system_documents_count || 0 }}</p>
      </div>
    </div>

    <div class="flex flex-col gap-4 rounded-2xl border bg-white p-4">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="space-y-1">
          <p class="text-sm font-semibold text-slate-950">Pusat Dokumen</p>
          <p class="text-sm text-slate-500">Cari file, filter berdasarkan kategori, lalu buka arsip yang Anda butuhkan tanpa harus scroll panjang.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
          <div class="relative w-full sm:w-72">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input
              v-model="searchTerm"
              type="search"
              placeholder="Cari nama file atau tipe dokumen"
              class="pl-9"
            />
          </div>
          <div class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs text-slate-500">
            <Filter class="h-3.5 w-3.5" />
            {{ totalFilteredItems }} file tampil
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button
          v-for="item in filters"
          :key="item.key"
          size="sm"
          :variant="activeFilter === item.key ? 'default' : 'outline'"
          @click="activeFilter = item.key"
        >
          {{ item.label }}
        </Button>
      </div>

      <div class="flex flex-wrap gap-2">
        <Badge
          v-for="flag in readinessFlags"
          :key="flag.key"
          :variant="flag.ready ? 'default' : 'outline'"
        >
          {{ flag.label }} {{ flag.ready ? "Siap" : "Belum" }}
        </Badge>
      </div>
    </div>

    <div v-if="!filteredSections.length" class="rounded-2xl border border-dashed p-6 text-sm text-slate-500">
      {{ emptyStateLabel }}
    </div>

    <div v-else class="space-y-5">
      <section
        v-for="section in filteredSections"
        :key="section.key"
        class="rounded-3xl border bg-white p-5"
      >
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h3 class="text-base font-semibold text-slate-950">{{ section.title }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ section.description }}</p>
          </div>
          <Badge variant="outline">{{ section.items.length }} file</Badge>
        </div>

        <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
          <article
            v-for="file in section.items"
            :key="`${section.key}-${file.id}-${file.type}`"
            class="overflow-hidden rounded-2xl border bg-slate-50/70"
          >
            <div class="flex h-full gap-4 p-4">
              <div
                v-if="isImageFile(file)"
                class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl border bg-white"
              >
                <img
                  v-if="file.url"
                  :src="file.url"
                  :alt="file.original_name || file.label"
                  class="h-full w-full object-cover"
                />
                <div v-else class="flex h-full items-center justify-center text-xs text-slate-400">Foto</div>
              </div>

              <div class="min-w-0 flex-1 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ file.label }}</p>
                    <p class="mt-1 truncate text-sm font-medium text-slate-950">{{ file.original_name || "-" }}</p>
                  </div>
                  <span
                    class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-medium"
                    :class="itemBadgeClass(file.bucket)"
                  >
                    {{ file.bucket === "photo" ? "Foto" : file.bucket === "asset" ? "Aset" : file.bucket === "customer" ? "Customer" : file.bucket === "legal" ? "Legal" : file.bucket === "billing" ? "Billing" : "Sistem" }}
                  </span>
                </div>

                <div class="grid gap-2 text-xs text-slate-500 sm:grid-cols-2">
                  <p>Ukuran: <span class="font-medium text-slate-700">{{ formatBytes(file.size) }}</span></p>
                  <p>Dibuat: <span class="font-medium text-slate-700">{{ file.created_at || "-" }}</span></p>
                  <p class="sm:col-span-2">Tipe: <span class="font-medium text-slate-700">{{ file.type || "-" }}</span></p>
                </div>

                <div>
                  <Button v-if="file.url" as-child variant="outline" size="sm">
                    <a :href="file.url" target="_blank" rel="noreferrer">
                      <ExternalLink class="mr-2 h-4 w-4" />
                      Buka Dokumen
                    </a>
                  </Button>
                  <span v-else class="text-xs text-slate-400">File belum memiliki URL unduh.</span>
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </div>
</template>
