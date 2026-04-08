<script setup>
import { computed } from "vue";
import { Head, router } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import AppraisalProgressMilestone from "@/components/appraisal/AppraisalProgressMilestone.vue";
import AppraisalStatusTimeline from "@/components/appraisal/AppraisalStatusTimeline.vue";
import AppraisalPhysicalDeliveryStatus from "@/components/appraisal/AppraisalPhysicalDeliveryStatus.vue";
import { ArrowLeft } from "lucide-vue-next";

const props = defineProps({
    request: { type: Object, default: null },
});

const req = computed(() => props.request ?? {});
const progressSummary = computed(() => req.value?.progress_summary ?? null);
const physicalReport = computed(() => req.value?.physical_report ?? null);
const trackingContext = computed(() => req.value?.tracking_context ?? {});
const cancellationRequest = computed(() => req.value?.cancellation_request ?? null);
const showPhysicalReport = computed(() => Boolean(physicalReport.value?.needs_physical_report));
const subtitle = computed(() => {
    const parts = [req.value?.report_type_label, `${req.value?.assets_count ?? 0} aset`].filter(Boolean);
    return parts.join(" | ");
});

const goBack = () => {
    if (trackingContext.value?.back_url) {
        router.visit(trackingContext.value.back_url);
        return;
    }

    try {
        router.visit(route("appraisal.show", req.value?.id));
    } catch (_) {
        router.visit(`/permohonan-penilaian/${req.value?.id}`);
    }
};
</script>

<template>
    <Head :title="`Tracking ${req.request_number}`" />

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <Button variant="ghost" size="icon" @click="goBack">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>

                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-semibold">{{ trackingContext.title || "Tracking Progress" }}</h1>
                            <Badge variant="secondary">{{ req.request_number }}</Badge>
                            <Badge variant="outline">{{ req.status_label }}</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ subtitle }}
                        </p>
                    </div>
                </div>
            </div>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Milestone Progress</CardTitle>
                    <CardDescription>
                        {{ trackingContext.description || "Riwayat lengkap progres permohonan penilaian Anda." }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <AppraisalProgressMilestone v-if="progressSummary" :summary="progressSummary" />
                </CardContent>
            </Card>

            <Card
                v-if="cancellationRequest?.status"
                class="border-slate-200"
            >
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Status Pengajuan Pembatalan</CardTitle>
                    <CardDescription>
                        Ringkasan review admin terhadap permohonan pembatalan yang pernah Anda kirim.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-xl border p-3">
                            <div class="text-xs text-slate-500">Status Review</div>
                            <div class="mt-1 font-medium text-slate-950">
                                {{ cancellationRequest.status_label || "-" }}
                            </div>
                        </div>
                        <div class="rounded-xl border p-3">
                            <div class="text-xs text-slate-500">Waktu Pengajuan</div>
                            <div class="mt-1 font-medium text-slate-950">
                                {{ cancellationRequest.requested_at || "-" }}
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-slate-500">Alasan Customer</div>
                        <div class="mt-1 whitespace-pre-line text-slate-950">
                            {{ cancellationRequest.reason || "-" }}
                        </div>
                    </div>

                    <div
                        v-if="cancellationRequest.review_note"
                        class="rounded-xl border p-3"
                    >
                        <div class="text-xs text-slate-500">Catatan Review Admin</div>
                        <div class="mt-1 whitespace-pre-line text-slate-950">
                            {{ cancellationRequest.review_note }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="showPhysicalReport">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Pengiriman Hard Copy</CardTitle>
                    <CardDescription>
                        Status manual pengiriman laporan fisik berdasarkan catatan operasional admin.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <AppraisalPhysicalDeliveryStatus :summary="physicalReport" />
                </CardContent>
            </Card>

            <Card
                v-if="req.status === 'cancelled'"
                class="border-red-200 bg-red-50/80"
            >
                <CardHeader class="pb-2">
                    <CardTitle class="text-base text-red-950">Permohonan Dibatalkan</CardTitle>
                    <CardDescription class="text-red-800">
                        Tracking tetap ditampilkan agar Anda mengetahui titik terakhir proses sebelum pembatalan.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-red-950">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-lg border border-red-200 bg-white/70 p-3">
                            <div class="text-xs text-red-700">Dicatat Oleh</div>
                            <div class="mt-1 font-medium">{{ req.cancelled_by_name ?? "Admin" }}</div>
                        </div>
                        <div class="rounded-lg border border-red-200 bg-white/70 p-3">
                            <div class="text-xs text-red-700">Waktu Pembatalan</div>
                            <div class="mt-1 font-medium">{{ req.cancelled_at ?? "-" }}</div>
                        </div>
                    </div>
                    <div class="rounded-lg border border-red-200 bg-white/70 p-3">
                        <div class="text-xs text-red-700">Alasan Pembatalan</div>
                        <div class="mt-1 whitespace-pre-line">{{ req.cancellation_reason ?? "-" }}</div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Timeline Lengkap</CardTitle>
                    <CardDescription>Riwayat lengkap perubahan status, negosiasi, pembayaran, preview, dan finalisasi laporan.</CardDescription>
                </CardHeader>
                <CardContent>
                    <AppraisalStatusTimeline
                        :events="req.status_timeline || []"
                        empty-text="Riwayat status belum tersedia untuk permohonan ini."
                    />
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
