<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";

import { ArrowLeft, Calendar, CreditCard, FileText, MapPin } from "lucide-vue-next";
import { useAppraisalRequestShow } from "@/composables/useAppraisalRequestShow";

const props = defineProps({
    request: { type: Object, default: null },
});

const {
    req,
    statusLabel,
    statusVariant,
    formatIDR,
    formatBytes,
    formatArea,
    formatCoordinates,
    docTypeLabel,
    documentsSummary,
    documentsShortList,
    documentsImages,
    requestDocuments,
    documentsByAssetSections,
    statusTimeline,
    canDownloadReport,
    downloadReport,
} = useAppraisalRequestShow(props);

const subtitle = computed(() => {
    const r = req.value;
    const parts = [r.report_type_label, `${r.assets_count ?? 0} aset`].filter(Boolean);
    return parts.join(" | ");
});

const offerPageUrl = computed(() => {
    try {
        return route("appraisal.offer.page", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/penawaran`;
    }
});

const paymentPageUrl = computed(() => {
    try {
        return route("appraisal.payment.page", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/pembayaran`;
    }
});

const invoicePageUrl = computed(() => {
    try {
        return route("appraisal.invoice.page", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/invoice`;
    }
});

const paymentSummary = computed(() => {
    return req.value?.payment_summary ?? {};
});
const revisionSummary = computed(() => req.value?.revision_summary ?? {});
const previewState = computed(() => req.value?.preview_state ?? {});

const canOpenPreviewPage = computed(() => previewState.value?.status === "preview_ready" && Boolean(previewState.value?.page_url));

const canOpenPaymentPage = computed(() => {
    const status = String(req.value?.status ?? "").toLowerCase();
    return [
        "contract_signed",
        "valuation_in_progress",
        "valuation_completed",
        "preview_ready",
        "report_preparation",
        "report_ready",
        "completed",
    ].includes(status);
});

const paymentActionLabel = computed(() => {
    return paymentSummary.value?.is_paid ? "Lihat Invoice" : "Halaman Pembayaran";
});

const goBack = () => {
    try {
        router.visit(route("appraisal.list"));
    } catch (_) {
        router.visit("/penilaian");
    }
};

const goOfferPage = () => {
    if (!req.value?.id) return;
    router.visit(offerPageUrl.value);
};

const goPaymentPage = () => {
    if (!req.value?.id || !canOpenPaymentPage.value) return;
    if (paymentSummary.value?.is_paid) {
        router.visit(invoicePageUrl.value);
        return;
    }
    router.visit(paymentPageUrl.value);
};

const goRevisionPage = () => {
    if (!revisionSummary.value?.page_url) return;
    router.visit(revisionSummary.value.page_url);
};

const goPreviewPage = () => {
    if (!previewState.value?.page_url) return;
    router.visit(previewState.value.page_url);
};

const downloadIfReady = () => {
    if (!canDownloadReport.value) return;
    downloadReport();
};

const timelineDotClass = (type) => {
    const key = String(type ?? "default").toLowerCase();
    if (["success"].includes(key)) return "bg-emerald-500";
    if (["danger"].includes(key)) return "bg-red-500";
    if (["warning"].includes(key)) return "bg-amber-500";
    if (["offer", "payment", "submitted", "info"].includes(key)) return "bg-sky-500";
    return "bg-slate-400";
};
</script>

<template>
    <DashboardLayout>
        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <Button variant="ghost" size="icon" @click="goBack">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>

                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-semibold">Detail Request Penilaian</h1>
                            <Badge variant="secondary">{{ req.request_number }}</Badge>
                            <Badge :variant="statusVariant">{{ statusLabel }}</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ subtitle }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="goOfferPage">
                        <FileText class="mr-2 h-4 w-4" />
                        Halaman Penawaran
                    </Button>

                    <Button v-if="canOpenPreviewPage" @click="goPreviewPage">
                        <FileText class="mr-2 h-4 w-4" />
                        Review Preview Kajian
                    </Button>

                    <Button v-if="canOpenPaymentPage" variant="outline" @click="goPaymentPage">
                        <CreditCard class="mr-2 h-4 w-4" />
                        {{ paymentActionLabel }}
                    </Button>

                    <Button variant="outline" :disabled="!canDownloadReport" @click="downloadIfReady">
                        <FileText class="mr-2 h-4 w-4" />
                        Download Laporan
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Timeline Status</CardTitle>
                    <CardDescription>Riwayat perubahan status dan aktivitas penting permohonan</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="!statusTimeline.length" class="rounded-lg border p-3 text-sm text-muted-foreground">
                        Riwayat status belum tersedia.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="item in statusTimeline"
                            :key="item.key"
                            class="relative pl-6 pb-3 border-l border-slate-200 last:pb-0"
                        >
                            <span
                                class="absolute -left-[5px] top-1 h-2.5 w-2.5 rounded-full"
                                :class="timelineDotClass(item.type)"
                            />
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="text-sm font-medium">{{ item.title ?? "-" }}</div>
                                <div class="text-xs text-muted-foreground">{{ item.at ?? "-" }}</div>
                            </div>
                            <div class="text-xs text-slate-600 mt-1">
                                {{ item.description ?? "-" }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="req.status === 'cancelled'"
                class="border-red-200 bg-red-50/80"
            >
                <CardHeader class="pb-2">
                    <CardTitle class="text-base text-red-950">Permohonan Dibatalkan Sistem</CardTitle>
                    <CardDescription class="text-red-800">
                        Request ini tidak dapat dilanjutkan. Alasan pembatalan ditampilkan agar Anda mengetahui dasar keputusan sistem.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="rounded-lg border border-red-200 bg-white/70 p-3">
                            <div class="text-xs text-red-700">Dicatat Oleh</div>
                            <div class="mt-1 font-medium text-red-950">{{ req.cancelled_by_name ?? "Admin" }}</div>
                        </div>
                        <div class="rounded-lg border border-red-200 bg-white/70 p-3">
                            <div class="text-xs text-red-700">Waktu Pembatalan</div>
                            <div class="mt-1 font-medium text-red-950">{{ req.cancelled_at ?? "-" }}</div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-red-200 bg-white/70 p-3">
                        <div class="text-xs text-red-700">Alasan Pembatalan</div>
                        <div class="mt-1 whitespace-pre-line text-red-950">{{ req.cancellation_reason ?? "-" }}</div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="previewState.has_preview">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Preview Hasil Kajian Pasar</CardTitle>
                    <CardDescription>Ringkasan hasil kajian pasar dalam bentuk range sebelum laporan final diterbitkan.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="rounded-lg border p-3">
                            <div class="text-xs text-muted-foreground">Estimasi Bawah</div>
                            <div class="mt-1 font-semibold">{{ formatIDR(previewState.summary?.estimated_value_low) }}</div>
                        </div>
                        <div class="rounded-lg border p-3">
                            <div class="text-xs text-muted-foreground">Estimasi Atas</div>
                            <div class="mt-1 font-semibold">{{ formatIDR(previewState.summary?.estimated_value_high) }}</div>
                        </div>
                    </div>

                    <div
                        v-if="previewState.status === 'report_preparation'"
                        class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900"
                    >
                        Customer sudah menyetujui preview. Admin sedang menyiapkan laporan final lengkap dengan barcode/QR dan tanda tangan.
                    </div>

                    <div
                        v-else-if="previewState.status === 'preview_ready'"
                        class="flex flex-col gap-3 rounded-lg border border-fuchsia-200 bg-fuchsia-50 p-3 text-sm text-fuchsia-900 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            Preview versi {{ previewState.version || 1 }} sudah siap ditinjau customer.
                            <span v-if="previewState.appeal_remaining === 0" class="block mt-1 text-fuchsia-800">
                                Kesempatan banding sudah digunakan, sehingga preview revisi ini hanya bisa disetujui.
                            </span>
                        </div>
                        <Button @click="goPreviewPage">
                            <FileText class="mr-2 h-4 w-4" />
                            Buka Preview
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="revisionSummary.has_open_batch">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Revisi Data / Dokumen Dibutuhkan</CardTitle>
                    <CardDescription>
                        Admin meminta Anda memperbaiki {{ revisionSummary.items_count }} item data, dokumen, atau foto.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-slate-700">
                        Buka halaman revisi untuk melihat item mana yang harus diperbaiki, lalu kirim ulang seluruh perubahan yang diminta.
                    </div>
                    <Button @click="goRevisionPage">
                        <FileText class="mr-2 h-4 w-4" />
                        Buka Halaman Revisi
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Ringkasan Request & Berkas</CardTitle>
                    <CardDescription>Data inti request dan ikhtisar berkas dalam satu tampilan</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        <div class="rounded-lg border p-3">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <div>
                                    <div class="text-xs text-muted-foreground">Client</div>
                                    <div class="font-medium">{{ req.client_name ?? "-" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Jenis Laporan</div>
                                    <div class="font-medium">{{ req.report_type_label ?? "-" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Tujuan Penilaian</div>
                                    <div class="font-medium">{{ req.valuation_objective_label ?? "-" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Tanggal Request</div>
                                    <div class="font-medium flex items-center gap-1">
                                        <Calendar class="h-4 w-4" />
                                        {{ req.requested_at ?? "-" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Tanggal Verifikasi</div>
                                    <div class="font-medium">{{ req.verified_at ?? "-" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Nomor Kontrak</div>
                                    <div class="font-medium">{{ req.contract_number ?? "-" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Sertifikat On Hand</div>
                                    <div class="font-medium">{{ req.sertifikat_on_hand_confirmed ? "Ya" : "Tidak" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Fee</div>
                                    <div class="font-medium">{{ req.fee_total != null ? formatIDR(req.fee_total) : "-" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted-foreground">Tidak Dijaminkan</div>
                                    <div class="font-medium">{{ req.certificate_not_encumbered_confirmed ? "Ya" : "Tidak" }}</div>
                                </div>
                            </div>

                            <div class="mt-3 rounded-lg border px-3 py-2">
                                <div class="text-xs text-muted-foreground">Lokasi Ringkas</div>
                                <div class="mt-1 flex items-start gap-2">
                                    <MapPin class="mt-0.5 h-4 w-4" />
                                    <div class="font-medium">{{ req.first_asset_address ?? "-" }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border p-3 space-y-3">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-md border px-3 py-2">
                                    <div class="text-[11px] text-muted-foreground">Total Berkas</div>
                                    <div class="text-base font-semibold">{{ documentsSummary.totalCount }}</div>
                                </div>
                                <div class="rounded-md border px-3 py-2">
                                    <div class="text-[11px] text-muted-foreground">Total Ukuran</div>
                                    <div class="text-base font-semibold">{{ formatBytes(documentsSummary.totalBytes) }}</div>
                                </div>
                                <div class="rounded-md border px-3 py-2">
                                    <div class="text-[11px] text-muted-foreground">Dokumen</div>
                                    <div class="text-base font-semibold">{{ documentsShortList.length }}</div>
                                </div>
                                <div class="rounded-md border px-3 py-2">
                                    <div class="text-[11px] text-muted-foreground">Foto Aset</div>
                                    <div class="text-base font-semibold">{{ documentsImages.length }}</div>
                                </div>
                            </div>

                            <div class="rounded-md border px-3 py-2">
                                <div class="text-xs text-muted-foreground">Status Laporan</div>
                                <div class="text-sm font-medium">{{ canDownloadReport ? "Siap diunduh" : "Belum tersedia" }}</div>
                            </div>

                            <div v-if="documentsSummary.byType.length" class="flex flex-wrap gap-2">
                                <Badge v-for="item in documentsSummary.byType.slice(0, 4)" :key="item.label" variant="outline">
                                    {{ item.label }}: {{ item.count }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Dokumen Permohonan</CardTitle>
                    <CardDescription>Dokumen request level umum, termasuk surat representatif dan file kontrak digital.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-if="!requestDocuments.length" class="rounded-lg border p-3 text-sm text-muted-foreground">
                        Dokumen permohonan belum tersedia.
                    </div>

                    <div v-for="file in requestDocuments" :key="file.id" class="rounded-lg border p-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-1">
                                <div class="text-xs text-muted-foreground">{{ docTypeLabel(file.type) }}</div>
                                <div class="font-medium">{{ file.original_name ?? '-' }}</div>
                                <div class="text-xs text-muted-foreground">{{ formatBytes(file.size ?? 0) }} | {{ file.created_at ?? "-" }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button v-if="file.url" variant="outline" size="sm" as-child>
                                    <a :href="file.url" target="_blank" rel="noreferrer">Buka Dokumen</a>
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Aset</CardTitle>
                    <CardDescription>Daftar aset yang diajukan untuk dinilai</CardDescription>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div v-if="!(req.assets?.length)" class="rounded-xl border p-4 text-sm text-muted-foreground">
                        Belum ada aset.
                    </div>

                    <div v-for="(a, assetIndex) in (req.assets ?? [])" :key="a.id" class="rounded-lg border p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="font-medium">{{ a.type_label ?? a.type ?? "-" }}</div>
                            <Badge variant="outline">Aset #{{ assetIndex + 1 }}</Badge>
                        </div>

                        <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3 text-sm">
                            <div>
                                <div class="text-xs text-muted-foreground">Alamat</div>
                                <div class="font-medium">{{ a.address ?? "-" }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Luas Tanah</div>
                                <div class="font-medium">{{ formatArea(a.land_area) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Luas Bangunan</div>
                                <div class="font-medium">{{ a.type === "tanah" ? "-" : formatArea(a.building_area) }}</div>
                            </div>
                        </div>

                        <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3 text-sm">
                            <div>
                                <div class="text-xs text-muted-foreground">Lantai</div>
                                <div class="font-medium">{{ a.type === "tanah" ? "-" : (a.building_floors ?? "-") }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Tahun Renovasi</div>
                                <div class="font-medium">{{ a.type === "tanah" ? "-" : (a.renovation_year ?? "-") }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Koordinat</div>
                                <div class="font-medium">{{ formatCoordinates(a.coordinates) }}</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Dokumen & Foto Aset</CardTitle>
                    <CardDescription>Tampilan berkas dipisah per aset agar tidak tercampur antar aset</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="!documentsByAssetSections.length" class="rounded-lg border p-3 text-sm text-muted-foreground">
                        Belum ada berkas aset.
                    </div>

                    <div
                        v-for="section in documentsByAssetSections"
                        :key="section.key"
                        class="rounded-lg border p-3 space-y-3"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div class="text-sm font-semibold">{{ section.title }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ section.asset?.address ?? "Alamat tidak tersedia" }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Badge variant="outline">Total Dokumen: {{ section.documents.length }}</Badge>
                                <Badge variant="outline">Total Foto: {{ section.images.length }}</Badge>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                            <div class="rounded-lg border p-3 space-y-2">
                                <div class="text-sm font-medium">Dokumen</div>

                                <div v-if="!section.documents.length" class="rounded-lg border p-3 text-sm text-muted-foreground">
                                    Belum ada dokumen non-foto untuk aset ini.
                                </div>

                                <div
                                    v-for="d in section.documents"
                                    :key="d.id"
                                    class="rounded-lg border px-3 py-2"
                                >
                                    <div class="text-xs uppercase tracking-wide text-slate-500">
                                        {{ docTypeLabel(d.type) }}
                                    </div>
                                    <div class="text-sm font-medium">{{ d.original_name ?? "-" }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ formatBytes(d.size ?? 0) }} | {{ d.created_at ?? "-" }}
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border p-3 space-y-2">
                                <div class="text-sm font-medium">Foto Aset</div>

                                <div v-if="!section.images.length" class="rounded-lg border p-3 text-sm text-muted-foreground">
                                    Belum ada foto untuk aset ini.
                                </div>

                                <div v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div
                                        v-for="d in section.images"
                                        :key="d.id"
                                        class="rounded-lg border overflow-hidden"
                                    >
                                        <div class="bg-slate-50 aspect-video flex items-center justify-center">
                                            <img
                                                v-if="d.url"
                                                :src="d.url"
                                                :alt="d.original_name || docTypeLabel(d.type)"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="text-xs text-muted-foreground">Gambar belum tersedia</div>
                                        </div>
                                        <div class="p-2 space-y-1">
                                            <div class="text-[11px] uppercase tracking-wide text-slate-500">
                                                {{ docTypeLabel(d.type) }}
                                            </div>
                                            <div class="text-xs font-medium">{{ d.original_name ?? "-" }}</div>
                                            <div class="text-[11px] text-muted-foreground">{{ formatBytes(d.size ?? 0) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
