<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { ArrowLeft, ChevronDown, Download, Printer, ReceiptText } from "lucide-vue-next";

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

const gatewayDetails = computed(() => props.payment?.gateway_details ?? null);
const billingSummary = computed(() => props.payment?.billing_summary ?? props.request?.billing_summary ?? {});

const formatIDR = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return "-";
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR" }).format(n);
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

                <div class="flex w-full flex-wrap items-center justify-between gap-2">
                    <Button variant="outline" @click="goBack">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Kembali ke Detail
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline">
                                Option
                                <ChevronDown class="ml-2 h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem @click="printInvoice">
                                <Printer class="mr-2 h-4 w-4" />
                                Cetak
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="downloadInvoicePdf">
                                <Download class="mr-2 h-4 w-4" />
                                Download Invoice
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="downloadContractPdf">
                                <Download class="mr-2 h-4 w-4" />
                                Download Kontrak
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Ringkasan Invoice</CardTitle>
                    <CardDescription>Informasi transaksi Midtrans yang telah dikonfirmasi</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Nomor Invoice</div>
                        <div class="font-medium">{{ payment.invoice_number ?? "-" }}</div>
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
                    <CardDescription>Nominal dan referensi transaksi dari Midtrans</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-xl border p-4">
                        <div class="text-xs text-muted-foreground">Total Tagihan</div>
                        <div class="mt-1 text-2xl font-semibold">{{ formatIDR(billingSummary.total_tagihan ?? payment.amount ?? request.fee_total) }}</div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-xl border p-3"><div class="text-xs text-muted-foreground">Nilai Jasa</div><div class="font-semibold">{{ formatIDR(billingSummary.nilai_jasa_dpp) }}</div></div>
                        <div class="rounded-xl border p-3"><div class="text-xs text-muted-foreground">PPN 11%</div><div class="font-semibold">{{ formatIDR(billingSummary.nilai_ppn) }}</div></div>
                        <div class="rounded-xl border p-3"><div class="text-xs text-muted-foreground">Total Tagihan</div><div class="font-semibold">{{ formatIDR(billingSummary.total_tagihan ?? payment.amount ?? request.fee_total) }}</div></div>
                        <div class="rounded-xl border p-3"><div class="text-xs text-muted-foreground">PPh 23 Dipotong</div><div class="font-semibold">{{ formatIDR(billingSummary.nilai_pph_dipotong) }}</div></div>
                        <div class="rounded-xl border p-3"><div class="text-xs text-muted-foreground">Total yang Ditransfer</div><div class="font-semibold">{{ formatIDR(billingSummary.total_transfer_customer) }}</div></div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="rounded-xl border p-3">
                            <div class="text-xs text-muted-foreground">Metode Pembayaran</div>
                            <div class="font-medium">{{ payment.method ?? "Midtrans Snap" }}</div>
                        </div>
                        <div class="rounded-xl border p-3">
                            <div class="text-xs text-muted-foreground">Status</div>
                            <div class="font-medium">{{ payment.status_label ?? "Dibayar" }}</div>
                        </div>
                        <div class="rounded-xl border p-3">
                            <div class="text-xs text-muted-foreground">Order ID</div>
                            <div class="font-medium break-all">{{ payment.external_payment_id ?? "-" }}</div>
                        </div>
                    </div>

                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Channel Pembayaran</div>
                        <div class="mt-1 text-sm space-y-1">
                            <div class="font-medium">{{ gatewayDetails?.label ?? payment.method ?? "-" }}</div>
                            <div v-if="gatewayDetails?.reference" class="font-mono">{{ gatewayDetails.reference }}</div>
                            <div v-if="gatewayDetails?.bank" class="text-muted-foreground">Bank: {{ gatewayDetails.bank }}</div>
                            <div v-if="gatewayDetails?.transaction_id" class="text-muted-foreground break-all">
                                Transaction ID: {{ gatewayDetails.transaction_id }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Keterangan</CardTitle>
                    <CardDescription>Ringkasan transaksi yang tercatat di DigiPro</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <div class="flex items-center gap-2 font-medium">
                            <ReceiptText class="h-4 w-4" />
                            Pembayaran terverifikasi otomatis
                        </div>
                        <p class="mt-1 text-xs text-emerald-800">
                            Status pembayaran telah diterima DigiPro dari Midtrans dan proses penilaian sudah berjalan.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </UserDashboardLayout>
</template>
