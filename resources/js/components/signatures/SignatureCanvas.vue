<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { RotateCcw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps({
  disabled: { type: Boolean, default: false },
  label: { type: String, default: 'Area tanda tangan' },
});

const emit = defineEmits(['change']);

const canvas = ref(null);
const hasStroke = ref(false);
let drawing = false;
let resizeObserver = null;

const context = () => canvas.value?.getContext('2d') ?? null;

const configureContext = () => {
  const ctx = context();
  if (!ctx) return;

  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.lineWidth = 2.5;
  ctx.strokeStyle = '#0f172a';
};

const resize = () => {
  const element = canvas.value;
  if (!element) return;

  const previous = hasStroke.value ? element.toDataURL('image/png') : null;
  const rect = element.getBoundingClientRect();
  const ratio = Math.min(window.devicePixelRatio || 1, 2);

  element.width = Math.max(1, Math.round(rect.width * ratio));
  element.height = Math.max(1, Math.round(rect.height * ratio));

  const ctx = context();
  ctx?.setTransform(ratio, 0, 0, ratio, 0, 0);
  configureContext();

  if (previous) {
    const image = new Image();
    image.onload = () => ctx?.drawImage(image, 0, 0, rect.width, rect.height);
    image.src = previous;
  }
};

const point = (event) => {
  const rect = canvas.value.getBoundingClientRect();

  return {
    x: event.clientX - rect.left,
    y: event.clientY - rect.top,
  };
};

const startDrawing = (event) => {
  if (props.disabled || !canvas.value) return;

  drawing = true;
  canvas.value.setPointerCapture?.(event.pointerId);
  const ctx = context();
  const current = point(event);
  ctx?.beginPath();
  ctx?.moveTo(current.x, current.y);
};

const draw = (event) => {
  if (!drawing || props.disabled) return;

  const ctx = context();
  const current = point(event);
  ctx?.lineTo(current.x, current.y);
  ctx?.stroke();
  hasStroke.value = true;
};

const emitImage = () => {
  if (!canvas.value || !hasStroke.value) return;

  canvas.value.toBlob((blob) => {
    if (!blob) return;
    emit('change', new File([blob], 'signature-canvas.png', { type: 'image/png' }));
  }, 'image/png');
};

const stopDrawing = (event) => {
  if (!drawing) return;

  drawing = false;
  canvas.value?.releasePointerCapture?.(event.pointerId);
  context()?.closePath();
  emitImage();
};

const clear = () => {
  if (props.disabled || !canvas.value) return;

  const rect = canvas.value.getBoundingClientRect();
  context()?.clearRect(0, 0, rect.width, rect.height);
  hasStroke.value = false;
  emit('change', null);
};

onMounted(async () => {
  await nextTick();
  resize();
  resizeObserver = new ResizeObserver(resize);
  resizeObserver.observe(canvas.value);
});

onBeforeUnmount(() => resizeObserver?.disconnect());
</script>

<template>
  <div class="space-y-3">
    <div class="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm focus-within:border-slate-500 focus-within:ring-2 focus-within:ring-slate-200">
      <canvas
        ref="canvas"
        class="block aspect-[3/1] w-full touch-none cursor-crosshair bg-white disabled:cursor-not-allowed"
        :aria-label="label"
        role="img"
        @pointerdown.prevent="startDrawing"
        @pointermove.prevent="draw"
        @pointerup.prevent="stopDrawing"
        @pointercancel.prevent="stopDrawing"
        @pointerleave="stopDrawing"
      />
      <div class="border-t border-dashed border-slate-200 px-4 py-2 text-center text-xs text-slate-500">
        Gunakan mouse, stylus, atau sentuhan untuk menandatangani area di atas.
      </div>
    </div>

    <div class="flex items-center justify-between gap-3">
      <span class="text-xs" :class="hasStroke ? 'text-emerald-700' : 'text-slate-500'">
        {{ hasStroke ? 'Tanda tangan siap disimpan.' : 'Area tanda tangan masih kosong.' }}
      </span>
      <Button type="button" variant="outline" size="sm" :disabled="disabled || !hasStroke" @click="clear">
        <RotateCcw class="mr-2 size-4" />
        Hapus
      </Button>
    </div>
  </div>
</template>
