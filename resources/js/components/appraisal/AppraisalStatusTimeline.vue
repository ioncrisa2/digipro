<script setup>
defineProps({
    events: { type: Array, default: () => [] },
    emptyText: { type: String, default: "Riwayat status belum tersedia." },
});

const timelineDotClass = (type) => {
    const key = String(type ?? "default").toLowerCase();

    if (key === "success") return "bg-emerald-500";
    if (key === "danger") return "bg-rose-500";
    if (key === "warning") return "bg-amber-500";
    if (["offer", "payment", "submitted", "info"].includes(key)) return "bg-sky-500";

    return "bg-slate-400";
};
</script>

<template>
    <div v-if="!events.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
        {{ emptyText }}
    </div>

    <div v-else class="space-y-4">
        <div
            v-for="item in events"
            :key="item.key"
            class="relative border-l border-slate-200 pl-6"
        >
            <span
                class="absolute -left-[5px] top-1 h-2.5 w-2.5 rounded-full"
                :class="timelineDotClass(item.type)"
            />
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm font-medium text-slate-950">{{ item.title ?? "-" }}</div>
                <div class="text-xs text-slate-500">{{ item.at ?? "-" }}</div>
            </div>
            <p class="mt-1 pb-4 text-sm leading-6 text-slate-600">{{ item.description ?? "-" }}</p>
        </div>
    </div>
</template>
