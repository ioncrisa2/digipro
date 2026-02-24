<script setup>
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";
import { useNotification } from "@/composables/useNotification";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { ArrowLeft, Upload, CreditCard, Building2, FileCheck2 } from "lucide-vue-next";

const props = defineProps({
    request: { type: Object, default: () => ({}) },
    payment: { type: Object, default: () => ({}) },
    bankAccounts: { type: Array, default: () => [] },
    canUploadProof: { type: Boolean, default: false },
});

const { notify } = useNotification();

const uploadLoading = ref(false);
const proofFile = ref(null);
const transferNote = ref("");
const transferDate = ref("");
const transferAmount = ref("");

const initialSelectedBankId = computed(() => {
    const selected = props.payment?.selected_bank_account?.id;
    if (selected) return String(selected);
    if (props.bankAccounts.length > 0) return String(props.bankAccounts[0].id);
    return "";
});
const selectedBankAccountId = ref(initialSelectedBankId.value);

const detailUrl = computed(() => {
    try {
        return route("appraisal.show", props.request?.id);
    } catch (_) {
        return `/permohonan-penilaian/${props.request?.id}`;
    }
});

const uploadUrl = computed(() => {
    try {
        return route("appraisal.payment.upload", props.request?.id);
    } catch (_) {
        return `/permohonan-penilaian/${props.request?.id}/pembayaran/proof`;
    }
});

const formatIDR = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return "-";
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR" }).format(n);
};

const formatBytes = (bytes) => {
    const n = Number(bytes);
    if (!Number.isFinite(n) || n <= 0) return "-";
    const units = ["B", "KB", "MB", "GB"];
    const index = Math.min(Math.floor(Math.log(n) / Math.log(1024)), units.length - 1);
    const value = n / Math.pow(1024, index);
    return `${value.toFixed(index === 0 ? 0 : 2)} ${units[index]}`;
};

const paymentStatusVariant = computed(() => {
    const status = String(props.payment?.status || "").toLowerCase();
    if (status === "paid") return "default";
    if (["failed", "rejected"].includes(status)) return "destructive";
    return "secondary";
});

const selectedBank = computed(() => {
    return props.bankAccounts.find((item) => String(item.id) === String(selectedBankAccountId.value)) ?? null;
});

const canSubmit = computed(() => {
    return Boolean(
        props.canUploadProof &&
            selectedBankAccountId.value &&
            proofFile.value &&
            !uploadLoading.value
    );
});

const firstErrorMessage = (errors) => {
    if (!errors || typeof errors !== "object") return "";
    const firstKey = Object.keys(errors)[0];
    if (!firstKey) return "";
    const value = errors[firstKey];
    if (Array.isArray(value)) return value[0] ?? "";
    return typeof value === "string" ? value : "";
};

const handleFileChange = (event) => {
    const files = event?.target?.files;
    proofFile.value = files?.length ? files[0] : null;
};

const uploadProof = () => {
    if (!canSubmit.value) {
        notify("warning", "Lengkapi rekening tujuan dan file bukti pembayaran.");
        return;
    }

    uploadLoading.value = true;
    router.post(
        uploadUrl.value,
        {
            office_bank_account_id: selectedBankAccountId.value,
            proof_file: proofFile.value,
            transfer_note: transferNote.value?.trim() || null,
            transfer_date: transferDate.value || null,
            transfer_amount: transferAmount.value ? Number(String(transferAmount.value).replace(/[^\d]/g, "")) : null,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                notify("error", firstErrorMessage(errors) || "Gagal mengunggah bukti pembayaran.");
            },
            onFinish: () => {
                uploadLoading.value = false;
            },
        }
    );
};

const goBack = () => {
    router.visit(detailUrl.value);
};
</script>

<template>
    <UserDashboardLayout>
        <template #title>Pembayaran Permohonan</template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold">Pembayaran Permohonan</h1>
                        <Badge variant="outline">{{ request.request_number ?? "-" }}</Badge>
                        <Badge :variant="paymentStatusVariant">{{ payment.status_label ?? "-" }}</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Gunakan rekening resmi DigiPro di bawah ini lalu unggah bukti transfer Anda.
                    </p>
                </div>

                <Button variant="outline" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali ke Detail
                </Button>
            </div>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Ringkasan Tagihan</CardTitle>
                    <CardDescription>Informasi pembayaran berdasarkan kontrak yang sudah ditandatangani</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Nomor Kontrak</div>
                        <div class="font-medium">{{ request.contract_number ?? "-" }}</div>
                    </div>
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Total Tagihan</div>
                        <div class="font-semibold">{{ formatIDR(request.fee_total) }}</div>
                    </div>
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Status Pembayaran</div>
                        <div class="font-medium">{{ payment.status_label ?? "-" }}</div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Rekening Tujuan</CardTitle>
                    <CardDescription>Pilih salah satu rekening aktif kantor untuk transfer</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-if="!bankAccounts.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        Rekening kantor belum tersedia. Silakan hubungi admin.
                    </div>

                    <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label
                            v-for="account in bankAccounts"
                            :key="account.id"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4"
                            :class="String(selectedBankAccountId) === String(account.id)
                                ? 'border-emerald-300 bg-emerald-50'
                                : 'border-slate-200 bg-white'"
                        >
                            <input
                                v-model="selectedBankAccountId"
                                type="radio"
                                :value="String(account.id)"
                                class="mt-1"
                            />
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 text-sm font-medium">
                                    <Building2 class="h-4 w-4 text-slate-500" />
                                    {{ account.bank_name }}
                                </div>
                                <div class="mt-1 font-mono text-sm">{{ account.account_number }}</div>
                                <div class="text-xs text-muted-foreground">a.n. {{ account.account_holder }}</div>
                                <div v-if="account.branch" class="text-xs text-muted-foreground">{{ account.branch }}</div>
                            </div>
                        </label>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Upload Bukti Pembayaran</CardTitle>
                    <CardDescription>Format file: PDF/JPG/JPEG/PNG, maksimal 15 MB</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="payment.proof_file_path" class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 space-y-1">
                        <div class="flex items-center gap-2 font-medium">
                            <FileCheck2 class="h-4 w-4" />
                            Bukti pembayaran sudah diunggah
                        </div>
                        <div>{{ payment.proof_original_name ?? "-" }} ({{ formatBytes(payment.proof_size) }})</div>
                        <a
                            v-if="payment.proof_url"
                            :href="payment.proof_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex text-sm underline"
                        >
                            Lihat bukti pembayaran
                        </a>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="transfer-amount">Nominal transfer (opsional)</Label>
                            <Input id="transfer-amount" v-model="transferAmount" type="number" min="0" placeholder="Contoh: 1800000" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="transfer-date">Tanggal transfer (opsional)</Label>
                            <Input id="transfer-date" v-model="transferDate" type="date" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="transfer-note">Catatan transfer (opsional)</Label>
                        <Textarea
                            id="transfer-note"
                            v-model="transferNote"
                            class="min-h-20"
                            placeholder="Contoh: transfer via mobile banking, nama pengirim berbeda."
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="proof-file">File bukti pembayaran <span class="text-red-600">*</span></Label>
                        <Input id="proof-file" type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleFileChange" />
                        <p v-if="proofFile" class="text-xs text-muted-foreground">
                            File terpilih: {{ proofFile.name }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs text-muted-foreground">
                            Rekening terpilih:
                            <span class="font-medium text-slate-700">
                                {{ selectedBank ? `${selectedBank.bank_name} - ${selectedBank.account_number}` : "-" }}
                            </span>
                        </div>
                        <Button :disabled="!canSubmit" @click="uploadProof">
                            <Upload class="mr-2 h-4 w-4" />
                            {{ uploadLoading ? "Mengunggah..." : "Upload Bukti Pembayaran" }}
                        </Button>
                    </div>

                    <div v-if="!canUploadProof" class="rounded-xl border p-3 text-xs text-muted-foreground">
                        Upload bukti pembayaran belum tersedia pada status permohonan saat ini.
                    </div>
                </CardContent>
            </Card>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <div class="flex items-center gap-2 font-medium">
                    <CreditCard class="h-4 w-4" />
                    Catatan
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Setelah bukti pembayaran diunggah, status akan menunggu verifikasi admin. Proses penilaian dimulai setelah pembayaran terverifikasi.
                </p>
            </div>
        </div>
    </UserDashboardLayout>
</template>
