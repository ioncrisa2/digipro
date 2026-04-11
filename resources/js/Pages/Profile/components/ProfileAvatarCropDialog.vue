<script setup>
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Loader2 } from "lucide-vue-next";

defineProps({
  open: { type: Boolean, default: false },
  avatarForm: { type: Object, required: true },
  cropPreviewUrl: { type: String, default: "" },
  cropScale: { type: Number, default: 1 },
  cropCanvasRef: { type: [Object, Function], default: null },
});

const emit = defineEmits([
  "update:open",
  "start-drag",
  "drag",
  "end-drag",
  "update-crop-scale",
  "apply-crop",
]);
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>Atur Foto Profil</DialogTitle>
        <DialogDescription>Geser dan zoom agar foto pas di frame.</DialogDescription>
      </DialogHeader>

      <div class="mt-2">
        <div class="w-full rounded-lg border bg-slate-50 p-2">
          <div class="h-72 w-full overflow-hidden rounded-md bg-slate-100 flex items-center justify-center">
            <canvas
              v-if="cropPreviewUrl"
              :ref="cropCanvasRef"
              width="320"
              height="320"
              class="h-full w-full cursor-move"
              @pointerdown="emit('start-drag', $event)"
              @pointermove="emit('drag', $event)"
              @pointerup="emit('end-drag', $event)"
              @pointerleave="emit('end-drag', $event)"
            ></canvas>
          </div>
          <div class="mt-3 flex items-center gap-3">
            <span class="text-xs text-slate-500">Zoom</span>
            <input
              type="range"
              min="1"
              max="2.5"
              step="0.01"
              :value="cropScale"
              @input="emit('update-crop-scale', $event)"
              class="w-full"
            />
          </div>
        </div>
      </div>

      <DialogFooter class="gap-2 sm:justify-end">
        <Button variant="outline" type="button" @click="emit('update:open', false)">
          Batal
        </Button>
        <Button
          type="button"
          class="bg-slate-900 hover:bg-slate-800"
          :disabled="avatarForm.processing"
          @click="emit('apply-crop')"
        >
          <Loader2 v-if="avatarForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          Simpan Foto
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
