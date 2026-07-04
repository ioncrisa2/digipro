<script setup>
import { Button } from "@/components/ui/button";
import { Building2, Plus } from "lucide-vue-next";

defineProps({
  title: {
    type: String,
    default: "Permohonan Penilaian",
  },
  subtitle: {
    type: String,
    default: "Kelola semua permohonan penilaian properti Anda",
  },
  onCreate: {
    type: Function,
    required: true,
  },
  showAction: {
    type: Boolean,
    default: true,
  },
});
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="customer-kicker">Dossier Penilaian</p>
        <h1 class="customer-display mt-2 text-3xl font-semibold text-[var(--customer-ink)]">{{ title }}</h1>
        <p class="mt-2 flex items-center gap-2 text-sm text-[var(--customer-muted)]">
          <Building2 class="w-4 h-4" />
          {{ subtitle }}
        </p>
      </div>
      <Button v-if="showAction" @click="onCreate" size="lg" class="min-h-11 shadow-sm">
        <Plus class="w-5 h-5 mr-2" />
        Buat Permohonan
      </Button>
    </div>
  </div>
</template>
