<script setup>
import { Button } from "@/components/ui/button";
import { Home, Plus } from "lucide-vue-next";

defineProps({
  count: {
    type: Number,
    default: 0,
  },
  onAdd: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-semibold flex items-center gap-2">
      <Home class="w-5 h-5" />
      Daftar Aset ({{ count }} Unit)
    </h2>
    <Button @click="onAdd" size="sm">
      <Plus class="w-4 h-4 mr-2" />
      Tambah Aset
    </Button>
  </div>
</template>
