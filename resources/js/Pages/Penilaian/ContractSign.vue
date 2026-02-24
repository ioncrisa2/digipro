<script setup>
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { ArrowLeft, Download, FilePenLine } from "lucide-vue-next";

const props = defineProps({
    request: { type: Object, default: null },
});

const req = computed(() => props.request ?? {});
const signing = ref(false);
const hasAgreedToSign = ref(false);

const canSign = computed(() => req.value?.status === "waiting_signature");
const contractDoc = computed(() => req.value?.contract_document ?? {});
const signatureInfo = computed(() => contractDoc.value?.signature ?? {});

const assetRows = computed(() => {
    const rows = contractDoc.value?.assets;
    return Array.isArray(rows) ? rows : [];
});

const includedScope = computed(() => {
    const rows = contractDoc.value?.included_scope;
    return Array.isArray(rows) ? rows : [];
});

const excludedScope = computed(() => {
    const rows = contractDoc.value?.excluded_scope;
    return Array.isArray(rows) ? rows : [];
});

const detailUrl = computed(() => {
    try {
        return route("appraisal.show", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}`;
    }
});

const signUrl = computed(() => {
    try {
        return route("appraisal.contract.sign", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/kontrak/sign`;
    }
});

const downloadPdfUrl = computed(() => {
    try {
        return route("appraisal.contract.pdf", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/kontrak/pdf`;
    }
});

const paymentPageUrl = computed(() => {
    try {
        return route("appraisal.payment.page", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/pembayaran`;
    }
});

const goBack = () => {
    router.visit(detailUrl.value);
};

const downloadContractPdf = () => {
    if (!req.value?.id) return;
    window.open(downloadPdfUrl.value, "_blank", "noopener,noreferrer");
};

const goToPaymentPage = () => {
    if (!req.value?.id) return;
    router.visit(paymentPageUrl.value);
};

const formatIDR = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return "-";

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
    }).format(n);
};

const submitSignature = () => {
    if (!canSign.value || signing.value || hasAgreedToSign.value !== true) return;

    signing.value = true;
    router.post(signUrl.value, {
        agree_contract: hasAgreedToSign.value === true,
    }, {
        preserveScroll: true,
        onFinish: () => {
            signing.value = false;
        },
    });
};
</script>

<template>
    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold">Tanda Tangan Kontrak</h1>
                        <Badge variant="secondary">{{ req.request_number ?? '-' }}</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Finalisasi persetujuan kontrak penugasan appraisal.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" @click="downloadContractPdf">
                        <Download class="mr-2 h-4 w-4" />
                        Download PDF
                    </Button>
                    <Button variant="outline" @click="goBack">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Kembali ke Detail
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ contractDoc.title ?? "PENAWARAN LAYANAN ESTIMASI RENTANG HARGA PROPERTI" }}
                    </CardTitle>
                    <CardDescription>
                        {{ contractDoc.subtitle ?? "(Tanpa Inspeksi Lapangan - Non-Reliance)" }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6 text-sm">
                    <div class="grid grid-cols-1 gap-3 rounded-xl border p-4 md:grid-cols-2">
                        <div>
                            <div class="text-xs text-muted-foreground">No</div>
                            <div class="font-medium">{{ contractDoc.agr_no ?? req.contract_number ?? "-" }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Tanggal</div>
                            <div class="font-medium">{{ contractDoc.date ?? req.contract_date ?? "-" }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Kepada</div>
                            <div class="font-medium">{{ contractDoc.user_name ?? req.client_name ?? "-" }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">ID Permohonan</div>
                            <div class="font-medium">{{ contractDoc.request_id ?? req.request_number ?? "-" }}</div>
                        </div>
                    </div>

                    <p class="text-sm leading-relaxed">
                        DigiPro menyampaikan penawaran layanan Estimasi Rentang Harga Properti berdasarkan dokumen, foto, dan informasi
                        yang diunggah pengguna serta data pembanding pada Bank Data DigiPro. Layanan ini dilakukan tanpa inspeksi lapangan
                        dan tanpa pengukuran fisik. Hasil layanan berupa rentang estimasi (batas bawah - batas atas), bukan nilai tunggal/final.
                    </p>

                    <section class="space-y-2">
                        <h3 class="font-semibold">A. Daftar Aset</h3>
                        <div class="overflow-x-auto rounded-xl border">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-slate-700">No</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-700">Nama/Label Aset</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-700">Lokasi Singkat</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-700">Dokumen Utama</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-700">Luas (basis)</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-700">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-if="!assetRows.length">
                                        <td colspan="6" class="px-3 py-3 text-slate-500">Belum ada data aset.</td>
                                    </tr>
                                    <tr v-for="row in assetRows" :key="row.no">
                                        <td class="px-3 py-2 align-top">{{ row.no ?? "-" }}</td>
                                        <td class="px-3 py-2 align-top">{{ row.label ?? "-" }}</td>
                                        <td class="px-3 py-2 align-top">{{ row.address ?? "-" }}</td>
                                        <td class="px-3 py-2 align-top">{{ row.main_documents ?? "-" }}</td>
                                        <td class="px-3 py-2 align-top">{{ row.area_basis ?? "-" }}</td>
                                        <td class="px-3 py-2 align-top">{{ row.note ?? "-" }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Catatan basis luas: DOC = luas berdasarkan dokumen yang diunggah; USER = luas berdasarkan input pengguna.
                        </p>
                    </section>

                    <section class="space-y-2">
                        <h3 class="font-semibold">B. Ruang Lingkup Layanan</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="rounded-xl border p-3">
                                <div class="mb-2 text-sm font-medium">Termasuk</div>
                                <ul class="list-disc space-y-1 pl-5 text-xs text-slate-700">
                                    <li v-for="item in includedScope" :key="`included-${item}`">{{ item }}</li>
                                </ul>
                            </div>
                            <div class="rounded-xl border p-3">
                                <div class="mb-2 text-sm font-medium">Tidak termasuk</div>
                                <ul class="list-disc space-y-1 pl-5 text-xs text-slate-700">
                                    <li v-for="item in excludedScope" :key="`excluded-${item}`">{{ item }}</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-2">
                        <h3 class="font-semibold">C. Output</h3>
                        <p class="text-sm text-slate-700">{{ contractDoc.output_text ?? "-" }}</p>
                    </section>

                    <section class="space-y-2">
                        <h3 class="font-semibold">D. Waktu Penyelesaian (SLA)</h3>
                        <p class="text-sm text-slate-700">{{ contractDoc.sla_text ?? "-" }}</p>
                    </section>

                    <section class="space-y-2">
                        <h3 class="font-semibold">E. Biaya</h3>
                        <div class="overflow-x-auto rounded-xl border">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-slate-700">Komponen</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-700">Nilai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr>
                                        <td class="px-3 py-2">Biaya layanan per aset</td>
                                        <td class="px-3 py-2">{{ formatIDR(contractDoc.fee_per_asset ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2">Jumlah aset</td>
                                        <td class="px-3 py-2">{{ contractDoc.asset_count ?? assetRows.length }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2 font-semibold">Total</td>
                                        <td class="px-3 py-2 font-semibold">{{ formatIDR(contractDoc.total_fee ?? req.fee_total ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2">Pajak</td>
                                        <td class="px-3 py-2">{{ contractDoc.tax_note ?? "-" }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2">Metode bayar</td>
                                        <td class="px-3 py-2">{{ contractDoc.payment_methods ?? "-" }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="space-y-2">
                        <h3 class="font-semibold">F. Pernyataan Kunci (Non-Reliance)</h3>
                        <p class="text-sm text-slate-700">{{ contractDoc.statement_text ?? "-" }}</p>
                    </section>

                    <section class="space-y-2 rounded-xl border p-4">
                        <div class="text-sm font-medium">Persetujuan Pengguna (Mock E-Sign)</div>
                        <div class="grid grid-cols-1 gap-2 text-xs text-slate-700 md:grid-cols-2">
                            <div>Nama: <span class="font-medium text-slate-900">{{ contractDoc.user_name ?? "-" }}</span></div>
                            <div>ID/Email: <span class="font-medium text-slate-900">{{ contractDoc.user_identifier ?? "-" }}</span></div>
                            <div>Tanggal: <span class="font-medium text-slate-900">{{ contractDoc.accepted_at ?? "-" }}</span></div>
                            <div>Consent ID: <span class="font-medium text-slate-900">{{ contractDoc.consent_id ?? "-" }}</span></div>
                        </div>
                        <div class="h-16 rounded-md border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                            Area tanda tangan digital (mock).
                        </div>
                    </section>

                    <p class="text-xs text-muted-foreground">
                        {{ contractDoc.disclaimer_footer ?? "" }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Tindakan</CardTitle>
                    <CardDescription>Finalisasi persetujuan kontrak</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-if="canSign" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-3">
                        <div class="text-sm font-medium">Kontrak siap ditandatangani</div>
                        <p class="text-xs text-muted-foreground">
                            Dengan menekan tombol tanda tangan, Anda menyetujui dokumen dan status akan berubah menjadi Kontrak Ditandatangani.
                        </p>
                        <label class="flex items-start gap-2 rounded-lg border border-emerald-300 bg-white/80 p-3">
                            <Checkbox v-model="hasAgreedToSign" class="mt-0.5" />
                            <span class="text-xs text-slate-700">
                                Saya telah membaca, memahami, dan menyetujui seluruh isi dokumen penawaran ini sebagai persetujuan tanda tangan digital (mock).
                            </span>
                        </label>
                        <Button
                            class="bg-emerald-600 hover:bg-emerald-700"
                            :disabled="signing || hasAgreedToSign !== true"
                            @click="submitSignature"
                        >
                            <FilePenLine class="mr-2 h-4 w-4" />
                            {{ signing ? "Memproses..." : "Tanda Tangani Kontrak" }}
                        </Button>
                    </div>
                    <div v-else-if="signatureInfo?.is_signed" class="rounded-xl border border-sky-200 bg-sky-50 p-4 space-y-2 text-sm">
                        <div class="font-medium text-sky-900">Kontrak sudah ditandatangani (Mock)</div>
                        <div class="grid grid-cols-1 gap-1 text-xs text-sky-800 md:grid-cols-2">
                            <div>Waktu: {{ signatureInfo.signed_at ?? "-" }}</div>
                            <div>Signer: {{ signatureInfo.signed_by_name ?? "-" }}</div>
                            <div>Email: {{ signatureInfo.signed_by_email ?? "-" }}</div>
                            <div>Signature ID: {{ signatureInfo.signature_id ?? "-" }}</div>
                            <div class="md:col-span-2">Hash Dokumen: {{ signatureInfo.document_hash ?? "-" }}</div>
                        </div>
                        <div>
                            <Button @click="goToPaymentPage">Lanjut ke Pembayaran</Button>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border p-4 text-sm text-muted-foreground">
                        Kontrak belum berada pada tahap tanda tangan atau data tanda tangan belum tersedia.
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
