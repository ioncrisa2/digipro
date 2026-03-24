<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { ImagePlus, LoaderCircle, Upload, X } from 'lucide-vue-next';

const props = defineProps({
  modelValue: {
    type: [File, Array, Object, null],
    default: null,
  },
  multiple: {
    type: Boolean,
    default: false,
  },
  accept: {
    type: String,
    default: 'image/*',
  },
  existing: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Upload gambar',
  },
  description: {
    type: String,
    default: 'Pilih gambar atau drag-and-drop ke area ini.',
  },
  maxFiles: {
    type: Number,
    default: null,
  },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const isDragging = ref(false);
const objectUrls = ref([]);
const previewItems = ref([]);

const selectedFiles = computed(() => {
  if (props.multiple) {
    return Array.isArray(props.modelValue) ? props.modelValue.filter((file) => file instanceof File) : [];
  }

  return props.modelValue instanceof File ? [props.modelValue] : [];
});

const showExistingItems = computed(() => selectedFiles.value.length === 0);

const displayItems = computed(() => {
  if (!showExistingItems.value) {
    return previewItems.value;
  }

  return props.existing.map((item, index) => ({
    key: `existing-${index}`,
    url: item.url,
    name: item.name || 'Gambar tersimpan',
    sizeLabel: item.sizeLabel || '',
    existing: true,
  }));
});

const revokeAllObjectUrls = () => {
  objectUrls.value.forEach((url) => URL.revokeObjectURL(url));
  objectUrls.value = [];
};

watch(
  selectedFiles,
  (files) => {
    revokeAllObjectUrls();

    previewItems.value = files.map((file, index) => {
      const url = URL.createObjectURL(file);
      objectUrls.value.push(url);

      return {
        key: `new-${index}-${file.name}`,
        url,
        name: file.name,
        sizeLabel: formatBytes(file.size),
        existing: false,
      };
    });
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  revokeAllObjectUrls();
});

function formatBytes(bytes) {
  if (!Number.isFinite(bytes) || bytes <= 0) {
    return '';
  }

  const units = ['B', 'KB', 'MB', 'GB'];
  const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  const value = bytes / (1024 ** index);

  return `${index === 0 ? Math.round(value) : value.toFixed(1)} ${units[index]}`;
}

function emitFiles(files) {
  if (props.multiple) {
    const nextFiles = props.maxFiles ? files.slice(0, props.maxFiles) : files;
    emit('update:modelValue', nextFiles);
    return;
  }

  emit('update:modelValue', files[0] ?? null);
}

function handleFiles(fileList) {
  const incomingFiles = Array.from(fileList ?? []).filter((file) => file.type.startsWith('image/'));
  if (incomingFiles.length === 0) {
    return;
  }

  if (props.multiple) {
    emitFiles([...selectedFiles.value, ...incomingFiles]);
  } else {
    emitFiles([incomingFiles[0]]);
  }
}

function openPicker() {
  if (props.disabled) {
    return;
  }

  fileInput.value?.click();
}

function onInputChange(event) {
  handleFiles(event.target.files);
  if (fileInput.value) {
    fileInput.value.value = '';
  }
}

function onDrop(event) {
  if (props.disabled) {
    return;
  }

  event.preventDefault();
  isDragging.value = false;
  handleFiles(event.dataTransfer?.files);
}

function removeSelected(index) {
  if (!props.multiple) {
    emit('update:modelValue', null);
    return;
  }

  const nextFiles = selectedFiles.value.filter((_, fileIndex) => fileIndex !== index);
  emit('update:modelValue', nextFiles);
}
</script>

<template>
  <div class="space-y-3">
    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      :multiple="multiple"
      class="hidden"
      @change="onInputChange"
    >

    <div
      class="rounded-2xl border border-dashed bg-slate-50/60 transition-colors"
      :class="[
        isDragging ? 'border-slate-950 bg-slate-100' : 'border-slate-300',
        disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
      ]"
      @click="openPicker"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop="onDrop"
    >
      <div v-if="displayItems.length === 0" class="flex min-h-56 flex-col items-center justify-center gap-3 px-6 py-8 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-sm">
          <ImagePlus class="h-6 w-6 text-slate-600" />
        </div>
        <div class="space-y-1">
          <p class="text-sm font-semibold text-slate-900">{{ title }}</p>
          <p class="text-sm text-slate-600">{{ description }}</p>
        </div>
        <Button type="button" variant="outline" size="sm" :disabled="disabled">
          <Upload class="mr-2 h-4 w-4" />
          Pilih Gambar
        </Button>
      </div>

      <div
        v-else
        class="grid gap-4 p-4"
        :class="multiple ? 'sm:grid-cols-2 xl:grid-cols-3' : 'grid-cols-1'"
      >
        <div
          v-for="(item, index) in displayItems"
          :key="item.key"
          class="relative overflow-hidden rounded-2xl border bg-black/90"
        >
          <img :src="item.url" :alt="item.name" class="h-56 w-full object-cover opacity-90">

          <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 bg-gradient-to-b from-black/80 via-black/25 to-transparent p-3 text-white">
            <div class="min-w-0">
              <p class="truncate text-xs font-semibold">{{ item.name }}</p>
              <p v-if="item.sizeLabel" class="mt-1 text-[11px] text-white/80">{{ item.sizeLabel }}</p>
            </div>

            <Button
              v-if="!item.existing && !loading"
              type="button"
              variant="secondary"
              size="icon"
              class="h-8 w-8 rounded-full bg-black/50 text-white hover:bg-black/70"
              @click.stop="removeSelected(index)"
            >
              <X class="h-4 w-4" />
            </Button>
          </div>

          <div
            v-if="loading"
            class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/55 text-white"
          >
            <LoaderCircle class="h-7 w-7 animate-spin" />
            <p class="text-sm font-medium">Mengunggah gambar...</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
