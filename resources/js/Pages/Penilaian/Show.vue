<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import AppraisalProgressMilestone from "@/components/appraisal/AppraisalProgressMilestone.vue";
import AppraisalStatusTimeline from "@/components/appraisal/AppraisalStatusTimeline.vue";
import AppraisalDocumentWorkspace from "@/components/appraisal/AppraisalDocumentWorkspace.vue";
import AppraisalPhysicalDeliveryStatus from "@/components/appraisal/AppraisalPhysicalDeliveryStatus.vue";
import { useNotification } from "@/composables/useNotification";

import { ArrowLeft, Calendar, FileText, MapPin, PhoneCall } from "lucide-vue-next";
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
    documentsSummary,
    documentsShortList,
    documentsImages,
    documentWorkspace,
    progressSummary,
    physicalReport,
    billingSummary,
    recentStatusEvents,
    trackingPageUrl,
    cancellationRequest,
    canRequestCancellation,
    cancellationBlockers,
    cancellationRequestUrl,
    supportContact,
    canDownloadReport,
} = useAppraisalRequestShow(props);
const { notify } = useNotification();

const subtitle = computed(() => {
    const r = req.value;
    const parts = [r.report_type_label, `${r.assets_count ?? 0} aset`].filter(Boolean);
    return parts.join(" | ");
});

const revisionSummary = computed(() => req.value?.revision_summary ?? {});
const previewState = computed(() => req.value?.preview_state ?? {});
const primaryAction = computed(() => progressSummary.value?.primary_action ?? null);
const showPhysicalReport = computed(() => Boolean(physicalReport.value?.needs_physical_report));
const cancellationFormDialog = ref(false);
const cancellationForm = useForm({
    reason: "",
});
const cancellationDialogOpen = computed({
    get: () => cancellationFormDialog.value,
    set: (value) => {
        cancellationFormDialog.value = value;
        if (!value) {
            cancellationForm.reset();
            cancellationForm.clearErrors();
        }
    },
});
const hasOpenCancellationRequest = computed(() => Boolean(cancellationRequest.value?.has_open_request));
const hasPhoneBlocker = computed(() => {
    return cancellationBlockers.value.some((item) => {
        const key = typeof item === "string" ? item : item?.key;
        return key === "missing_phone_number";
    });
});
const canOpenCancellationDialog = computed(() => {
    return Boolean(canRequestCancellation.value && cancellationRequestUrl.value && !hasOpenCancellationRequest.value);
});

const goBack = () => {
    try {
        router.visit(route("appraisal.list"));
    } catch (_) {
        router.visit("/penilaian");
    }
};

const goRevisionPage = () => {
    if (!revisionSummary.value?.page_url) return;
    router.visit(revisionSummary.value.page_url);
};

const goPreviewPage = () => {
    if (!previewState.value?.page_url) return;
    router.visit(previewState.value.page_url);
};

const goTrackingPage = () => {
    if (!trackingPageUrl.value) return;
    router.visit(trackingPageUrl.value);
};

const goProfilePage = () => {
    try {
        router.visit(route("profile.edit"));
    } catch (_) {
        router.visit("/profile");
    }
};

const runPrimaryAction = () => {
    if (!primaryAction.value?.url) return;

    if (primaryAction.value.external) {
        window.open(primaryAction.value.url, "_blank", "noreferrer");
        return;
    }

    router.visit(primaryAction.value.url);
};

const openCancellationDialog = () => {
    if (!canOpenCancellationDialog.value) return;
    cancellationDialogOpen.value = true;
};

const submitCancellationRequest = () => {
    if (!cancellationRequestUrl.value || cancellationForm.processing) return;

    cancellationForm.post(cancellationRequestUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            cancellationDialogOpen.value = false;
            notify("success", "Pengajuan pembatalan berhasil dikirim. Admin akan menghubungi Anda untuk review.");
        },
        onError: () => {
            notify("error", "Pengajuan pembatalan belum berhasil dikirim. Periksa kembali isian Anda.");
        },
    });
};
</script>

<template>
    <Head :title="`Detail Request ${req.request_number}`" />

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
            </div>

            <Card class="overflow-hidden border-slate-200">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Progress Permohonan</CardTitle>
                    <CardDescription>
                        Ringkasan milestone utama, status aktif, dan tindakan berikutnya untuk permohonan Anda.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <AppraisalProgressMilestone v-if="progressSummary" :summary="progressSummary" />

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="primaryAction"
                            :variant="primaryAction.variant || 'default'"
                            @click="runPrimaryAction"
                        >
                            <FileText class="mr-2 h-4 w-4" />
                            {{ primaryAction.label }}
                        </Button>

                        <Button variant="outline" @click="goTrackingPage">
                            <FileText class="mr-2 h-4 w-4" />
                            Lihat Tracking Lengkap
                        </Button>
                    </div>

                    <div class="border-t border-slate-200 pt-6">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-950">3 Event Terbaru</p>
                                <p class="text-sm text-slate-500">Tampilan ringkas perubahan status terakhir pada permohonan Anda.</p>
                            </div>
                        </div>

                        <AppraisalStatusTimeline
                            :events="recentStatusEvents"
                            empty-text="Riwayat status belum tersedia."
                        />
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="showPhysicalReport"
                class="border-slate-200"
            >
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Pengiriman Hard Copy</CardTitle>
                    <CardDescription>
                        Ringkasan manual pengiriman laporan fisik yang dicatat oleh admin.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <AppraisalPhysicalDeliveryStatus :summary="physicalReport" />
                </CardContent>
            </Card>

            <Card v-if="billingSummary" class="border-slate-200">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Ringkasan Tagihan & Pajak</CardTitle>
                    <CardDescription>
                        Breakdown tagihan jasa, PPN, PPh dipotong, dan dokumen keuangan yang diterbitkan admin finance.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-xl border bg-slate-50/60 p-3">
                            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Nilai Jasa</div>
                            <div class="mt-2 text-base font-semibold text-slate-950">{{ formatIDR(billingSummary.nilai_jasa_dpp) }}</div>
                        </div>
                        <div class="rounded-xl border bg-slate-50/60 p-3">
                            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">PPN 11%</div>
                            <div class="mt-2 text-base font-semibold text-slate-950">{{ formatIDR(billingSummary.nilai_ppn) }}</div>
                        </div>
                        <div class="rounded-xl border bg-slate-50/60 p-3">
                            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Total Tagihan</div>
                            <div class="mt-2 text-base font-semibold text-slate-950">{{ formatIDR(billingSummary.total_tagihan) }}</div>
                        </div>
                        <div class="rounded-xl border bg-slate-50/60 p-3">
                            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">PPh 23 Dipotong</div>
                            <div class="mt-2 text-base font-semibold text-slate-950">{{ formatIDR(billingSummary.nilai_pph_dipotong) }}</div>
                        </div>
                        <div class="rounded-xl border bg-slate-50/60 p-3">
                            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Total yang Ditransfer</div>
                            <div class="mt-2 text-base font-semibold text-slate-950">{{ formatIDR(billingSummary.total_transfer_customer) }}</div>
                        </div>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.9fr)]">
                        <div class="rounded-2xl border p-4">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Invoice</div>
                                    <div class="mt-1 text-sm font-medium text-slate-950">{{ billingSummary.nomor_invoice || "-" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Tanggal Invoice</div>
                                    <div class="mt-1 text-sm font-medium text-slate-950">{{ billingSummary.tanggal_invoice || "-" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Faktur Pajak</div>
                                    <div class="mt-1 text-sm font-medium text-slate-950">{{ billingSummary.nomor_faktur_pajak || "-" }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Bukti Potong</div>
                                    <div class="mt-1 text-sm font-medium text-slate-950">{{ billingSummary.nomor_bukti_potong || "-" }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border p-4">
                            <div class="space-y-3">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Dokumen Finance</div>
                                    <div class="mt-1 text-sm text-slate-600">
                                        Invoice tagihan, faktur pajak, dan bukti potong akan muncul di sini setelah admin finance melengkapinya.
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button v-if="billingSummary.dokumen_invoice_url" variant="outline" as-child>
                                        <a :href="billingSummary.dokumen_invoice_url" target="_blank" rel="noreferrer">Buka Invoice</a>
                                    </Button>
                                    <Button v-if="billingSummary.dokumen_faktur_pajak_url" variant="outline" as-child>
                                        <a :href="billingSummary.dokumen_faktur_pajak_url" target="_blank" rel="noreferrer">Buka Faktur Pajak</a>
                                    </Button>
                                    <Button v-if="billingSummary.dokumen_bukti_potong_url" variant="outline" as-child>
                                        <a :href="billingSummary.dokumen_bukti_potong_url" target="_blank" rel="noreferrer">Buka Bukti Potong</a>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-slate-200">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Tindakan Customer</CardTitle>
                    <CardDescription>
                        Kanal resmi untuk pengajuan pembatalan dan bantuan follow-up bila Anda perlu menghentikan proses request ini.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="hasOpenCancellationRequest"
                        class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50/80 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-amber-950">
                                    {{ cancellationRequest?.status_label || "Menunggu Review Pembatalan" }}
                                </p>
                                <p class="text-sm text-amber-900">
                                    Admin akan menghubungi Anda untuk review permohonan pembatalan. Proses penilaian sedang ditahan sampai keputusan review selesai.
                                </p>
                            </div>
                            <Button variant="outline" @click="goTrackingPage">Lihat Tracking</Button>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-xl border border-amber-200 bg-white/80 p-3">
                                <div class="text-xs uppercase tracking-[0.18em] text-amber-700">Waktu Pengajuan</div>
                                <div class="mt-1 text-sm font-medium text-amber-950">
                                    {{ cancellationRequest?.requested_at || "-" }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-white/80 p-3">
                                <div class="text-xs uppercase tracking-[0.18em] text-amber-700">Review Admin</div>
                                <div class="mt-1 text-sm font-medium text-amber-950">
                                    {{ cancellationRequest?.reviewed_by_name || "Belum ditugaskan" }}
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-amber-200 bg-white/80 p-3">
                            <div class="text-xs uppercase tracking-[0.18em] text-amber-700">Alasan Anda</div>
                            <div class="mt-2 whitespace-pre-line text-sm text-amber-950">
                                {{ cancellationRequest?.reason || "-" }}
                            </div>
                        </div>

                        <div
                            v-if="cancellationRequest?.review_note"
                            class="rounded-xl border border-amber-200 bg-white/80 p-3"
                        >
                            <div class="text-xs uppercase tracking-[0.18em] text-amber-700">Catatan Review</div>
                            <div class="mt-2 whitespace-pre-line text-sm text-amber-950">
                                {{ cancellationRequest.review_note }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="canOpenCancellationDialog"
                        class="grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(240px,0.7fr)]"
                    >
                        <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-slate-950">Ajukan Pembatalan ke Admin</p>
                                <p class="text-sm text-slate-600">
                                    Pembatalan tidak disarankan. Bila Anda tetap ingin menghentikan proses, admin akan meninjau permintaan ini terlebih dahulu.
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl border bg-white p-3">
                                    <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Kebijakan Biaya</div>
                                    <div class="mt-1 text-sm font-medium text-slate-950">
                                        Biaya yang telah dibayarkan tidak dapat dikembalikan.
                                    </div>
                                </div>
                                <div class="rounded-xl border bg-white p-3">
                                    <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Alur Review</div>
                                    <div class="mt-1 text-sm font-medium text-slate-950">
                                        Admin akan menghubungi Anda sebelum keputusan akhir dibuat.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 p-4">
                            <div class="space-y-2">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Support Contact</p>
                                <div class="text-sm font-medium text-slate-950">
                                    {{ supportContact?.name || "Tim Admin DigiPro by KJPP HJAR" }}
                                </div>
                                <div class="flex items-center gap-2 text-sm text-slate-600">
                                    <PhoneCall class="h-4 w-4" />
                                    <span>{{ supportContact?.phone || "-" }}</span>
                                </div>
                                <p class="text-xs text-slate-500">
                                    {{ supportContact?.availability_label || "Hubungi admin bila Anda memerlukan klarifikasi tambahan." }}
                                </p>
                            </div>

                            <Button variant="outline" @click="openCancellationDialog">
                                Ajukan Pembatalan
                            </Button>
                        </div>
                    </div>

                    <div
                        v-else
                        class="grid gap-4 lg:grid-cols-[minmax(0,1.25fr)_minmax(240px,0.75fr)]"
                    >
                        <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-slate-950">Permohonan belum bisa diajukan pembatalan</p>
                                <p class="text-sm text-slate-600">
                                    Sistem memeriksa kelengkapan profil dan status permohonan sebelum pengajuan pembatalan bisa diproses.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="(item, index) in cancellationBlockers"
                                    :key="`${item?.key || 'blocker'}-${index}`"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                >
                                    {{ item?.message || item }}
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col justify-between gap-3 rounded-2xl border border-slate-200 p-4">
                            <div class="space-y-2">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Butuh Bantuan</p>
                                <div class="text-sm font-medium text-slate-950">
                                    {{ supportContact?.name || "Tim Admin DigiPro by KJPP HJAR" }}
                                </div>
                                <div class="flex items-center gap-2 text-sm text-slate-600">
                                    <PhoneCall class="h-4 w-4" />
                                    <span>{{ supportContact?.phone || "-" }}</span>
                                </div>
                            </div>

                            <Button v-if="hasPhoneBlocker" variant="outline" @click="goProfilePage">
                                Lengkapi Profil
                            </Button>
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
                            <span v-if="previewState.appeal_remaining === 0" class="mt-1 block text-fuchsia-800">
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
                                    <div class="flex items-center gap-1 font-medium">
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
                                    <div class="font-medium">{{ billingSummary?.total_tagihan != null ? formatIDR(billingSummary.total_tagihan) : (req.fee_total != null ? formatIDR(req.fee_total) : "-") }}</div>
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

                        <div class="space-y-3 rounded-lg border p-3">
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
                    <CardTitle class="text-base">Pusat Dokumen</CardTitle>
                    <CardDescription>
                        Semua arsip request, dokumen sistem, invoice, legal final, dan file aset dalam satu workspace yang lebih mudah discan.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <AppraisalDocumentWorkspace
                        :workspace="documentWorkspace"
                        :format-bytes="formatBytes"
                    />
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

                        <div class="mt-2 grid grid-cols-1 gap-2 text-sm md:grid-cols-3">
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

                        <div class="mt-2 grid grid-cols-1 gap-2 text-sm md:grid-cols-3">
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
        </div>
    </DashboardLayout>

    <Dialog v-model:open="cancellationDialogOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Ajukan Pembatalan Request</DialogTitle>
                <DialogDescription>
                    Pengajuan ini akan direview admin terlebih dahulu. Biaya yang telah dibayarkan tidak dapat dikembalikan.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-sm text-amber-950">
                    Pembatalan tidak disarankan. Jika tetap diajukan, proses penilaian akan ditahan sambil menunggu review admin.
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-slate-900" for="cancellation-reason">Alasan pembatalan</label>
                    <Textarea
                        id="cancellation-reason"
                        v-model="cancellationForm.reason"
                        rows="5"
                        placeholder="Jelaskan alasan Anda mengajukan pembatalan request penilaian."
                    />
                    <p v-if="cancellationForm.errors.reason" class="text-xs text-red-600">
                        {{ cancellationForm.errors.reason }}
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button type="button" variant="outline" @click="cancellationDialogOpen = false">
                    Kembali
                </Button>
                <Button type="button" :disabled="cancellationForm.processing" @click="submitCancellationRequest">
                    Kirim Pengajuan Pembatalan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
