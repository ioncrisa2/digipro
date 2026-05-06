<script setup>
import { useNotification } from "@/composables/useNotification";
import { CheckCircle2, XCircle, Info, AlertTriangle, X } from "lucide-vue-next";

const { notifications, removeNotification } = useNotification();

const getIcon = (type) => {
  switch (type) {
    case "success":
      return CheckCircle2;
    case "error":
      return XCircle;
    case "warning":
      return AlertTriangle;
    default:
      return Info;
  }
};

const getClasses = (type) => {
  // gaya shadcn-ish: card putih, border halus, highlight di icon + border kiri
  switch (type) {
    case "success":
      return "border-l-4 border-emerald-500 text-emerald-900 bg-white";
    case "error":
      return "border-l-4 border-red-500 text-red-900 bg-white";
    case "warning":
      return "border-l-4 border-amber-500 text-amber-900 bg-white";
    default:
      return "border-l-4 border-sky-500 text-slate-900 bg-white";
  }
};
</script>

<template>
  <div
    class="fixed top-24 right-5 z-50 flex flex-col gap-3 pointer-events-none"
    role="status"
    aria-live="polite"
    aria-relevant="additions text"
  >
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
      enter-from-class="translate-x-full opacity-0 motion-reduce:translate-x-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="translate-x-full opacity-0 motion-reduce:translate-x-0"
    >
      <div
        v-for="note in notifications"
        :key="note.id"
        class="pointer-events-auto w-80 shadow-lg rounded-xl px-4 py-3 flex items-start gap-3 border border-slate-200/70 backdrop-blur-sm"
        :class="getClasses(note.type)"
      >
        <component :is="getIcon(note.type)" class="w-5 h-5 shrink-0 mt-0.5" />

        <div class="flex-1 text-sm font-medium leading-relaxed">
          {{ note.message }}
        </div>

        <button
          type="button"
          @click="removeNotification(note.id)"
          aria-label="Tutup notifikasi"
          class="rounded-md text-slate-400 transition-colors hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
