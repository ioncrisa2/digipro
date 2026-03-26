<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import { useNotification } from "@/composables/useNotification";
import { useDialogStore } from "@/stores/dialogStore";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { ArrowLeft, FileText, HandCoins, XCircle, Clock3 } from "lucide-vue-next";
import { useAppraisalRequestShow } from "@/composables/useAppraisalRequestShow";

const props = defineProps({
    request: { type: Object, default: null },
});

const { req, statusLabel, statusVariant, formatIDR } = useAppraisalRequestShow(props);
const { notify } = useNotification();
const dialog = useDialogStore();

const MAX_NEGOTIATION_ROUNDS = 3;
const ADMIN_RESPONSE_TARGET_MINUTES = 5;

const acceptActionLoading = ref(false);
const negotiationDialogOpen = ref(false);
const negotiationSubmitLoading = ref(false);
const selectOfferLoading = ref(false);
const cancelRequestLoading = ref(false);

const negotiationReason = ref("");
const negotiationExpectedFee = ref("");
const negotiationReasonError = ref("");
const negotiationExpectedFeeError = ref("");
const selectedOfferOptionId = ref(null);

const hasOffer = computed(() => !!req.value?.contract_number);
const showAcceptOfferButton = computed(() => req.value?.status === "offer_sent");
const showWaitingOfferState = computed(() => req.value?.status === "waiting_offer");
const showSignContractButton = computed(() => req.value?.status === "waiting_signature");

const negotiationRoundsUsed = computed(() => {
    const parsed = Number(req.value?.negotiation_rounds_used ?? 0);
    return Number.isFinite(parsed) && parsed > 0 ? Math.trunc(parsed) : 0;
});
const negotiationRoundsRemaining = computed(() => Math.max(0, MAX_NEGOTIATION_ROUNDS - negotiationRoundsUsed.value));
const hasReachedNegotiationLimit = computed(() => negotiationRoundsUsed.value >= MAX_NEGOTIATION_ROUNDS);
const showFinalDecisionSection = computed(() => showAcceptOfferButton.value && hasReachedNegotiationLimit.value);
const canSubmitNegotiation = computed(() => showAcceptOfferButton.value && negotiationRoundsRemaining.value > 0);

const offerNegotiations = computed(() => {
    return Array.isArray(req.value?.offer_negotiations) ? req.value.offer_negotiations : [];
});

const latestCounterRequest = computed(() => {
    const counters = offerNegotiations.value.filter((item) => item?.action === "counter_request");
    return counters.length ? counters[counters.length - 1] : null;
});

const toFeeInteger = (value) => {
    if (value === null || value === undefined || value === "") return null;
    if (typeof value === "number") {
        return Number.isFinite(value) && value >= 0 ? Math.trunc(value) : null;
    }
    const digitsOnly = String(value).replace(/[^\d]/g, "");
    if (!digitsOnly) return null;
    const parsed = Number(digitsOnly);
    return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
};

const normalizeFirstErrorMessage = (value) => {
    if (!value) return "";
    if (typeof value === "string") return value;
    if (Array.isArray(value)) return normalizeFirstErrorMessage(value[0]);
    if (typeof value === "object") {
        const firstValue = Object.values(value)[0];
        return normalizeFirstErrorMessage(firstValue);
    }
    return "";
};

const firstValidationMessage = (errors) => {
    const entries = Object.entries(errors || {});
    if (!entries.length) return "";
    return normalizeFirstErrorMessage(entries[0][1]);
};

const offerOptions = computed(() => {
    const rawOptions = Array.isArray(req.value?.offer_fee_options) ? req.value.offer_fee_options : [];
    const normalized = rawOptions
        .map((item) => {
            const feeTotal = toFeeInteger(item?.fee_total);
            if (feeTotal === null) return null;
            return {
                id: item?.id ? String(item.id) : `fee-${feeTotal}`,
                feeTotal,
            };
        })
        .filter(Boolean);

    if (!normalized.length) {
        const fallbackFee = toFeeInteger(req.value?.fee_total);
        if (fallbackFee !== null) {
            normalized.push({
                id: `fee-${fallbackFee}`,
                feeTotal: fallbackFee,
            });
        }
    }

    const seen = new Set();
    return normalized
        .filter((option) => {
            if (seen.has(option.feeTotal)) return false;
            seen.add(option.feeTotal);
            return true;
        })
        .sort((a, b) => b.feeTotal - a.feeTotal);
});

const currentFeeValue = computed(() => toFeeInteger(req.value?.fee_total));
const selectedOfferOption = computed(() => offerOptions.value.find((item) => item.id === selectedOfferOptionId.value) ?? null);

watch(
    offerOptions,
    (options) => {
        if (!options.length) {
            selectedOfferOptionId.value = null;
            return;
        }

        if (selectedOfferOptionId.value && options.some((item) => item.id === selectedOfferOptionId.value)) {
            return;
        }

        if (currentFeeValue.value !== null) {
            const currentOption = options.find((item) => item.feeTotal === currentFeeValue.value);
            if (currentOption) {
                selectedOfferOptionId.value = currentOption.id;
                return;
            }
        }

        selectedOfferOptionId.value = options[0].id;
    },
    { immediate: true }
);

const detailUrl = computed(() => {
    try {
        return route("appraisal.show", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}`;
    }
});

const offerAcceptUrl = computed(() => {
    try {
        return route("appraisal.offer.accept", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/offer/accept`;
    }
});

const offerNegotiateUrl = computed(() => {
    try {
        return route("appraisal.offer.negotiate", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/offer/negotiate`;
    }
});

const offerSelectUrl = computed(() => {
    try {
        return route("appraisal.offer.select", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/offer/select`;
    }
});

const offerCancelUrl = computed(() => {
    try {
        return route("appraisal.offer.cancel", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/offer/cancel`;
    }
});

const contractPageUrl = computed(() => {
    try {
        return route("appraisal.contract.page", req.value?.id);
    } catch (_) {
        return `/permohonan-penilaian/${req.value?.id}/kontrak`;
    }
});

const goBack = () => {
    router.visit(detailUrl.value);
};

const acceptOffer = () => {
    if (!req.value?.id || acceptActionLoading.value || !showAcceptOfferButton.value) return;

    acceptActionLoading.value = true;
    router.post(offerAcceptUrl.value, {}, {
        preserveScroll: true,
        onError: (errors) => {
            notify("error", firstValidationMessage(errors) || "Gagal menyetujui penawaran.");
        },
        onFinish: () => {
            acceptActionLoading.value = false;
        },
    });
};

const goContractPage = () => {
    if (!req.value?.id) return;
    router.visit(contractPageUrl.value);
};

const resetNegotiationForm = () => {
    negotiationReason.value = "";
    negotiationExpectedFee.value = "";
    negotiationReasonError.value = "";
    negotiationExpectedFeeError.value = "";
};

const openNegotiationDialog = () => {
    if (!canSubmitNegotiation.value) {
        notify("warning", "Kuota negosiasi sudah habis. Pilih penawaran terakhir atau batalkan permohonan.");
        return;
    }
    resetNegotiationForm();
    negotiationDialogOpen.value = true;
};

const submitNegotiation = () => {
    if (!req.value?.id || negotiationSubmitLoading.value || !canSubmitNegotiation.value) return;

    const reason = negotiationReason.value.trim();
    if (!reason) {
        negotiationReasonError.value = "Alasan keberatan wajib diisi.";
        return;
    }

    const expectedFee = toFeeInteger(negotiationExpectedFee.value);
    const payload = {
        reason,
        expected_fee: expectedFee,
    };

    negotiationSubmitLoading.value = true;
    negotiationReasonError.value = "";
    negotiationExpectedFeeError.value = "";

    router.post(offerNegotiateUrl.value, payload, {
        preserveScroll: true,
        onSuccess: () => {
            negotiationDialogOpen.value = false;
            resetNegotiationForm();
        },
        onError: (errors) => {
            const reasonError = normalizeFirstErrorMessage(errors?.reason);
            const expectedFeeError = normalizeFirstErrorMessage(errors?.expected_fee);
            if (reasonError) {
                negotiationReasonError.value = reasonError;
            }
            if (expectedFeeError) {
                negotiationExpectedFeeError.value = expectedFeeError;
            }
            notify("error", firstValidationMessage(errors) || "Gagal mengirim keberatan fee.");
        },
        onFinish: () => {
            negotiationSubmitLoading.value = false;
        },
    });
};

const continueWithSelectedOffer = async () => {
    if (!req.value?.id || selectOfferLoading.value) return;
    if (!selectedOfferOption.value) {
        notify("warning", "Pilih salah satu penawaran fee terlebih dahulu.");
        return;
    }

    const confirmed = await dialog.confirm({
        title: "Pakai Penawaran Terpilih?",
        description: `Anda akan melanjutkan dengan fee ${formatIDR(selectedOfferOption.value.feeTotal)}.`,
        confirmText: "Ya, Lanjutkan",
        cancelText: "Batal",
    });
    if (!confirmed) return;

    selectOfferLoading.value = true;
    router.post(
        offerSelectUrl.value,
        {
            selected_fee: selectedOfferOption.value.feeTotal,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                notify("error", firstValidationMessage(errors) || "Gagal memilih penawaran.");
            },
            onFinish: () => {
                selectOfferLoading.value = false;
            },
        }
    );
};

const cancelRequest = async () => {
    if (!req.value?.id || cancelRequestLoading.value) return;

    const confirmed = await dialog.confirmDestruct({
        title: "Batalkan Permohonan?",
        description: "Permohonan akan dibatalkan jika Anda tidak melanjutkan penawaran ini.",
        confirmText: "Ya, Batalkan",
        cancelText: "Kembali",
    });
    if (!confirmed) return;

    cancelRequestLoading.value = true;
    router.post(offerCancelUrl.value, {}, {
        preserveScroll: true,
        onError: (errors) => {
            notify("error", firstValidationMessage(errors) || "Gagal membatalkan permohonan.");
        },
        onFinish: () => {
            cancelRequestLoading.value = false;
        },
    });
};

const negotiationActionLabel = (action) => {
    switch (action) {
        case "counter_request":
            return "Keberatan Fee";
        case "offer_sent":
            return "Penawaran Dikirim";
        case "offer_revised":
            return "Penawaran Direvisi";
        case "accept_offer":
        case "accepted":
            return "Setuju Penawaran";
        case "cancel_request":
            return "Permohonan Dibatalkan";
        default:
            return action || "-";
    }
};

const negotiationActionClass = (action) => {
    switch (action) {
        case "counter_request":
            return "border-amber-200 bg-amber-50 text-amber-700";
        case "offer_sent":
        case "offer_revised":
            return "border-sky-200 bg-sky-50 text-sky-700";
        case "accept_offer":
        case "accepted":
            return "border-emerald-200 bg-emerald-50 text-emerald-700";
        case "cancel_request":
            return "border-red-200 bg-red-50 text-red-700";
        default:
            return "border-slate-200 bg-slate-50 text-slate-700";
    }
};
</script>

<template>
    <DashboardLayout>
        <div class="mx-auto max-w-4xl space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold">Penawaran</h1>
                        <Badge variant="secondary">{{ req.request_number }}</Badge>
                        <Badge :variant="statusVariant">{{ statusLabel }}</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Ringkasan penawaran biaya dan langkah persetujuan kontrak.
                    </p>
                </div>

                <Button variant="outline" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Kembali ke Detail
                </Button>
            </div>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Detail Penawaran</CardTitle>
                    <CardDescription>Nomor penawaran, masa berlaku, dan nilai fee</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="!hasOffer" class="rounded-xl border p-4 text-sm text-muted-foreground">
                        Penawaran belum tersedia. Admin belum mengirimkan nomor kontrak dan fee.
                    </div>

                    <div v-else class="space-y-4">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div class="rounded-xl border p-3">
                                <div class="text-xs text-muted-foreground">Nomor Penawaran</div>
                                <div class="font-medium">{{ req.contract_number ?? "-" }}</div>
                            </div>
                            <div class="rounded-xl border p-3">
                                <div class="text-xs text-muted-foreground">Tanggal</div>
                                <div class="font-medium">{{ req.contract_date ?? "-" }}</div>
                            </div>
                            <div class="rounded-xl border p-3">
                                <div class="text-xs text-muted-foreground">Masa Berlaku</div>
                                <div class="font-medium">{{ req.offer_validity_days ? `${req.offer_validity_days} hari` : "-" }}</div>
                            </div>
                        </div>

                        <div class="rounded-xl border p-4 bg-white/80">
                            <div class="text-xs text-muted-foreground">Total Fee</div>
                            <div class="mt-1 text-2xl font-semibold">
                                {{ req.fee_total != null ? formatIDR(req.fee_total) : "-" }}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Persetujuan</CardTitle>
                    <CardDescription>Tindak lanjut dari user terhadap penawaran</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-if="showAcceptOfferButton" class="space-y-4">
                        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                            Tim admin standby. Target respon keberatan fee maksimal {{ ADMIN_RESPONSE_TARGET_MINUTES }} menit pada jam operasional.
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-3">
                                <div class="text-sm font-medium">Setujui Penawaran</div>
                                <p class="text-xs text-muted-foreground">
                                    Setelah disetujui, status akan berubah menjadi menunggu tanda tangan kontrak.
                                </p>
                                <Button
                                    class="bg-emerald-600 hover:bg-emerald-700"
                                    :disabled="acceptActionLoading"
                                    @click="acceptOffer"
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    {{ acceptActionLoading ? "Memproses..." : "Setuju Penawaran" }}
                                </Button>
                            </div>

                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
                                <div class="text-sm font-medium">Ajukan Keberatan Fee</div>
                                <p class="text-xs text-muted-foreground">
                                    Maksimal 3 putaran negosiasi. Alasan wajib, nominal harapan opsional.
                                </p>
                                <Button
                                    variant="outline"
                                    class="border-amber-300 bg-white text-amber-700 hover:bg-amber-100"
                                    :disabled="!canSubmitNegotiation"
                                    @click="openNegotiationDialog"
                                >
                                    <HandCoins class="mr-2 h-4 w-4" />
                                    Ajukan Keberatan
                                </Button>
                            </div>
                        </div>

                        <div class="rounded-xl border bg-slate-50 p-4 space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-sm font-medium">Kuota Negosiasi</div>
                                <Badge variant="outline">{{ negotiationRoundsUsed }} / {{ MAX_NEGOTIATION_ROUNDS }}</Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Sisa putaran: {{ negotiationRoundsRemaining }}.
                            </p>
                        </div>

                        <div v-if="showFinalDecisionSection" class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
                            <div class="space-y-1">
                                <div class="text-sm font-medium">Batas Negosiasi Tercapai</div>
                                <p class="text-xs text-muted-foreground">
                                    Negosiasi sudah mencapai 3 putaran. Pilih penawaran fee yang ingin Anda lanjutkan, atau batalkan permohonan.
                                </p>
                            </div>

                            <div v-if="!offerOptions.length" class="rounded-lg border bg-white p-3 text-sm text-muted-foreground">
                                Belum ada opsi penawaran yang bisa dipilih.
                            </div>

                            <div v-else class="space-y-2">
                                <label
                                    v-for="option in offerOptions"
                                    :key="option.id"
                                    class="flex cursor-pointer items-start justify-between gap-3 rounded-lg border bg-white px-3 py-2"
                                >
                                    <div class="flex items-start gap-2">
                                        <input
                                            v-model="selectedOfferOptionId"
                                            type="radio"
                                            :value="option.id"
                                            class="mt-1"
                                        />
                                        <div>
                                            <div class="text-sm font-medium">
                                                {{ option.feeTotal === currentFeeValue ? "Penawaran Saat Ini" : "Opsi Penawaran" }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-sm font-semibold">
                                        {{ formatIDR(option.feeTotal) }}
                                    </div>
                                </label>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button :disabled="!selectedOfferOptionId || selectOfferLoading" @click="continueWithSelectedOffer">
                                    {{ selectOfferLoading ? "Memproses..." : "Pakai Penawaran Terpilih" }}
                                </Button>
                                <Button variant="destructive" :disabled="cancelRequestLoading" @click="cancelRequest">
                                    <XCircle class="mr-2 h-4 w-4" />
                                    {{ cancelRequestLoading ? "Memproses..." : "Batalkan Permohonan" }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="showWaitingOfferState" class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-2">
                        <div class="flex items-center gap-2 text-sm font-medium text-amber-900">
                            <Clock3 class="h-4 w-4" />
                            Menunggu respon admin atas negosiasi
                        </div>
                        <p class="text-xs text-amber-800">
                            Keberatan fee Anda sedang diproses. Target respon admin sekitar {{ ADMIN_RESPONSE_TARGET_MINUTES }} menit pada jam operasional.
                        </p>
                        <p v-if="latestCounterRequest" class="text-xs text-amber-900">
                            Putaran terakhir: {{ latestCounterRequest.round || "-" }} |
                            Harapan fee: {{ latestCounterRequest.expected_fee != null ? formatIDR(latestCounterRequest.expected_fee) : "-" }}
                        </p>
                    </div>

                    <div v-else-if="showSignContractButton" class="rounded-xl border border-sky-200 bg-sky-50 p-4 space-y-3">
                        <div class="text-sm font-medium">Lanjutkan ke tanda tangan kontrak</div>
                        <p class="text-xs text-muted-foreground">
                            Penawaran sudah disetujui. Lanjutkan ke halaman tanda tangan kontrak.
                        </p>
                        <Button @click="goContractPage">Buka Halaman Tanda Tangan</Button>
                    </div>

                    <div v-else-if="req.status === 'contract_signed'" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                        Kontrak sudah ditandatangani. Menunggu verifikasi pembayaran.
                    </div>

                    <div v-else class="rounded-xl border p-4 text-sm text-muted-foreground">
                        Status penawaran saat ini: {{ req.status_label ?? statusLabel }}.
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Riwayat Negosiasi</CardTitle>
                    <CardDescription>Catatan perubahan penawaran dan keputusan</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="!offerNegotiations.length" class="rounded-xl border p-4 text-sm text-muted-foreground">
                        Belum ada riwayat negosiasi.
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="item in offerNegotiations"
                            :key="item.id"
                            class="rounded-xl border bg-white p-3"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span
                                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                    :class="negotiationActionClass(item.action)"
                                >
                                    {{ negotiationActionLabel(item.action) }}
                                </span>
                                <span class="text-xs text-muted-foreground">{{ item.created_at ?? "-" }}</span>
                            </div>

                            <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3 text-xs">
                                <div class="rounded-lg border px-2 py-1.5">
                                    <div class="text-muted-foreground">Putaran</div>
                                    <div class="font-medium">{{ item.round ?? "-" }}</div>
                                </div>
                                <div class="rounded-lg border px-2 py-1.5">
                                    <div class="text-muted-foreground">Fee Penawaran</div>
                                    <div class="font-medium">{{ item.offered_fee != null ? formatIDR(item.offered_fee) : "-" }}</div>
                                </div>
                                <div class="rounded-lg border px-2 py-1.5">
                                    <div class="text-muted-foreground">Fee Diharapkan</div>
                                    <div class="font-medium">{{ item.expected_fee != null ? formatIDR(item.expected_fee) : "-" }}</div>
                                </div>
                            </div>

                            <p v-if="item.reason" class="mt-2 text-xs text-slate-700">
                                {{ item.reason }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog :open="negotiationDialogOpen" @update:open="negotiationDialogOpen = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Ajukan Keberatan Fee</DialogTitle>
                    <DialogDescription>
                        Putaran negosiasi ke-{{ negotiationRoundsUsed + 1 }} dari {{ MAX_NEGOTIATION_ROUNDS }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <Label for="negotiation-reason">Alasan keberatan <span class="text-red-600">*</span></Label>
                        <Textarea
                            id="negotiation-reason"
                            v-model="negotiationReason"
                            placeholder="Contoh: menyesuaikan budget internal dan ruang lingkup pekerjaan."
                            class="min-h-24"
                        />
                        <p v-if="negotiationReasonError" class="text-xs text-red-600">
                            {{ negotiationReasonError }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="expected-fee">Nominal harapan (opsional)</Label>
                        <Input
                            id="expected-fee"
                            v-model="negotiationExpectedFee"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="Contoh: 1500000"
                        />
                        <p v-if="negotiationExpectedFeeError" class="text-xs text-red-600">
                            {{ negotiationExpectedFeeError }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Boleh dikosongkan jika hanya ingin menyampaikan alasan negosiasi.
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-end">
                    <Button variant="outline" @click="negotiationDialogOpen = false">
                        Batal
                    </Button>
                    <Button :disabled="negotiationSubmitLoading" @click="submitNegotiation">
                        {{ negotiationSubmitLoading ? "Menyimpan..." : "Kirim Keberatan" }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </DashboardLayout>
</template>
