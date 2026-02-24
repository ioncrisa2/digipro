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
});
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900">{{ title }}</h1>
        <p class="text-sm text-slate-600 mt-1.5 flex items-center gap-2">
          <Building2 class="w-4 h-4" />
          {{ subtitle }}
        </p>
      </div>
      <Button @click="onCreate" size="lg" class="shadow-sm">
        <Plus class="w-5 h-5 mr-2" />
        Buat Permohonan
      </Button>
    </div>
  </div>
</template>
