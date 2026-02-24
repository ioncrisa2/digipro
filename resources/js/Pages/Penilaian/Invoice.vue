<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { ArrowLeft, Download, FileCheck2, Printer } from "lucide-vue-next";

const props = defineProps({
    request: { type: Object, default: () => ({}) },
    payment: { type: Object, default: () => ({}) },
});

const detailUrl = computed(() => {
    try {
        return route("appraisal.show", props.request?.id);
    } catch (_) {
        return `/permohonan-penilaian/${props.request?.id}`;
    }
});

const contractPdfUrl = computed(() => {
    try {
        return route("appraisal.contract.pdf", props.request?.id);
    } catch (_) {
        return `/permohonan-penilaian/${props.request?.id}/kontrak/pdf`;
    }
});

const invoicePdfUrl = computed(() => {
    try {
        return route("appraisal.invoice.pdf", props.request?.id);
    } catch (_) {
        return `/permohonan-penilaian/${props.request?.id}/invoice/pdf`;
    }
});

const invoiceNumber = computed(() => props.payment?.invoice_number ?? "-");
const selectedBank = computed(() => props.payment?.selected_bank_account ?? null);

const formatIDR = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return "-";
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR" }).format(n);
};

const formatBytes = (bytes) => {
    const n = Number(bytes);
    if (!Number.isFinite(n) || n <= 0) return "-";
    const units = ["B", "KB", "MB", "GB"];
    const idx = Math.min(Math.floor(Math.log(n) / Math.log(1024)), units.length - 1);
    const value = n / Math.pow(1024, idx);
    return `${value.toFixed(idx === 0 ? 0 : 2)} ${units[idx]}`;
};

const formatDateTime = (value) => {
    if (!value) return "-";
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(parsed);
};

const goBack = () => {
    router.visit(detailUrl.value);
};

const printInvoice = () => {
    window.print();
};

const downloadContractPdf = () => {
    window.open(contractPdfUrl.value, "_blank", "noopener,noreferrer");
};

const downloadInvoicePdf = () => {
    window.open(invoicePdfUrl.value, "_blank", "noopener,noreferrer");
};
</script>

<template>
    <UserDashboardLayout>
        <template #title>Invoice</template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold">Invoice Pembayaran</h1>
                        <Badge variant="outline">{{ request.request_number ?? "-" }}</Badge>
                        <Badge>{{ payment.status_label ?? "Dibayar" }}</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Pembayaran sudah terverifikasi. Simpan invoice ini sebagai bukti transaksi.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="printInvoice">
                        <Printer class="mr-2 h-4 w-4" />
                        Cetak
                    </Button>
                    <Button @click="downloadInvoicePdf">
                        <Download class="mr-2 h-4 w-4" />
                        Download Invoice PDF
                    </Button>
                    <Button variant="outline" @click="downloadContractPdf">
                        <Download class="mr-2 h-4 w-4" />
                        Download Kontrak
                    </Button>
                    <Button variant="outline" @click="goBack">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Kembali ke Detail
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Ringkasan Invoice</CardTitle>
                    <CardDescription>Informasi transaksi yang telah diverifikasi admin</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Nomor Invoice</div>
                        <div class="font-medium">{{ invoiceNumber }}</div>
                    </div>
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Nomor Request</div>
                        <div class="font-medium">{{ request.request_number ?? "-" }}</div>
                    </div>
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Nomor Kontrak</div>
                        <div class="font-medium">{{ request.contract_number ?? "-" }}</div>
                    </div>
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Waktu Konfirmasi</div>
                        <div class="font-medium">{{ formatDateTime(payment.paid_at) }}</div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Detail Pembayaran</CardTitle>
                    <CardDescription>Nominal dan rekening tujuan transfer</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-xl border p-4">
                        <div class="text-xs text-muted-foreground">Total Dibayar</div>
                        <div class="mt-1 text-2xl font-semibold">{{ formatIDR(payment.amount ?? request.fee_total) }}</div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="rounded-xl border p-3">
                            <div class="text-xs text-muted-foreground">Metode Pembayaran</div>
                            <div class="font-medium">{{ payment.method ?? "Transfer Bank" }}</div>
                        </div>
                        <div class="rounded-xl border p-3">
                            <div class="text-xs text-muted-foreground">Status</div>
                            <div class="font-medium">{{ payment.status_label ?? "Dibayar" }}</div>
                        </div>
                    </div>

                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Rekening Tujuan</div>
                        <div class="mt-1 text-sm">
                            <template v-if="selectedBank">
                                <div class="font-medium">{{ selectedBank.bank_name ?? "-" }}</div>
                                <div class="font-mono">{{ selectedBank.account_number ?? "-" }}</div>
                                <div class="text-muted-foreground">a.n. {{ selectedBank.account_holder ?? "-" }}</div>
                            </template>
                            <template v-else>
                                <div class="font-medium">-</div>
                            </template>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Bukti Pembayaran</CardTitle>
                    <CardDescription>File yang Anda unggah saat proses pembayaran</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <div class="flex items-center gap-2 font-medium">
                            <FileCheck2 class="h-4 w-4" />
                            Pembayaran terverifikasi
                        </div>
                        <p class="mt-1 text-xs text-emerald-800">
                            Proses penilaian sudah berjalan. Halaman upload pembayaran tidak bisa diakses lagi.
                        </p>
                    </div>

                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Nama File</div>
                        <div class="font-medium">{{ payment.proof_original_name ?? "-" }}</div>
                        <div class="text-xs text-muted-foreground">{{ formatBytes(payment.proof_size) }}</div>
                        <a
                            v-if="payment.proof_url"
                            :href="payment.proof_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 inline-flex text-sm underline"
                        >
                            Lihat file bukti pembayaran
                        </a>
                    </div>
                </CardContent>
            </Card>
        </div>
    </UserDashboardLayout>
</template>
