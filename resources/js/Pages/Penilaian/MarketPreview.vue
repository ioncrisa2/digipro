<script setup>
import { computed } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import { ArrowLeft, FileCheck2, Scale } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";

const props = defineProps({
    preview: { type: Object, required: true },
});

const preview = computed(() => props.preview ?? {});

const appealForm = useForm({
    reason: "",
});

const formatIDR = (value) => {
    const number = Number(value);
    if (!Number.isFinite(number)) return "-";

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(number);
};

const goBack = () => {
    router.visit(route("appraisal.show", preview.value?.id));
};

const approvePreview = () => {
    if (!preview.value?.approve_url || !preview.value?.can_approve) return;
    router.post(preview.value.approve_url, {}, { preserveScroll: true });
};

const submitAppeal = () => {
    if (!preview.value?.appeal_url || !preview.value?.can_appeal) return;
    appealForm.post(preview.value.appeal_url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Preview Kajian ${preview.request_number}`" />

    <DashboardLayout>
        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <Button variant="ghost" size="icon" @click="goBack">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-semibold">Preview Hasil Kajian Pasar</h1>
                            <Badge variant="secondary">{{ preview.request_number }}</Badge>
                            <Badge variant="outline">Versi {{ preview.version || 1 }}</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Ini adalah hasil kajian pasar dalam bentuk range sebelum laporan final diterbitkan.
                        </p>
                    </div>
                </div>

                <Button :disabled="!preview.can_approve" @click="approvePreview">
                    <FileCheck2 class="mr-2 h-4 w-4" />
                    Setuju & Lanjutkan Finalisasi
                </Button>
            </div>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Ringkasan Range Total</CardTitle>
                    <CardDescription>Akumulasi seluruh aset pada request ini.</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-muted-foreground">Estimasi Bawah</div>
                        <div class="mt-2 text-lg font-semibold">{{ formatIDR(preview.summary?.estimated_value_low) }}</div>
                    </div>
                    <div class="rounded-lg border p-4">
                        <div class="text-xs text-muted-foreground">Estimasi Atas</div>
                        <div class="mt-2 text-lg font-semibold">{{ formatIDR(preview.summary?.estimated_value_high) }}</div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Breakdown per Aset</CardTitle>
                    <CardDescription>Customer meninjau range tiap aset sebelum admin menyiapkan laporan final.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="(asset, index) in preview.assets || []"
                        :key="asset.asset_id || index"
                        class="rounded-lg border p-4"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="font-medium">Aset #{{ index + 1 }} · {{ asset.asset_type_label || "-" }}</div>
                                <div class="text-sm text-muted-foreground">{{ asset.address || "-" }}</div>
                            </div>
                            <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs text-slate-600">
                                <Scale class="h-3.5 w-3.5" />
                                LT {{ asset.land_area ?? "-" }} m2 · LB {{ asset.building_area ?? "-" }} m2
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <div class="rounded-md bg-slate-50 p-3">
                                <div class="text-xs text-muted-foreground">Estimasi Bawah</div>
                                <div class="mt-1 font-semibold">{{ formatIDR(asset.estimated_value_low) }}</div>
                            </div>
                            <div class="rounded-md bg-slate-50 p-3">
                                <div class="text-xs text-muted-foreground">Estimasi Atas</div>
                                <div class="mt-1 font-semibold">{{ formatIDR(asset.estimated_value_high) }}</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Keputusan Customer</CardTitle>
                    <CardDescription>Anda dapat menyetujui preview ini, atau menggunakan 1x kesempatan banding dengan alasan yang jelas.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Nilai pada halaman ini masih berupa preview hasil kajian pasar dalam range. Laporan final baru akan disiapkan setelah Anda menyetujui preview ini.
                    </div>

                    <div v-if="preview.can_appeal" class="space-y-3">
                        <div>
                            <Label for="appeal_reason">Alasan Banding</Label>
                            <Textarea
                                id="appeal_reason"
                                v-model="appealForm.reason"
                                class="mt-2 min-h-28"
                                placeholder="Jelaskan alasan banding, misalnya ada data objek atau kondisi pasar yang menurut Anda perlu ditinjau ulang."
                            />
                            <p v-if="appealForm.errors.reason" class="mt-2 text-sm text-red-600">
                                {{ appealForm.errors.reason }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" :disabled="appealForm.processing" @click="submitAppeal">
                                Ajukan Banding 1x
                            </Button>
                            <Button :disabled="appealForm.processing || !preview.can_approve" @click="approvePreview">
                                Setuju & Lanjutkan Finalisasi
                            </Button>
                        </div>
                    </div>

                    <div v-else class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
                        Kesempatan banding sudah digunakan. Preview revisi yang diterbitkan reviewer hanya bisa disetujui untuk dilanjutkan ke proses finalisasi laporan.
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
