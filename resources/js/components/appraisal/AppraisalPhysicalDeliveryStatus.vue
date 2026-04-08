<script setup>
import { computed } from "vue";
import { Badge } from "@/components/ui/badge";

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
});

const toneClass = computed(() => {
    switch (props.summary?.state) {
        case "delivered":
            return "border-emerald-200 bg-emerald-50 text-emerald-900";
        case "shipped":
            return "border-sky-200 bg-sky-50 text-sky-900";
        case "printed":
        case "ready_to_print":
            return "border-amber-200 bg-amber-50 text-amber-900";
        default:
            return "border-slate-200 bg-slate-50 text-slate-700";
    }
});

const steps = computed(() => {
    const state = String(props.summary?.state ?? "waiting_final_report");
    const printedDone = ["printed", "shipped", "delivered"].includes(state);
    const shippedDone = ["shipped", "delivered"].includes(state);
    const deliveredDone = state === "delivered";

    return [
        { key: "printed", label: "Dicetak", done: printedDone },
        { key: "shipped", label: "Dikirim", done: shippedDone },
        { key: "delivered", label: "Diterima", done: deliveredDone },
    ];
});
</script>

<template>
    <div
        class="space-y-5 rounded-2xl border px-5 py-5"
        :class="toneClass"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em]">Pengiriman Hard Copy</p>
                    <Badge variant="outline" class="bg-white/70 text-current">
                        {{ summary.copies_count || 0 }} copy
                    </Badge>
                </div>
                <p class="text-base font-semibold">{{ summary.state_label || "Belum diproses" }}</p>
                <p class="text-sm">{{ summary.state_description || "Status pengiriman laporan fisik akan diperbarui oleh admin." }}</p>
            </div>

            <div class="text-right text-xs">
                <div class="font-semibold uppercase tracking-[0.18em]">Format</div>
                <div class="mt-1 text-sm font-medium normal-case">{{ summary.report_format_label || "-" }}</div>
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex gap-2">
                <div
                    v-for="step in steps"
                    :key="step.key"
                    class="h-2 flex-1 rounded-full"
                    :class="step.done ? 'bg-current opacity-90' : 'bg-white/70'"
                />
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div
                    v-for="step in steps"
                    :key="`${step.key}-label`"
                    class="rounded-2xl border border-white/70 bg-white/70 px-3 py-3"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                        {{ step.done ? "Selesai" : "Menunggu" }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-slate-950">{{ step.label }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.9fr)]">
            <div class="space-y-3 rounded-2xl border border-white/70 bg-white/80 p-4 text-sm text-slate-700">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Penerima</p>
                    <p class="mt-1 font-medium text-slate-950">{{ summary.delivery_recipient_name || "-" }}</p>
                    <p class="mt-1">{{ summary.delivery_recipient_phone || "-" }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Alamat Pengiriman</p>
                    <p class="mt-1 whitespace-pre-line">{{ summary.delivery_address || "-" }}</p>
                </div>
            </div>

            <div class="grid gap-3">
                <div class="rounded-2xl border border-white/70 bg-white/80 p-4 text-sm text-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kurir & Resi</p>
                    <p class="mt-1 font-medium text-slate-950">{{ summary.courier || "-" }}</p>
                    <p class="mt-1 break-all">{{ summary.tracking_number || "-" }}</p>
                </div>
                <div class="rounded-2xl border border-white/70 bg-white/80 p-4 text-sm text-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Timeline Pengiriman</p>
                    <div class="mt-2 space-y-1">
                        <p>Dicetak: {{ summary.printed_at || "-" }}</p>
                        <p>Dikirim: {{ summary.shipped_at || "-" }}</p>
                        <p>Diterima: {{ summary.delivered_at || "-" }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="summary.notes"
            class="rounded-2xl border border-white/70 bg-white/80 p-4 text-sm text-slate-700"
        >
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Catatan Pengiriman</p>
            <p class="mt-2 whitespace-pre-line">{{ summary.notes }}</p>
        </div>
    </div>
</template>
