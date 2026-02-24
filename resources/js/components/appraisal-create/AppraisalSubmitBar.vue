<script setup>
import { Button } from "@/components/ui/button";
import { Send } from "lucide-vue-next";

defineProps({
  canSubmit: {
    type: Boolean,
    required: true,
  },
  submitting: {
    type: Boolean,
    required: true,
  },
  onCancel: {
    type: Function,
    required: true,
  },
  onSubmit: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <div class="flex justify-between items-center pt-6 border-t">
    <Button variant="outline" @click="onCancel">
      Batal
    </Button>

    <Button
      @click="onSubmit"
      :disabled="!canSubmit"
      class="bg-green-600 hover:bg-green-700"
    >
      <span v-if="submitting">Mengirim...</span>
      <span v-else>Kirim Permohonan</span>
      <Send class="w-4 h-4 ml-2" />
    </Button>
  </div>
</template>
