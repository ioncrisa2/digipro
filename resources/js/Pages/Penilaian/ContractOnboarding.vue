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
import { ArrowLeft, CircleAlert, CircleCheck, RefreshCw, ShieldCheck, Smartphone } from "lucide-vue-next";

const props = defineProps({
    request: { type: Object, required: true },
    profile: { type: Object, required: true },
    readiness: { type: Object, default: () => ({}) },
    references: { type: Object, default: () => ({}) },
    actions: { type: Object, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const provinces = computed(() => Array.isArray(props.references?.provinces) ? props.references.provinces : []);
const cities = computed(() => Array.isArray(props.references?.cities) ? props.references.cities : []);
const referencesError = computed(() => props.references?.error ?? null);
const keylaQr = computed(() => props.profile?.keyla_qr_image ?? props.readiness?.keyla_qr_image ?? null);

const stepLabels = ["Data Diri", "Video Verifikasi", "Tanda Tangan", "Aplikasi KEYLA"];
const currentStep = ref(1);

const identityForm = useForm({
    peruri_email: props.profile?.peruri_email ?? "",
    peruri_phone: props.profile?.peruri_phone ?? "",
    nik: props.profile?.nik ?? "",
    reference_province_id: props.profile?.reference_province_id ?? "",
    reference_city_id: props.profile?.reference_city_id ?? "",
    address: props.profile?.address ?? "",
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
const keylaDone = computed(() => props.readiness?.keyla?.is_ready === true);
const overallReady = computed(() => props.readiness?.overall?.is_ready === true);
const currentStepTitle = computed(() => stepLabels[currentStep.value - 1] ?? "");

const statusClass = (tone) => ({
    success: "border-emerald-200 bg-emerald-50 text-emerald-800",
    warning: "border-amber-200 bg-amber-50 text-amber-800",
    danger: "border-rose-200 bg-rose-50 text-rose-800",
    muted: "border-slate-200 bg-slate-50 text-slate-700",
}[tone] ?? "border-slate-200 bg-slate-50 text-slate-700");

const registrationLabel = computed(() => registrationDone.value ? "Sudah terdaftar" : "Belum terdaftar");
const activeStepIndex = computed(() => {
    if (!registrationDone.value) return 1;
    if (!kycDone.value) return 2;
    if (!specimenDone.value) return 3;
    return 4;
});

const progressItems = computed(() => ([
    {
        label: "Akun layanan digital",
        value: registrationDone.value ? "Sudah terdaftar" : "Belum terdaftar",
        tone: registrationDone.value ? "success" : "warning",
    },
    {
        label: "Video verifikasi",
        value: kycDone.value ? "Sudah dikirim" : "Belum direkam",
        tone: kycDone.value ? "success" : "warning",
    },
    {
        label: "Tanda tangan",
        value: specimenDone.value ? "Sudah tersimpan" : "Belum dibuat",
        tone: specimenDone.value ? "success" : "warning",
    },
    {
        label: "Aplikasi KEYLA",
        value: keylaDone.value ? "Sudah aktif" : "Belum terhubung",
        tone: keylaDone.value ? "success" : "warning",
    },
]));

watch(activeStepIndex, (value) => {
    if (currentStep.value < value || overallReady.value) {
        currentStep.value = value;
    }
}, { immediate: true });

watch(() => identityForm.nik, (value) => {
    const normalized = String(value ?? "").replace(/\D/g, "").slice(0, 16);
    if (normalized !== value) {
        identityForm.nik = normalized;
    }
});

watch(() => identityForm.reference_province_id, (next, prev) => {
    if (!next || next === prev) return;
    identityForm.reference_city_id = "";
    router.get(props.actions.contract_url.replace("/kontrak", "/kontrak/onboarding"), { province_id: next }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ["references", "profile", "readiness", "actions", "request"],
    });
});

const saveIdentity = () => identityForm.post(props.actions.save_identity_url, { preserveScroll: true });
const refreshReadiness = () => router.post(props.actions.refresh_url, {}, { preserveScroll: true });
const registerUser = () => router.post(props.actions.register_user_url, {}, { preserveScroll: true });
const registerKeyla = () => router.post(props.actions.register_keyla_url, {}, { preserveScroll: true });
const goToContract = () => router.visit(props.actions.contract_url);
const goBackStep = () => { currentStep.value = Math.max(1, currentStep.value - 1); };
const goNextStep = () => { currentStep.value = Math.min(4, currentStep.value + 1); };

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
                        Ikuti langkah berikut agar kontrak bisa ditandatangani. Anda juga perlu memasang aplikasi KEYLA dari Peruri di ponsel sebelum masuk ke tahap terakhir.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" @click="refreshReadiness">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Cek Status
                    </Button>
                    <Button variant="outline" @click="goToContract">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Kembali ke Kontrak
                    </Button>
                </div>
            </div>

            <Alert v-if="flash.error" variant="destructive">
                <CircleAlert />
                <AlertTitle>Masih ada yang perlu diperbaiki</AlertTitle>
                <AlertDescription>{{ flash.error }}</AlertDescription>
            </Alert>

            <Alert v-else-if="flash.success" class="border-emerald-200 bg-emerald-50 text-emerald-900">
                <CircleCheck />
                <AlertTitle>Langkah berhasil diproses</AlertTitle>
                <AlertDescription>{{ flash.success }}</AlertDescription>
            </Alert>

            <Card class="border-slate-200 shadow-sm">
                <CardHeader class="space-y-4 pb-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle class="text-base">Progress Aktivasi</CardTitle>
                            <CardDescription>Ringkasan singkat agar Anda tahu langkah mana yang masih perlu diselesaikan.</CardDescription>
                        </div>
                        <Badge variant="outline" :class="statusClass(readiness?.overall?.tone)">
                            {{ readiness?.overall?.label ?? "Belum Diketahui" }}
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
                                {{ readiness?.overall?.message ?? "Lengkapi langkah aktivasi agar kontrak dapat ditandatangani." }}
                            </p>
                            <p v-if="readiness?.last_error" class="mt-2 text-xs text-rose-700">{{ readiness.last_error }}</p>
                        </div>
                        <Badge variant="outline" :class="statusClass(registrationDone ? 'success' : 'warning')">
                            {{ registrationLabel }}
                        </Badge>
                    </div>

                    <div class="grid gap-3 md:grid-cols-4">
                        <div
                            v-for="item in progressItems"
                            :key="item.label"
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
                            Lengkapi data singkat berikut. Setelah itu, tekan tombol daftar agar akun tanda tangan digital Anda dibuat.
                        </div>

                        <form class="grid gap-4" @submit.prevent="saveIdentity">
                            <div class="space-y-2">
                                <Label for="peruri_email">Email aktif</Label>
                                <Input id="peruri_email" v-model="identityForm.peruri_email" type="email" class="text-base" />
                                <p class="text-xs text-slate-500">Gunakan email yang aktif dan bisa diakses untuk proses aktivasi tanda tangan digital.</p>
                                <p v-if="identityForm.errors.peruri_email" class="text-sm text-rose-600">{{ identityForm.errors.peruri_email }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="peruri_phone">Nomor WhatsApp / HP</Label>
                                <Input id="peruri_phone" v-model="identityForm.peruri_phone" class="text-base" />
                                <p class="text-xs text-slate-500">Masukkan nomor ponsel yang aktif agar data akun dan verifikasi tetap sinkron.</p>
                                <p v-if="identityForm.errors.peruri_phone" class="text-sm text-rose-600">{{ identityForm.errors.peruri_phone }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="nik">NIK</Label>
                                <Input id="nik" v-model="identityForm.nik" inputmode="numeric" maxlength="16" class="text-base" />
                                <p class="text-xs text-slate-500">Masukkan 16 digit NIK sesuai KTP tanpa spasi atau tanda baca.</p>
                                <p v-if="identityForm.errors.nik" class="text-sm text-rose-600">{{ identityForm.errors.nik }}</p>
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
                                    <p class="text-xs text-slate-500">Pilih provinsi sesuai data identitas yang akan dipakai saat registrasi.</p>
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
                                <p class="text-xs text-slate-500">Isi alamat sesuai KTP atau identitas resmi yang digunakan untuk onboarding.</p>
                                <p v-if="identityForm.errors.address" class="text-sm text-rose-600">{{ identityForm.errors.address }}</p>
                            </div>

                            <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-4">
                                <Button type="submit" :disabled="identityForm.processing">Simpan Data</Button>
                                <Button type="button" variant="outline" @click="registerUser">Daftarkan Akun</Button>
                            </div>
                        </form>
                    </div>

                    <div v-else-if="currentStep === 2" class="space-y-5">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 text-pretty">
                            Rekam video singkat langsung dari webcam. Pastikan wajah terlihat jelas dan ruangan cukup terang.
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
                                <Button type="button" :disabled="!kycForm.kyc_video || kycForm.processing" @click="submitKyc">Gunakan Video Ini</Button>
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
                            <Button type="button" variant="outline" :disabled="!hasSignatureStroke" @click="useSignatureCanvas">Gunakan Gambar Ini</Button>
                            <Button type="button" :disabled="!specimenForm.signature_image || specimenForm.processing" @click="submitSpecimen">Simpan Tanda Tangan</Button>
                        </div>
                    </div>

                    <div v-else class="space-y-5">
                        <Alert class="border-sky-200 bg-sky-50 text-sky-950">
                            <Smartphone class="h-4 w-4" />
                            <AlertTitle>Pasang aplikasi KEYLA dulu</AlertTitle>
                            <AlertDescription>
                                Unduh aplikasi KEYLA dari Peruri di ponsel, lalu buat QR di bawah dan scan dari aplikasi tersebut.
                            </AlertDescription>
                        </Alert>
                        <p class="text-xs text-slate-500">Setelah QR dibuat, buka aplikasi KEYLA di ponsel Anda lalu scan QR tersebut untuk menghubungkan akun.</p>

                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" @click="registerKeyla">Buat QR KEYLA</Button>
                            <Button type="button" @click="refreshReadiness">Saya Sudah Scan, Cek Lagi</Button>
                        </div>

                        <div v-if="keylaQr" class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-sm font-medium text-slate-900">Scan QR dengan aplikasi KEYLA</div>
                            <div class="mt-4 flex justify-center rounded-2xl border bg-white p-4">
                                <img :src="keylaQr" alt="QR KEYLA customer" class="h-56 w-56 object-contain" />
                            </div>
                        </div>

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
        </div>
    </DashboardLayout>
</template>
