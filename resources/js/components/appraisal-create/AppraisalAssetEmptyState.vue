<script setup>
import { Button } from "@/components/ui/button";
import { AlertCircle, Plus } from "lucide-vue-next";

defineProps({
  onAdd: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <div class="text-center py-12 border-2 border-dashed rounded-lg">
    <AlertCircle class="w-12 h-12 mx-auto text-muted-foreground mb-4" />
    <p class="text-muted-foreground mb-4">Belum ada aset yang ditambahkan</p>
    <Button @click="onAdd">
      <Plus class="w-4 h-4 mr-2" />
      Tambah Aset Pertama
    </Button>
  </div>
</template>
