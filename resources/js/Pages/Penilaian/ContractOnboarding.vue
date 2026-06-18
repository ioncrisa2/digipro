<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import StepProgress from "@/components/base/StepProgress.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { ArrowLeft, CircleAlert, CircleCheck, RefreshCw, ShieldCheck, Smartphone } from "lucide-vue-next";

const props = defineProps({
    request: { type: Object, required: true },
    profile: { type: Object, required: true },
    readiness: { type: Object, default: () => ({}) },
    friendly: { type: Object, default: () => ({}) },
    references: { type: Object, default: () => ({}) },
    actions: { type: Object, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const provinces = computed(() => Array.isArray(props.references?.provinces) ? props.references.provinces : []);
const cities = computed(() => Array.isArray(props.references?.cities) ? props.references.cities : []);
const referencesError = computed(() => props.references?.error ?? null);
const keylaQr = computed(() => props.profile?.keyla_qr_image ?? props.readiness?.keyla_qr_image ?? null);

const fallbackStepLabels = ["Data Diri", "Rekam Wajah", "Tanda Tangan", "Aplikasi HP"];
const stepLabels = computed(() => Array.isArray(props.friendly?.step_labels) && props.friendly.step_labels.length
    ? props.friendly.step_labels
    : fallbackStepLabels);
const currentStep = ref(1);

const identityForm = useForm({
    is_wna: props.profile?.is_wna ? "1" : "0",
    peruri_email: props.profile?.peruri_email ?? "",
    peruri_phone: props.profile?.peruri_phone ?? "",
    nik: props.profile?.nik ?? "",
    reference_province_id: props.profile?.reference_province_id ?? "",
    reference_city_id: props.profile?.reference_city_id ?? "",
    gender: props.profile?.gender ?? "",
    place_of_birth: props.profile?.place_of_birth ?? "",
    date_of_birth: props.profile?.date_of_birth ?? "",
    address: props.profile?.address ?? "",
    ktp_photo: null,
});

const kycForm = useForm({ kyc_video: null });
const specimenForm = useForm({ signature_image: null });

const liveVideoRef = ref(null);
const signatureCanvasRef = ref(null);
const recordedVideoUrl = ref(null);
const mediaStream = ref(null);
const mediaRecorder = ref(null);
const recordedChunks = ref([]);
const isCameraReady = ref(false);
const isPreparingCamera = ref(false);
const isRecording = ref(false);
const isDrawing = ref(false);
const hasSignatureStroke = ref(false);

const registrationDone = computed(() => props.readiness?.registration?.is_ready === true);
const kycDone = computed(() => props.readiness?.kyc?.is_ready === true);
const specimenDone = computed(() => props.readiness?.specimen?.is_ready === true);
const certificateState = computed(() => props.readiness?.certificate ?? {});
const certificateReady = computed(() => certificateState.value?.is_ready === true);
const accountVerificationMessage = computed(() => certificateReady.value
    ? (props.friendly?.account_verification_message ?? "Akun tanda tangan Anda sudah selesai diverifikasi.")
    : (props.friendly?.account_verification_message ?? "Akun Anda masih diverifikasi oleh Peruri. Tekan cek status secara berkala sebelum menghubungkan aplikasi HP."));
const keylaDone = computed(() => props.readiness?.keyla?.is_ready === true);
const overallReady = computed(() => props.readiness?.overall?.is_ready === true);
const currentStepTitle = computed(() => stepLabels.value[currentStep.value - 1] ?? "");
const canRegisterKeyla = computed(() => props.friendly?.actions?.can_register_keyla ?? (registrationDone.value && kycDone.value && specimenDone.value && certificateReady.value && !keylaDone.value));
const keylaRefreshLabel = computed(() => props.friendly?.actions?.keyla_refresh_label ?? (keylaQr.value ? "Saya Sudah Scan, Cek Status" : "Cek Status Aktivasi"));
const hasCompleteSavedIdentity = computed(() => Boolean(
    props.profile?.has_ktp_photo
    && props.profile?.reference_city_id
    && props.profile?.gender
    && props.profile?.date_of_birth
));
const identitySubmitLabel = computed(() => {
    if (props.friendly?.actions?.identity_submit_label) return props.friendly.actions.identity_submit_label;
    if (registrationDone.value) return "Simpan Perubahan Data";
    if (hasCompleteSavedIdentity.value) return "Coba Buat Akun Lagi";

    return "Simpan dan Buat Akun Tanda Tangan";
});
const overallStatusMessage = computed(() => overallReady.value
    ? (props.friendly?.status?.message ?? "Aktivasi selesai. Anda sudah bisa kembali ke kontrak untuk tanda tangan.")
    : (props.friendly?.status?.message ?? "Selesaikan langkah aktif di bawah agar kontrak bisa ditandatangani secara digital."));
const overallStatusLabel = computed(() => props.friendly?.status?.label ?? props.readiness?.overall?.label ?? "Belum Diketahui");
const overallStatusTone = computed(() => props.friendly?.status?.tone ?? props.readiness?.overall?.tone ?? "warning");
const technicalDetails = computed(() => Array.isArray(props.friendly?.technical_details) ? props.friendly.technical_details : []);
const keylaHelpItems = computed(() => Array.isArray(props.friendly?.keyla_help) ? props.friendly.keyla_help : []);
const autoRefreshConfig = computed(() => props.friendly?.auto_refresh ?? {});
const shouldAutoRefreshKeyla = computed(() => Boolean(
    props.actions?.silent_refresh_url
    && autoRefreshConfig.value?.enabled
    && keylaQr.value
    && !keylaDone.value
));
const keylaAutoRefreshMessage = computed(() => autoRefreshConfig.value?.message ?? "DigiPro akan mengecek status aplikasi HP secara otomatis beberapa kali.");

const statusClass = (tone) => ({
    success: "border-emerald-200 bg-emerald-50 text-emerald-800",
    warning: "border-amber-200 bg-amber-50 text-amber-800",
    danger: "border-rose-200 bg-rose-50 text-rose-800",
    muted: "border-slate-200 bg-slate-50 text-slate-700",
}[tone] ?? "border-slate-200 bg-slate-50 text-slate-700");

const registrationLabel = computed(() => registrationDone.value ? "Sudah terdaftar" : "Belum terdaftar");
const activeStepIndex = computed(() => {
    if (props.friendly?.active_step) return Number(props.friendly.active_step);
    if (!registrationDone.value) return 1;
    if (!kycDone.value) return 2;
    if (!specimenDone.value) return 3;
    return 4;
});

const progressItems = computed(() => Array.isArray(props.friendly?.steps) && props.friendly.steps.length
    ? props.friendly.steps
    : [
        {
            label: "Akun tanda tangan",
            value: registrationDone.value ? "Sudah dibuat" : "Belum dibuat",
            tone: registrationDone.value ? "success" : "warning",
        },
        {
            label: "Video wajah",
            value: kycDone.value ? "Sudah dikirim" : "Belum direkam",
            tone: kycDone.value ? "success" : "warning",
        },
        {
            label: "Tanda tangan",
            value: specimenDone.value ? "Sudah tersimpan" : "Belum dibuat",
            tone: specimenDone.value ? "success" : "warning",
        },
        {
            label: "Aplikasi HP",
            value: keylaDone.value ? "Sudah aktif" : "Belum terhubung",
            tone: keylaDone.value ? "success" : "warning",
        },
    ]);

watch(activeStepIndex, (value) => {
    if (currentStep.value < value || overallReady.value) {
        currentStep.value = value;
    }
}, { immediate: true });

watch(() => flash.value?.error, (message) => {
    if (message) {
        console.error("Signature activation error:", message);
    }
}, { immediate: true });

const isWna = computed(() => identityForm.is_wna === "1" || identityForm.is_wna === true);
const identityPhotoLabel = computed(() => props.profile?.has_ktp_photo ? "Foto identitas sudah tersimpan" : "Foto identitas belum diunggah");

watch(() => identityForm.nik, (value) => {
    if (isWna.value) return;

    const normalized = String(value ?? "").replace(/\D/g, "").slice(0, 16);
    if (normalized !== value) {
        identityForm.nik = normalized;
    }
});

watch(() => identityForm.is_wna, () => {
    if (isWna.value) return;

    identityForm.nik = String(identityForm.nik ?? "").replace(/\D/g, "").slice(0, 16);
});

watch(() => identityForm.reference_province_id, (next, prev) => {
    if (!next || next === prev) return;
    identityForm.reference_city_id = "";
    router.get(props.actions.contract_url.replace("/kontrak", "/kontrak/onboarding"), { province_id: next }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ["references", "profile", "readiness", "friendly", "actions", "request"],
    });
});

const completeIdentity = () => identityForm.post(props.actions.complete_identity_url ?? props.actions.save_identity_url, {
    preserveScroll: true,
    forceFormData: true,
});
const refreshReadiness = () => router.post(props.actions.refresh_url, {}, { preserveScroll: true });
const registerKeyla = () => {
    if (!canRegisterKeyla.value) return;

    router.post(props.actions.register_keyla_url, {}, { preserveScroll: true });
};
const goToContract = () => router.visit(props.actions.contract_url);
const goBackStep = () => { currentStep.value = Math.max(1, currentStep.value - 1); };
const goNextStep = () => { currentStep.value = Math.min(4, currentStep.value + 1); };

let keylaAutoRefreshTimer = null;
let keylaAutoRefreshAttempts = 0;
const isAutoRefreshingKeyla = ref(false);

const stopKeylaAutoRefresh = () => {
    if (keylaAutoRefreshTimer) {
        window.clearInterval(keylaAutoRefreshTimer);
        keylaAutoRefreshTimer = null;
    }
    keylaAutoRefreshAttempts = 0;
};

const silentRefreshReadiness = () => {
    if (!shouldAutoRefreshKeyla.value || isAutoRefreshingKeyla.value) return;

    const maxAttempts = Number(autoRefreshConfig.value?.max_attempts ?? 8);
    if (keylaAutoRefreshAttempts >= maxAttempts) {
        stopKeylaAutoRefresh();
        return;
    }

    keylaAutoRefreshAttempts += 1;
    isAutoRefreshingKeyla.value = true;
    router.post(props.actions.silent_refresh_url, {}, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ["profile", "readiness", "friendly"],
        onFinish: () => {
            isAutoRefreshingKeyla.value = false;
        },
    });
};

const startKeylaAutoRefresh = () => {
    if (keylaAutoRefreshTimer || !shouldAutoRefreshKeyla.value) return;

    const intervalMs = Number(autoRefreshConfig.value?.interval_ms ?? 15000);
    keylaAutoRefreshTimer = window.setInterval(silentRefreshReadiness, Math.max(5000, intervalMs));
};

watch(shouldAutoRefreshKeyla, (enabled) => {
    if (enabled) {
        startKeylaAutoRefresh();
        return;
    }

    stopKeylaAutoRefresh();
}, { immediate: true });

const stopCamera = () => {
    if (mediaRecorder.value && isRecording.value) mediaRecorder.value.stop();
    if (mediaStream.value) mediaStream.value.getTracks().forEach((track) => track.stop());
    mediaStream.value = null;
    mediaRecorder.value = null;
    isRecording.value = false;
    isCameraReady.value = false;
    if (liveVideoRef.value) liveVideoRef.value.srcObject = null;
};

const startCamera = async () => {
    stopCamera();
    recordedChunks.value = [];
    if (recordedVideoUrl.value) URL.revokeObjectURL(recordedVideoUrl.value);
    recordedVideoUrl.value = null;
    isPreparingCamera.value = true;
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: true });
        mediaStream.value = stream;
        isCameraReady.value = true;
        if (liveVideoRef.value) {
            liveVideoRef.value.srcObject = stream;
            await liveVideoRef.value.play().catch(() => {});
        }
    } catch (_) {
        isCameraReady.value = false;
    } finally {
        isPreparingCamera.value = false;
    }
};

const startRecording = () => {
    if (!mediaStream.value) return;
    recordedChunks.value = [];
    const recorder = new MediaRecorder(mediaStream.value, {
        mimeType: MediaRecorder.isTypeSupported("video/webm;codecs=vp9") ? "video/webm;codecs=vp9" : "video/webm",
    });
    recorder.ondataavailable = (event) => { if (event.data?.size > 0) recordedChunks.value.push(event.data); };
    recorder.onstop = () => {
        const blob = new Blob(recordedChunks.value, { type: recorder.mimeType || "video/webm" });
        recordedVideoUrl.value = URL.createObjectURL(blob);
        kycForm.kyc_video = new File([blob], "customer-kyc.webm", { type: blob.type });
        isRecording.value = false;
    };
    recorder.start();
    mediaRecorder.value = recorder;
    isRecording.value = true;
};

const stopRecording = () => {
    if (mediaRecorder.value && isRecording.value) mediaRecorder.value.stop();
};

const submitKyc = () => {
    if (!kycForm.kyc_video) return;
    kycForm.post(props.actions.submit_kyc_url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => stopCamera(),
    });
};

const initializeSignatureCanvas = () => {
    const canvas = signatureCanvasRef.value;
    if (!canvas) return;
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(1, Math.floor(rect.width * ratio));
    canvas.height = Math.max(1, Math.floor(rect.height * ratio));
    const ctx = canvas.getContext("2d");
    if (!ctx) return;
    ctx.scale(ratio, ratio);
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, rect.width, rect.height);
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.lineWidth = 2.5;
    ctx.strokeStyle = "#0f172a";
    hasSignatureStroke.value = false;
    specimenForm.signature_image = null;
};

watch(signatureCanvasRef, () => requestAnimationFrame(() => initializeSignatureCanvas()));

const signaturePoint = (event) => {
    const canvas = signatureCanvasRef.value;
    if (!canvas) return null;
    const rect = canvas.getBoundingClientRect();
    const source = "touches" in event ? event.touches[0] : event;
    return { x: source.clientX - rect.left, y: source.clientY - rect.top };
};

const beginSignature = (event) => {
    const ctx = signatureCanvasRef.value?.getContext("2d");
    const point = signaturePoint(event);
    if (!ctx || !point) return;
    isDrawing.value = true;
    ctx.beginPath();
    ctx.moveTo(point.x, point.y);
    hasSignatureStroke.value = true;
};

const drawSignature = (event) => {
    if (!isDrawing.value) return;
    const ctx = signatureCanvasRef.value?.getContext("2d");
    const point = signaturePoint(event);
    if (!ctx || !point) return;
    ctx.lineTo(point.x, point.y);
    ctx.stroke();
};

const endSignature = () => { isDrawing.value = false; };
const resetSignature = () => initializeSignatureCanvas();

const useSignatureCanvas = () => {
    if (!signatureCanvasRef.value || !hasSignatureStroke.value) return;
    signatureCanvasRef.value.toBlob((blob) => {
        if (!blob) return;
        specimenForm.signature_image = new File([blob], "customer-signature.png", { type: "image/png" });
    }, "image/png");
};

const submitSpecimen = () => {
    if (!specimenForm.signature_image) return;
    specimenForm.post(props.actions.set_specimen_url, { preserveScroll: true, forceFormData: true });
};

onBeforeUnmount(() => {
    stopKeylaAutoRefresh();
    stopCamera();
    if (recordedVideoUrl.value) URL.revokeObjectURL(recordedVideoUrl.value);
});
</script>

<template>
    <Head title="Aktivasi Tanda Tangan Digital" />

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold text-balance">Aktivasi Tanda Tangan Digital</h1>
                        <Badge variant="secondary">{{ request.request_number ?? "-" }}</Badge>
                    </div>
                    <p class="max-w-3xl text-sm text-muted-foreground text-pretty">
                        Selesaikan langkah singkat ini agar kontrak bisa ditandatangani secara digital melalui Peruri. Siapkan KTP, HP, dan aplikasi KEYLA.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" @click="refreshReadiness">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Cek Status Aktivasi
                    </Button>
                    <Button variant="outline" @click="goToContract">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Kembali ke Kontrak
                    </Button>
                </div>
            </div>

            <Alert v-if="flash.error" variant="destructive">
                <CircleAlert />
                <AlertTitle>Perlu diperbaiki</AlertTitle>
                <AlertDescription>{{ flash.error }}</AlertDescription>
            </Alert>

            <Alert v-else-if="flash.success" class="border-emerald-200 bg-emerald-50 text-emerald-900">
                <CircleCheck />
                <AlertTitle>Berhasil</AlertTitle>
                <AlertDescription>{{ flash.success }}</AlertDescription>
            </Alert>

            <Card class="border-slate-200 shadow-sm">
                <CardHeader class="space-y-4 pb-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="text-base">Progress Aktivasi</CardTitle>
                            <CardDescription>Ringkasan singkat agar Anda tahu langkah mana yang masih perlu diselesaikan.</CardDescription>
                        </div>
                        <Badge variant="outline" :class="statusClass(overallStatusTone)">
                            {{ overallStatusLabel }}
                        </Badge>
                    </div>

                    <StepProgress :current-step="currentStep" :steps="stepLabels" />
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 text-sm font-medium text-slate-900">
                                <ShieldCheck class="h-4 w-4" />
                                <span>Status aktivasi</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600 text-pretty">
                                {{ overallStatusMessage }}
                            </p>
                            <p v-if="friendly?.status?.show_last_error || readiness?.last_error" class="mt-2 text-xs text-rose-700">
                                Status terakhir belum berhasil diperbarui. Coba cek status lagi atau hubungi admin jika tetap gagal.
                            </p>
                        </div>
                        <Badge variant="outline" :class="statusClass(registrationDone ? 'success' : 'warning')">
                            {{ registrationLabel }}
                        </Badge>
                    </div>

                    <div class="grid gap-3 md:grid-cols-4">
                        <div
                            v-for="item in progressItems"
                            :key="item.key ?? item.label"
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                        >
                            <div class="text-sm font-medium text-slate-900">{{ item.label }}</div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="inline-flex size-2.5 rounded-full" :class="item.tone === 'success' ? 'bg-emerald-500' : 'bg-amber-400'" />
                                <p class="text-xs text-slate-600">{{ item.value }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-slate-200 shadow-sm">
                <CardHeader class="space-y-2">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="text-lg">Tahap {{ currentStep }} dari {{ stepLabels.length }}</CardTitle>
                            <CardDescription class="text-sm text-slate-600">{{ currentStepTitle }}</CardDescription>
                        </div>
                        <Badge variant="secondary">Langkah Aktif</Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-5">
                    <div v-if="currentStep === 1" class="space-y-5">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 text-pretty">
                            Lengkapi data berikut untuk membuat akun tanda tangan digital. Pastikan sesuai KTP atau identitas resmi.
                        </div>

                        <form class="grid gap-4" @submit.prevent="completeIdentity">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="is_wna">Kewarganegaraan</Label>
                                    <Select v-model="identityForm.is_wna">
                                        <SelectTrigger id="is_wna" class="text-base">
                                            <SelectValue placeholder="Pilih kewarganegaraan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="0">WNI</SelectItem>
                                            <SelectItem value="1">WNA</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-slate-500">Pilih sesuai identitas yang digunakan untuk tanda tangan digital.</p>
                                    <p v-if="identityForm.errors.is_wna" class="text-sm text-rose-600">{{ identityForm.errors.is_wna }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="gender">Jenis kelamin</Label>
                                    <Select v-model="identityForm.gender">
                                        <SelectTrigger id="gender" class="text-base">
                                            <SelectValue placeholder="Pilih jenis kelamin" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="M">Laki-laki</SelectItem>
                                            <SelectItem value="F">Perempuan</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-slate-500">Data ini dibutuhkan untuk membuat akun tanda tangan.</p>
                                    <p v-if="identityForm.errors.gender" class="text-sm text-rose-600">{{ identityForm.errors.gender }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="peruri_email">Email aktif</Label>
                                <Input id="peruri_email" v-model="identityForm.peruri_email" type="email" class="text-base" />
                                <p class="text-xs text-slate-500">Gunakan email yang bisa Anda buka.</p>
                                <p v-if="identityForm.errors.peruri_email" class="text-sm text-rose-600">{{ identityForm.errors.peruri_email }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="peruri_phone">Nomor WhatsApp / HP</Label>
                                <Input id="peruri_phone" v-model="identityForm.peruri_phone" class="text-base" />
                                <p class="text-xs text-slate-500">Gunakan nomor aktif yang bisa menerima pesan atau panggilan.</p>
                                <p v-if="identityForm.errors.peruri_phone" class="text-sm text-rose-600">{{ identityForm.errors.peruri_phone }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="nik">{{ isWna ? "Nomor KITAS/KITAP" : "NIK" }}</Label>
                                <Input id="nik" v-model="identityForm.nik" :inputmode="isWna ? 'text' : 'numeric'" :maxlength="isWna ? 32 : 16" class="text-base" />
                                <p class="text-xs text-slate-500">{{ isWna ? "Masukkan nomor KITAS/KITAP tanpa spasi atau tanda baca." : "Masukkan 16 digit NIK sesuai KTP tanpa spasi atau tanda baca." }}</p>
                                <p v-if="identityForm.errors.nik" class="text-sm text-rose-600">{{ identityForm.errors.nik }}</p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="place_of_birth">Tempat lahir</Label>
                                    <Input id="place_of_birth" v-model="identityForm.place_of_birth" class="text-base" />
                                    <p class="text-xs text-slate-500">Isi sesuai identitas resmi.</p>
                                    <p v-if="identityForm.errors.place_of_birth" class="text-sm text-rose-600">{{ identityForm.errors.place_of_birth }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="date_of_birth">Tanggal lahir</Label>
                                    <Input id="date_of_birth" v-model="identityForm.date_of_birth" type="date" class="text-base" />
                                    <p class="text-xs text-slate-500">Isi sesuai identitas resmi.</p>
                                    <p v-if="identityForm.errors.date_of_birth" class="text-sm text-rose-600">{{ identityForm.errors.date_of_birth }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="ktp_photo">Foto {{ isWna ? "KITAS/KITAP" : "KTP" }}</Label>
                                <Input
                                    id="ktp_photo"
                                    type="file"
                                    accept="image/png,image/jpeg"
                                    class="text-base"
                                    @input="identityForm.ktp_photo = $event.target.files?.[0] ?? null"
                                />
                                <p class="text-xs text-slate-500">{{ identityPhotoLabel }}. Unggah foto asli yang jelas dan terbaca.</p>
                                <p v-if="identityForm.errors.ktp_photo" class="text-sm text-rose-600">{{ identityForm.errors.ktp_photo }}</p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="province">Provinsi</Label>
                                    <Select v-model="identityForm.reference_province_id">
                                        <SelectTrigger id="province" class="text-base">
                                            <SelectValue placeholder="Pilih provinsi" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="option in provinces" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-slate-500">Pilih provinsi sesuai alamat identitas.</p>
                                    <p v-if="identityForm.errors.reference_province_id" class="text-sm text-rose-600">{{ identityForm.errors.reference_province_id }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="city">Kota</Label>
                                    <Select v-model="identityForm.reference_city_id">
                                        <SelectTrigger id="city" class="text-base">
                                            <SelectValue placeholder="Pilih kota" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="option in cities" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-slate-500">Pilih kabupaten atau kota yang sesuai dengan alamat identitas Anda.</p>
                                    <p v-if="identityForm.errors.reference_city_id" class="text-sm text-rose-600">{{ identityForm.errors.reference_city_id }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="address">Alamat sesuai KTP</Label>
                                <Input id="address" v-model="identityForm.address" class="text-base" />
                                <p class="text-xs text-slate-500">Isi alamat sesuai KTP atau identitas resmi yang digunakan.</p>
                                <p v-if="identityForm.errors.address" class="text-sm text-rose-600">{{ identityForm.errors.address }}</p>
                            </div>

                            <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-4">
                                <Button type="submit" :disabled="identityForm.processing">{{ identityForm.processing ? "Memproses..." : identitySubmitLabel }}</Button>
                            </div>
                        </form>
                    </div>

                    <div v-else-if="currentStep === 2" class="space-y-5">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 text-pretty">
                            Rekam video singkat untuk memastikan akun tanda tangan benar milik Anda. Pastikan wajah terlihat jelas.
                        </div>
                        <p class="text-xs text-slate-500">Posisikan wajah di tengah frame, hindari cahaya dari belakang, dan pastikan suara sekitar tidak terlalu bising.</p>

                        <div class="overflow-hidden rounded-2xl border bg-slate-950">
                            <video ref="liveVideoRef" class="aspect-video w-full object-cover" autoplay muted playsinline />
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" :disabled="isPreparingCamera" @click="startCamera">Aktifkan Kamera</Button>
                            <Button type="button" :disabled="!isCameraReady || isRecording" @click="startRecording">Mulai Rekam</Button>
                            <Button type="button" variant="outline" :disabled="!isRecording" @click="stopRecording">Selesai Rekam</Button>
                        </div>

                        <div v-if="recordedVideoUrl" class="space-y-3 rounded-2xl border border-slate-200 p-4">
                            <div class="text-sm font-medium text-slate-900">Preview video</div>
                            <video :src="recordedVideoUrl" class="aspect-video w-full rounded-xl border bg-black" controls playsinline />
                            <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                                <Button type="button" :disabled="!kycForm.kyc_video || kycForm.processing" @click="submitKyc">Kirim Video Ini</Button>
                                <Button type="button" variant="outline" @click="startCamera">Rekam Ulang</Button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="currentStep === 3" class="space-y-5">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 text-pretty">
                            Gambar tanda tangan Anda di area berikut. Jika belum rapi, gunakan tombol ulangi.
                        </div>
                        <p class="text-xs text-slate-500">Buat tanda tangan seperti yang biasa Anda gunakan pada dokumen resmi, lalu simpan jika sudah sesuai.</p>

                        <div class="rounded-2xl border bg-white p-3">
                            <canvas
                                ref="signatureCanvasRef"
                                class="h-64 w-full touch-none rounded-xl border bg-white"
                                @mousedown.prevent="beginSignature"
                                @mousemove.prevent="drawSignature"
                                @mouseup.prevent="endSignature"
                                @mouseleave.prevent="endSignature"
                                @touchstart.prevent="beginSignature"
                                @touchmove.prevent="drawSignature"
                                @touchend.prevent="endSignature"
                            />
                        </div>

                        <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                            <Button type="button" variant="outline" @click="resetSignature">Ulangi</Button>
                            <Button type="button" variant="outline" :disabled="!hasSignatureStroke" @click="useSignatureCanvas">Gunakan Tanda Tangan Ini</Button>
                            <Button type="button" :disabled="!specimenForm.signature_image || specimenForm.processing" @click="submitSpecimen">Simpan Tanda Tangan</Button>
                        </div>
                    </div>

                    <div v-else class="space-y-5">
                        <Alert class="border-sky-200 bg-sky-50 text-sky-950">
                            <Smartphone class="h-4 w-4" />
                            <AlertTitle>Hubungkan aplikasi di HP</AlertTitle>
                            <AlertDescription>
                                Unduh aplikasi KEYLA dari Peruri di HP, lalu buat QR di bawah dan scan dari aplikasi tersebut.
                            </AlertDescription>
                        </Alert>
                        <p class="text-xs text-slate-500">Setelah QR dibuat, buka aplikasi KEYLA di HP Anda lalu scan QR tersebut untuk menghubungkan akun.</p>

                        <Alert :class="certificateReady ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950'">
                            <ShieldCheck v-if="certificateReady" class="h-4 w-4" />
                            <CircleAlert v-else class="h-4 w-4" />
                            <AlertTitle>Status verifikasi akun</AlertTitle>
                            <AlertDescription>{{ accountVerificationMessage }}</AlertDescription>
                        </Alert>

                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" :disabled="!canRegisterKeyla" @click="registerKeyla">Buat QR Aktivasi</Button>
                            <Button type="button" @click="refreshReadiness">{{ keylaRefreshLabel }}</Button>
                        </div>

                        <div v-if="keylaQr" class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-sm font-medium text-slate-900">Scan QR dengan aplikasi KEYLA</div>
                            <div class="mt-4 flex justify-center rounded-2xl border bg-white p-4">
                                <img :src="keylaQr" alt="QR aktivasi aplikasi KEYLA" class="h-56 w-56 object-contain" />
                            </div>
                            <div v-if="shouldAutoRefreshKeyla" class="mt-3 flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900">
                                <RefreshCw class="mt-0.5 h-3.5 w-3.5" :class="isAutoRefreshingKeyla ? 'animate-spin' : ''" />
                                <p>{{ keylaAutoRefreshMessage }}</p>
                            </div>
                        </div>

                        <Accordion v-if="keylaHelpItems.length" type="single" collapsible class="rounded-2xl border border-slate-200 bg-white px-4">
                            <AccordionItem value="keyla-help" class="border-0">
                                <AccordionTrigger class="text-left text-sm font-medium hover:no-underline">
                                    Cara menghubungkan aplikasi KEYLA
                                </AccordionTrigger>
                                <AccordionContent class="pb-4">
                                    <div class="grid gap-3">
                                        <div v-for="(item, index) in keylaHelpItems" :key="item.title" class="flex gap-3">
                                            <div class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">
                                                {{ index + 1 }}
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-sm font-medium text-slate-900">{{ item.title }}</div>
                                                <p class="text-xs text-slate-600 text-pretty">{{ item.description }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </AccordionContent>
                            </AccordionItem>
                        </Accordion>

                        <div v-if="overallReady" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                            <div class="flex items-center gap-2 text-sm font-medium text-emerald-900">
                                <CircleCheck class="h-4 w-4" />
                                <span>Aktivasi selesai</span>
                            </div>
                            <p class="mt-1 text-xs text-emerald-800">Anda sudah bisa kembali ke halaman kontrak.</p>
                            <Button class="mt-3" type="button" @click="goToContract">Lanjut ke Kontrak</Button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-200 pt-4">
                        <Button type="button" variant="ghost" :disabled="currentStep === 1" @click="goBackStep">Kembali</Button>
                        <Button type="button" variant="outline" :disabled="currentStep === 4" @click="goNextStep">Lanjut</Button>
                    </div>
                </CardContent>
            </Card>

            <Alert v-if="referencesError" class="border-amber-200 bg-amber-50 text-amber-900">
                <CircleAlert />
                <AlertTitle>Referensi wilayah belum tersedia</AlertTitle>
                <AlertDescription>{{ referencesError }}</AlertDescription>
            </Alert>

            <Accordion v-if="technicalDetails.length" type="single" collapsible class="rounded-2xl border border-slate-200 bg-white px-4">
                <AccordionItem value="technical-details" class="border-0">
                    <AccordionTrigger class="text-left text-sm font-medium hover:no-underline">
                        Detail teknis untuk bantuan admin
                    </AccordionTrigger>
                    <AccordionContent class="pb-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div v-for="item in technicalDetails" :key="item.label" class="rounded-xl border border-slate-200 px-3 py-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-xs font-medium text-slate-500">{{ item.label }}</div>
                                        <p class="mt-1 text-sm text-slate-900 break-words">{{ item.value || "-" }}</p>
                                    </div>
                                    <Badge v-if="item.code" variant="outline" :class="statusClass(item.tone)">
                                        {{ item.code }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </AccordionContent>
                </AccordionItem>
            </Accordion>
        </div>
    </DashboardLayout>
</template>
