<script setup>
import { computed } from "vue";
import { Badge } from "@/components/ui/badge";

const props = defineProps({
    summary: { type: Object, required: true },
});

const milestones = computed(() => Array.isArray(props.summary?.milestones) ? props.summary.milestones : []);
const substatus = computed(() => props.summary?.substatus ?? null);
const terminalState = computed(() => props.summary?.terminal_state ?? null);

const segmentClass = (milestone) => {
    const state = String(milestone?.state ?? "upcoming");

    if (state === "completed") return "bg-emerald-500";
    if (state === "current" && terminalState.value === "cancelled") return "bg-rose-500";
    if (state === "current") return "bg-sky-600";

    return "bg-slate-200";
};

const substatusClass = computed(() => {
    const tone = String(substatus.value?.tone ?? "info");

    switch (tone) {
        case "success":
            return "border-emerald-200 bg-emerald-50 text-emerald-800";
        case "warning":
            return "border-amber-200 bg-amber-50 text-amber-900";
        case "danger":
            return "border-rose-200 bg-rose-50 text-rose-900";
        default:
            return "border-sky-200 bg-sky-50 text-sky-900";
    }
});
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Progress Milestone</p>
                    <Badge variant="outline">Tahap {{ summary.current_step }} dari {{ summary.total_steps }}</Badge>
                    <Badge v-if="terminalState === 'cancelled'" variant="destructive">Terminal</Badge>
                </div>
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-950">{{ summary.current_label }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ summary.helper_text }}</p>
                </div>
            </div>

            <div class="flex flex-col items-start gap-2 text-sm text-slate-500 lg:items-end">
                <span>Status saat ini</span>
                <span class="font-medium text-slate-900">{{ summary.status_label }}</span>
                <span v-if="summary.last_event_at" class="text-xs text-slate-500">Update terakhir {{ summary.last_event_at }}</span>
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex gap-2">
                <div
                    v-for="milestone in milestones"
                    :key="milestone.key"
                    class="h-2 flex-1 rounded-full transition-colors"
                    :class="segmentClass(milestone)"
                />
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <div
                    v-for="milestone in milestones"
                    :key="`${milestone.key}-label`"
                    class="space-y-1 rounded-2xl border px-3 py-3"
                    :class="milestone.state === 'current' ? 'border-slate-900 bg-slate-950 text-white' : 'border-slate-200 bg-white'"
                >
                    <p
                        class="text-[11px] font-semibold uppercase tracking-[0.22em]"
                        :class="milestone.state === 'current' ? 'text-slate-300' : 'text-slate-500'"
                    >
                        {{ milestone.state === "completed" ? "Selesai" : milestone.state === "current" ? "Aktif" : "Berikutnya" }}
                    </p>
                    <p class="text-sm font-medium" :class="milestone.state === 'current' ? 'text-white' : 'text-slate-900'">
                        {{ milestone.label }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="substatus"
            class="inline-flex max-w-full items-start rounded-2xl border px-4 py-3 text-sm"
            :class="substatusClass"
        >
            {{ substatus.label }}
        </div>
    </div>
</template>
