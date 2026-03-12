<script setup>
import axios from "axios";
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";
import { useNotification } from "@/composables/useNotification";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { ArrowLeft, CreditCard, RefreshCw, ShieldCheck, Wallet, Clock3, AlertTriangle } from "lucide-vue-next";

const props = defineProps({
    request: { type: Object, default: () => ({}) },
    payment: { type: Object, default: () => ({}) },
    midtrans: { type: Object, default: () => ({}) },
    canStartCheckout: { type: Boolean, default: false },
    legacyManualMessage: { type: String, default: null },
});

const { notify } = useNotification();
const clonePayment = (value) => JSON.parse(JSON.stringify(value ?? {}));

const paymentState = ref(clonePayment(props.payment));
const payLoading = ref(false);
const switchMethodLoading = ref(false);
const refreshLoading = ref(false);
const snapReady = ref(false);

watch(
    () => props.payment,
    (value) => {
        paymentState.value = clonePayment(value);
    },
    { deep: true }
);

const detailUrl = computed(() => {
    try {
        return route("appraisal.show", props.request?.id);
    } catch (_) {
        return `/permohonan-penilaian/${props.request?.id}`;
    }
});

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

const paymentStatusVariant = computed(() => {
    const status = String(paymentState.value?.status || "").toLowerCase();
    if (status === "paid") return "default";
    if (["failed", "rejected"].includes(status)) return "destructive";
    if (status === "expired") return "outline";
    return "secondary";
});

const checkout = computed(() => paymentState.value?.checkout ?? {});
const gatewayDetails = computed(() => paymentState.value?.gateway_details ?? {});

const canPayNow = computed(() => {
    return Boolean(
        props.canStartCheckout &&
            props.midtrans?.configured &&
            !paymentState.value?.is_legacy_manual &&
            !payLoading.value &&
            !switchMethodLoading.value
    );
});

const canSwitchMethod = computed(() => {
    return Boolean(
        props.canStartCheckout &&
            props.midtrans?.configured &&
            paymentState.value?.status === "pending" &&
            paymentState.value?.checkout?.snap_token &&
            !paymentState.value?.is_legacy_manual &&
            !payLoading.value &&
            !switchMethodLoading.value
    );
});

const refreshPage = () => {
    refreshLoading.value = true;
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            refreshLoading.value = false;
        },
    });
};

const goBack = () => {
    router.visit(detailUrl.value);
};

const loadSnapScript = async () => {
    if (typeof window === "undefined") return;
    if (window.snap) {
        snapReady.value = true;
        return;
    }

    const existing = document.getElementById("midtrans-snap-js");
    if (existing) {
        await new Promise((resolve) => window.setTimeout(resolve, 100));
        snapReady.value = Boolean(window.snap);
        return;
    }

    await new Promise((resolve, reject) => {
        const script = document.createElement("script");
        script.id = "midtrans-snap-js";
        script.src = props.midtrans?.snap_script_url;
        script.setAttribute("data-client-key", props.midtrans?.client_key ?? "");
        script.async = true;
        script.onload = resolve;
        script.onerror = reject;
        document.body.appendChild(script);
    });

    snapReady.value = Boolean(window.snap);
};

const handleCallback = (type, message) => {
    notify(type, message);
    window.setTimeout(() => {
        refreshPage();
    }, 1200);
};

const openSnap = (token) => {
    if (!window.snap) {
        notify("error", "Snap Midtrans belum siap dimuat.");
        return;
    }

    window.snap.pay(token, {
        onSuccess: () => handleCallback("success", "Pembayaran berhasil diproses. Menunggu sinkronisasi status."),
        onPending: () => handleCallback("info", "Pembayaran masih menunggu penyelesaian."),
        onError: () => handleCallback("error", "Terjadi kendala saat memproses pembayaran."),
        onClose: () => {
            notify("info", "Popup pembayaran ditutup.");
            refreshPage();
        },
    });
};

const startCheckout = async ({ forceNewAttempt = false } = {}) => {
    if ((forceNewAttempt && !canSwitchMethod.value) || (!forceNewAttempt && !canPayNow.value)) {
        notify("warning", "Session pembayaran belum bisa dimulai.");
        return;
    }

    if (forceNewAttempt) {
        switchMethodLoading.value = true;
    } else {
        payLoading.value = true;
    }

    try {
        await loadSnapScript();
        const { data } = await axios.post(props.midtrans?.create_session_url, {
            force_new_attempt: forceNewAttempt,
        });
        if (data?.payment) {
            paymentState.value = data.payment;
        }

        const token = data?.payment?.checkout?.snap_token;
        if (!token) {
            notify("error", data?.message || "Snap token tidak tersedia.");
            return;
        }

        openSnap(token);
    } catch (error) {
        const message = error?.response?.data?.message || "Gagal membuat session pembayaran Midtrans.";
        notify("error", message);
    } finally {
        payLoading.value = false;
        switchMethodLoading.value = false;
    }
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
                        <Badge :variant="paymentStatusVariant">{{ paymentState.status_label ?? "Menunggu Pembayaran" }}</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Pembayaran utama DigiPro diproses melalui Midtrans Snap.
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
                <CardContent class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Nomor Kontrak</div>
                        <div class="font-medium">{{ request.contract_number ?? "-" }}</div>
                    </div>
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Total Tagihan</div>
                        <div class="font-semibold">{{ formatIDR(paymentState.amount ?? request.fee_total) }}</div>
                    </div>
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Status Pembayaran</div>
                        <div class="font-medium">{{ paymentState.status_label ?? "-" }}</div>
                    </div>
                    <div class="rounded-xl border p-3">
                        <div class="text-xs text-muted-foreground">Order ID</div>
                        <div class="font-medium break-all">{{ paymentState.external_payment_id ?? "-" }}</div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Midtrans Checkout</CardTitle>
                    <CardDescription>VA, QRIS, dan e-wallet ditampilkan sesuai channel yang aktif</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="legacyManualMessage"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        {{ legacyManualMessage }}
                    </div>

                    <div
                        v-else-if="!midtrans?.configured"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        Konfigurasi Midtrans belum lengkap. Isi credential sandbox/production di environment aplikasi.
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="rounded-xl border p-4 space-y-2">
                            <div class="flex items-center gap-2 font-medium">
                                <Wallet class="h-4 w-4 text-slate-500" />
                                {{ paymentState.method ?? "Midtrans Snap" }}
                            </div>
                            <div class="text-xs text-muted-foreground">Nomor Invoice</div>
                            <div class="font-medium">{{ paymentState.invoice_number ?? "-" }}</div>
                            <div class="text-xs text-muted-foreground">Kedaluwarsa Session</div>
                            <div class="font-medium">{{ formatDateTime(checkout.expires_at || gatewayDetails.expiry_time) }}</div>
                        </div>

                        <div class="rounded-xl border p-4 space-y-2">
                            <div class="flex items-center gap-2 font-medium">
                                <ShieldCheck class="h-4 w-4 text-slate-500" />
                                Detail Channel
                            </div>
                            <div class="text-xs text-muted-foreground">Channel</div>
                            <div class="font-medium">{{ gatewayDetails.label ?? "Midtrans Snap" }}</div>
                            <div class="text-xs text-muted-foreground">Referensi / VA</div>
                            <div class="font-medium break-all">{{ gatewayDetails.reference ?? "-" }}</div>
                        </div>
                    </div>

                    <div class="rounded-xl border bg-slate-50 p-4 text-sm text-slate-700">
                        <div class="flex items-center gap-2 font-medium">
                            <Clock3 class="h-4 w-4" />
                            Status pembayaran akan difinalkan otomatis lewat webhook Midtrans.
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Setelah transaksi sukses di Midtrans, DigiPro akan menyinkronkan status pembayaran dan mengaktifkan invoice secara otomatis.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs text-muted-foreground">
                            Gunakan tombol bayar untuk membuka popup Snap. Jika channel aktif bermasalah, buat sesi baru untuk memilih metode bayar lain.
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" :disabled="refreshLoading" @click="refreshPage">
                                <RefreshCw class="mr-2 h-4 w-4" />
                                {{ refreshLoading ? "Menyegarkan..." : "Refresh Status" }}
                            </Button>
                            <Button
                                v-if="canSwitchMethod"
                                variant="outline"
                                :disabled="!canSwitchMethod"
                                @click="startCheckout({ forceNewAttempt: true })"
                            >
                                <RefreshCw class="mr-2 h-4 w-4" />
                                {{ switchMethodLoading ? "Mengganti Metode..." : "Ganti Metode Bayar" }}
                            </Button>
                            <Button :disabled="!canPayNow" @click="startCheckout()">
                                <CreditCard class="mr-2 h-4 w-4" />
                                {{ payLoading ? "Membuka Midtrans..." : (paymentState.status === "pending" ? "Lanjutkan Pembayaran" : "Bayar Sekarang") }}
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="paymentState.status === 'expired' || paymentState.status === 'failed'"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        Sesi pembayaran sebelumnya tidak aktif lagi. Klik <span class="font-medium">Bayar Sekarang</span> untuk membuat sesi baru.
                    </div>

                    <div
                        v-if="paymentState.status === 'pending' && paymentState.external_payment_id"
                        class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900"
                    >
                        Pembayaran masih menunggu penyelesaian. Order ID:
                        <span class="font-medium break-all">{{ paymentState.external_payment_id }}</span>
                        <p class="mt-1 text-xs text-sky-800">
                            Jika QRIS, e-wallet, atau VA yang sedang aktif bermasalah, gunakan tombol
                            <span class="font-medium">Ganti Metode Bayar</span> untuk membuat order baru dan memilih channel lain di Snap.
                        </p>
                    </div>

                    <div
                        v-if="paymentState.is_legacy_manual"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        <div class="flex items-center gap-2 font-medium">
                            <AlertTriangle class="h-4 w-4" />
                            Record manual historis
                        </div>
                        <p class="mt-1 text-xs text-amber-800">
                            Request ini masih memiliki pembayaran manual lama dan tidak dibuka ulang ke Midtrans secara otomatis.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </UserDashboardLayout>
</template>
