<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";
import { router } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";
import AuthLayout from "@/layouts/AuthLayout.vue";
import { CheckCircle2 } from "lucide-vue-next";

const props = defineProps({
    redirectTo: {
        type: String,
        default: "/dashboard",
    },
    countdownSeconds: {
        type: Number,
        default: 5,
    },
});

const remaining = ref(props.countdownSeconds);
let timerId = null;

const goDashboard = () => {
    router.visit(props.redirectTo || "/dashboard");
};

onMounted(() => {
    timerId = window.setInterval(() => {
        if (remaining.value <= 1) {
            window.clearInterval(timerId);
            goDashboard();
            return;
        }

        remaining.value -= 1;
    }, 1000);
});

onBeforeUnmount(() => {
    if (timerId !== null) {
        window.clearInterval(timerId);
    }
});
</script>

<template>
    <AuthLayout>
        <div class="flex flex-col items-center text-center space-y-3 mb-8">
            <div class="p-3 rounded-2xl bg-emerald-100 text-emerald-700">
                <CheckCircle2 class="w-6 h-6" />
            </div>

            <h1 class="text-2xl font-semibold text-slate-900">Verifikasi Berhasil</h1>
            <p class="text-sm text-slate-500 max-w-[360px]">
                Email Anda sudah terverifikasi. Anda akan diarahkan ke dashboard dalam
                <span class="font-semibold text-slate-900">{{ remaining }}</span> detik.
            </p>
        </div>

        <Button
            type="button"
            class="w-full bg-slate-900 hover:bg-slate-800 text-white"
            @click="goDashboard"
        >
            Ke Dashboard Sekarang
        </Button>
    </AuthLayout>
</template>
