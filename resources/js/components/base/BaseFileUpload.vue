<script setup>
import { ref, watch, computed, onBeforeUnmount } from "vue";
import { Button } from "@/components/ui/button";
import { useNotification } from "@/composables/useNotification";
import {
  CloudUpload,
  X,
  FileText,
  Eye,
  Image as ImageIcon,
  Trash2,
} from "lucide-vue-next";

const props = defineProps({
  label: { type: String, default: "" },
  accept: { type: String, default: ".pdf,.jpg,.jpeg,.png" },
  multiple: { type: Boolean, default: false },
  modelValue: { type: [Array, Object, File, null], default: null },

  // ✅ limits
  maxFiles: { type: Number, default: 10 },       // only for multiple
  maxFileSizeMb: { type: Number, default: 15 },  // per file
  maxTotalSizeMb: { type: Number, default: 30 }, // per field
  helperText: { type: String, default: "" },     // override helper text
});

const emit = defineEmits(["update:modelValue", "error"]);
const { notify } = useNotification();

const fileInput = ref(null);
const isDragging = ref(false);
const files = ref([]);
const localError = ref("");

const setError = (message) => {
  localError.value = message || "";
  if (message) {
    emit("error", message);
    notify("error", message);
  }
};

const MB = (n) => n * 1024 * 1024;

const isPdf = (file) => file?.type === "application/pdf";
const isImage = (file) => file?.type?.startsWith("image/");

const normalizeAccept = (accept) =>
  String(accept || "")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);

const acceptTokens = computed(() => normalizeAccept(props.accept));

const extensionOf = (name) => {
  const s = String(name || "");
  const i = s.lastIndexOf(".");
  return i >= 0 ? s.slice(i).toLowerCase() : "";
};

const isFileAllowed = (f) => {
  // If accept is empty, allow.
  const tokens = acceptTokens.value;
  if (!tokens.length) return true;

  const ext = extensionOf(f?.name);
  const mime = String(f?.type || "");

  return tokens.some((t) => {
    if (t.startsWith(".")) return ext === t.toLowerCase();
    if (t.endsWith("/*")) return mime.startsWith(t.slice(0, -1));
    return mime === t;
  });
};

const toDisplayItem = (file) => {
  // ✅ If already a display item (from previous state), keep it
  if (file && typeof file === "object" && file.url && file.file) return file;

  // ✅ Placeholder support (e.g., draft restored with file name only)
  if (typeof file === "string") {
    return {
      file: file,
      url: null,
      name: file,
      type: "",
      sizeBytes: 0,
      size: "",
      isPdf: false,
      isImage: false,
      isPlaceholder: true,
    };
  }

  return {
    file,
    url: file ? URL.createObjectURL(file) : null,
    name: file?.name ?? "Unknown",
    type: file?.type ?? "",
    sizeBytes: file?.size ?? 0,
    size: ((file?.size ?? 0) / 1024 / 1024).toFixed(2) + " MB",
    isPdf: isPdf(file),
    isImage: isImage(file),
    isPlaceholder: false,
  };
};

const isGalleryMode = computed(() => {
  if (files.value.length === 0) return false;
  return files.value.every((f) => f.isImage);
});

const cleanupUrls = (items) => {
  for (const item of items || []) {
    if (item?.url?.startsWith("blob:")) URL.revokeObjectURL(item.url);
  }
};

onBeforeUnmount(() => cleanupUrls(files.value));

const totalSizeMb = computed(() => {
  const bytes = files.value.reduce((s, it) => s + (it?.file?.size || 0), 0);
  return (bytes / 1024 / 1024).toFixed(2);
});

const countLabel = computed(() => {
  if (!props.multiple) return "";
  return `${files.value.length} / ${props.maxFiles}`;
});

const defaultHelper = computed(() => {
  const parts = [];
  if (props.multiple) parts.push(`Batas file: ${props.maxFiles} file`);
  if (props.maxFileSizeMb) parts.push(`maks ${props.maxFileSizeMb}MB/file`);
  if (props.maxTotalSizeMb) parts.push(`maks total ${props.maxTotalSizeMb}MB`);
  return parts.join(" • ");
});

const helper = computed(() => props.helperText || defaultHelper.value);

watch(
  () => props.modelValue,
  (newVal) => {
    // clear local error when parent value changes
    localError.value = "";

    // Clear files if null or empty array
    if (!newVal || (Array.isArray(newVal) && newVal.length === 0)) {
      cleanupUrls(files.value);
      files.value = [];
      if (fileInput.value) fileInput.value.value = "";
      return;
    }

    // MULTIPLE: modelValue should be array of File
    if (props.multiple && Array.isArray(newVal)) {
      const currentFiles = files.value.map((f) => f.file);
      const newFiles = newVal;

      const filesChanged =
        currentFiles.length !== newFiles.length ||
        currentFiles.some((f, i) => f !== newFiles[i]);

      if (filesChanged) {
        cleanupUrls(files.value);
        files.value = newFiles.map((file) => toDisplayItem(file));
      }
      return;
    }

    // SINGLE: modelValue should be File
    if (!props.multiple && newVal) {
      const currentFile = files.value[0]?.file;
      if (currentFile !== newVal) {
        cleanupUrls(files.value);
        files.value = [toDisplayItem(newVal)];
      }
    }
  },
  { immediate: true }
);

const trigger = () => fileInput.value?.click();

const handleFiles = (list) => {
  localError.value = "";
  const selectedRaw = Array.from(list || []);
  if (!selectedRaw.length) return;

  // filter per-file size + type
  const selected = [];
  let firstRejectedMessage = "";
  for (const f of selectedRaw) {
    let rejectedMessage = "";

    if (!isFileAllowed(f)) {
      rejectedMessage = `Format file "${f.name}" tidak didukung.`;
    }

    if (!rejectedMessage && props.maxFileSizeMb && f.size > MB(props.maxFileSizeMb)) {
      rejectedMessage = `File "${f.name}" terlalu besar. Maks ${props.maxFileSizeMb}MB per file.`;
    }

    if (rejectedMessage) {
      if (!firstRejectedMessage) firstRejectedMessage = rejectedMessage;
      continue;
    }

    selected.push(f);
  }

  if (firstRejectedMessage) {
    setError(firstRejectedMessage);
  }

  if (!selected.length) return;

  // SINGLE
  if (!props.multiple) {
    cleanupUrls(files.value);
    const item = toDisplayItem(selected[0]);
    files.value = [item];
    emit("update:modelValue", item.file);
    return;
  }

  // MULTIPLE: enforce max count
  const remaining = Math.max(0, props.maxFiles - files.value.length);
  if (remaining <= 0) {
    setError(`Maksimal ${props.maxFiles} file.`);
    return;
  }

  const sliced = selected.slice(0, remaining);
  if (selected.length > remaining) {
    setError(`Maksimal ${props.maxFiles} file. Sisanya diabaikan.`);
  }

  const processed = sliced.map(toDisplayItem);

  // enforce max total size
  const currentBytes = files.value.reduce((s, it) => s + (it?.file?.size || 0), 0);
  const incomingBytes = processed.reduce((s, it) => s + (it?.file?.size || 0), 0);
  const newBytes = currentBytes + incomingBytes;

  if (props.maxTotalSizeMb && newBytes > MB(props.maxTotalSizeMb)) {
    cleanupUrls(processed);
    setError(`Total upload melebihi ${props.maxTotalSizeMb}MB untuk field ini.`);
    return;
  }

  files.value = [...files.value, ...processed];
  emit("update:modelValue", files.value.map((item) => item.file));
};

const handleChange = (e) => handleFiles(e.target.files);

const onDrop = (e) => {
  isDragging.value = false;
  handleFiles(e.dataTransfer.files);
};

const removeFile = (index) => {
  const removed = files.value.splice(index, 1);
  cleanupUrls(removed);

  if (props.multiple) {
    emit("update:modelValue", files.value.map((item) => item.file));
  } else {
    emit("update:modelValue", null);
    if (fileInput.value) fileInput.value.value = "";
  }
};

const previewPdf = (url) => window.open(url, "_blank");
</script>

<template>
  <div class="grid gap-2">
    <!-- Label + Counter -->
    <div v-if="label || (multiple && countLabel)" class="flex items-center justify-between">
      <label v-if="label" class="text-sm font-medium text-foreground">
        {{ label }}
      </label>

      <span v-if="multiple" class="text-xs text-muted-foreground">
        {{ countLabel }}
      </span>
    </div>

    <!-- Dropzone -->
    <div
      v-if="props.multiple || files.length === 0"
      class="rounded-lg border border-dashed p-6 text-center transition cursor-pointer select-none"
      :class="
        isDragging
          ? 'border-primary bg-muted/40'
          : 'border-muted-foreground/30 hover:border-primary/60 hover:bg-muted/30'
      "
      @click="trigger"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
    >
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        :accept="accept"
        :multiple="multiple"
        @change="handleChange"
      />

      <div class="mx-auto mb-2 h-10 w-10 rounded-full bg-muted flex items-center justify-center">
        <CloudUpload class="h-5 w-5 text-muted-foreground" />
      </div>

      <div class="text-xs text-muted-foreground">
        <span class="font-semibold text-foreground">Klik upload</span> atau drag file
      </div>
      <div class="text-[10px] text-muted-foreground/70 mt-1 uppercase">
        {{ accept.replace(/\./g, " ") }}
      </div>
    </div>

    <!-- Helper -->
    <div v-if="helper" class="text-[11px] text-muted-foreground">
      {{ helper }}
      <span v-if="multiple" class="ml-2">• Terpilih: {{ totalSizeMb }}MB</span>
    </div>

    <!-- Inline error (validation/client-side) -->
    <div v-if="localError" class="text-xs text-destructive">
      {{ localError }}
    </div>

    <!-- Preview -->
    <div v-if="files.length > 0">
      <!-- Gallery mode (all images) -->
      <div v-if="isGalleryMode" class="grid grid-cols-3 sm:grid-cols-4 gap-3">
        <div
          v-for="(item, i) in files"
          :key="item.url || (item.name + ':' + i)"
          class="relative group aspect-square rounded-lg overflow-hidden border bg-muted"
        >
          <img
            :src="item.url"
            :alt="item.name"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            @error="
              (e) =>
                (e.target.src =
                  'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect fill=\'%23ddd\' width=\'100\' height=\'100\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' fill=\'%23999\'%3EError%3C/text%3E%3C/svg%3E')
            "
          />

          <div
            class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center"
          >
            <Button
              type="button"
              size="icon"
              variant="destructive"
              class="h-8 w-8"
              @click.stop="removeFile(i)"
              title="Hapus"
            >
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      <!-- List mode (pdf / mixed) -->
      <div v-else class="grid gap-3">
        <div
          v-for="(item, i) in files"
          :key="item.url || (item.name + ':' + i)"
          class="rounded-lg border bg-background p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-3">
            <!-- Left -->
            <div class="flex items-start gap-3 min-w-0 flex-1">
              <div
                class="h-10 w-10 rounded-lg border flex items-center justify-center shrink-0"
                :class="
                  item.isPdf
                    ? 'bg-red-50 text-red-600 border-red-200'
                    : 'bg-muted text-muted-foreground'
                "
              >
                <span v-if="item.isPdf" class="text-[10px] font-bold">PDF</span>
                <ImageIcon v-else-if="item.isImage" class="h-5 w-5" />
                <FileText v-else class="h-5 w-5" />
              </div>

              <div class="min-w-0 flex-1">
                <div class="text-sm font-medium text-foreground clamp-2" :title="item.name">
                  {{ item.name }}
                </div>

                <div class="mt-1 flex items-center gap-2 flex-wrap">
                  <div class="text-[10px] text-muted-foreground">{{ item.size }}</div>

                  <button
                    v-if="item.isPdf && item.url"
                    type="button"
                    class="inline-flex items-center gap-1 text-[10px] text-primary hover:underline"
                    @click.stop="previewPdf(item.url)"
                  >
                    <Eye class="h-3 w-3" />
                    Lihat
                  </button>

                  <span v-if="item.isPlaceholder" class="text-[10px] text-amber-600">
                    (perlu upload ulang)
                  </span>
                </div>
              </div>
            </div>

            <!-- Right -->
            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="text-muted-foreground hover:text-destructive"
              @click.stop="removeFile(i)"
              title="Hapus"
            >
              <X class="h-5 w-5" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.clamp-2 {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  line-clamp: 2;
  overflow: hidden;
}
</style>
