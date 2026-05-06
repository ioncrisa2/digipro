<script setup>
import { computed, defineAsyncComponent, ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import ImageUpload from "@/components/admin/ImageUpload.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  ArrowLeft,
  ChevronLeft,
  ChevronRight,
  Download,
  ExternalLink,
  Eye,
  FileText,
  Image as ImageIcon,
  Upload,
} from "lucide-vue-next";

const ReviewerFilePreview = defineAsyncComponent(() => import("@/components/reviewer/ReviewerFilePreview.vue"));

const props = defineProps({
  record: { type: Object, required: true },
  batch: { type: Object, required: true },
  submit_url: { type: String, required: true },
  back_url: { type: String, required: true },
});

const form = useForm({
  replacements: {},
  field_values: Object.fromEntries(
    props.batch.items
      .filter(item => ['asset_field', 'request_field'].includes(item.item_type) && item.field)
      .map(item => [item.id,
        item.field?.replacement_value?.value ?? item.field?.original_value?.value ?? ''
      ])
  ),
});

const previewDialogOpen = ref(false);
const previewMode = ref("document");
const previewDocumentItemId = ref(null);
const previewImageIndex = ref(0);

const formatDateTime = (value) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
};

const formatFileSize = (bytes) => {
  const size = Number(bytes || 0);
  if (!size) return "0 B";

  const units = ["B", "KB", "MB", "GB"];
  const index = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1);
  const value = size / (1024 ** index);

  return `${new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: index === 0 ? 0 : 2,
  }).format(value)} ${units[index]}`;
};

const isImageItem = (item) => {
  return item?.accept === ".jpg,.jpeg,.png,.webp"
    || String(item?.original_file?.mime || "").startsWith("image/");
};

const imagePreviewItems = computed(() => (props.batch.items ?? []).filter((item) => isImageItem(item) && item.original_file?.url));
const currentPreviewItem = computed(() => {
  if (previewMode.value === "image") {
    return imagePreviewItems.value[previewImageIndex.value] ?? null;
  }

  return (props.batch.items ?? []).find((item) => item.id === previewDocumentItemId.value) ?? null;
});
const currentPreviewFile = computed(() => currentPreviewItem.value?.original_file ?? null);
const hasPrevImage = computed(() => previewImageIndex.value > 0);
const hasNextImage = computed(() => previewImageIndex.value < imagePreviewItems.value.length - 1);

const setReplacementFile = (itemId, file) => {
  if (file instanceof File) {
    form.replacements[itemId] = file;
    return;
  }

  delete form.replacements[itemId];
};

const setFieldValue = (itemId, value) => {
  form.field_values[itemId] = value;
};

const fieldValue = (item) => {
  if (Object.prototype.hasOwnProperty.call(form.field_values, item.id)) {
    return form.field_values[item.id];
  }

  return item.field?.replacement_value?.value ?? item.field?.original_value?.value ?? '';
};

const fieldDisplayValue = (snapshot) => snapshot?.display ?? 'Belum diisi';
const fieldInputType = (item) => item?.field?.input_type ?? 'text';
const latestSubmittedFieldValue = (item) => item?.field?.replacement_value ?? null;
const hasLatestSubmittedFieldValue = (item) => latestSubmittedFieldValue(item) !== null;
const latestSubmittedLabel = (item) => String(item?.status) === 'rejected'
  ? 'Nilai terakhir yang ditolak'
  : 'Nilai terakhir dikirim';

const openPreview = (item) => {
  if (!item) return;

  if (isImageItem(item) && imagePreviewItems.value.length > 0) {
    const index = imagePreviewItems.value.findIndex((entry) => entry.id === item.id);
    previewImageIndex.value = index >= 0 ? index : 0;
    previewMode.value = "image";
  } else {
    previewDocumentItemId.value = item.id;
    previewMode.value = "document";
  }

  previewDialogOpen.value = true;
};

const closePreview = () => {
  previewDialogOpen.value = false;
  previewMode.value = "document";
  previewDocumentItemId.value = null;
  previewImageIndex.value = 0;
};

const showPrevImage = () => {
  if (!hasPrevImage.value) return;
  previewImageIndex.value -= 1;
};

const showNextImage = () => {
  if (!hasNextImage.value) return;
  previewImageIndex.value += 1;
};

const selectPreviewImage = (index) => {
  if (index < 0 || index >= imagePreviewItems.value.length) return;
  previewImageIndex.value = index;
};

const submit = () => {
  form.post(props.submit_url, {
    forceFormData: true,
    preserveScroll: true,
  });
};

const itemStatusLabel = (status) => {
  switch (String(status || '')) {
    case 'pending':
      return 'Menunggu Upload Ulang';
    case 'reuploaded':
      return 'Menunggu Review Admin';
    case 'rejected':
      return 'Perlu Revisi Lagi';
    default:
      return status || '-';
  }
};
</script>

<template>

  <Head :title="`Revisi Data & Dokumen - ${record.request_number}`" />

  <DashboardLayout>
    <div class="space-y-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-2">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold">Revisi Data & Dokumen</h1>
            <Badge variant="secondary">{{ record.request_number }}</Badge>
          </div>
          <p class="text-sm text-muted-foreground">
            Perbaiki hanya item yang diminta admin. Histori nilai lama dan file lama tetap tersimpan untuk audit.
          </p>
        </div>

        <Button variant="outline" as-child>
          <Link :href="back_url">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Kembali ke Detail Request
          </Link>
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Ringkasan Permintaan Revisi</CardTitle>
          <CardDescription>
            Batch #{{ batch.id }} dibuat pada {{ formatDateTime(batch.created_at) }}.
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-if="batch.admin_note"
            class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ batch.admin_note }}
          </div>

          <form class="space-y-4" @submit.prevent="submit">
            <div v-for="item in batch.items" :key="item.id" class="rounded-2xl border p-4">
              <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p class="font-medium text-slate-950">{{ item.target_label }}</p>
                    <p v-if="item.asset_address" class="text-xs text-muted-foreground">{{ item.asset_address }}</p>
                  </div>
                  <Badge variant="outline">
                    {{ itemStatusLabel(item.status) }}
                  </Badge>
                </div>

                <div class="rounded-xl border bg-slate-50 px-3 py-2 text-sm text-slate-700">
                  {{ item.issue_note }}
                </div>

                <div v-if="item.review_note"
                  class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">
                  <p class="text-xs font-semibold uppercase tracking-widest text-rose-700">Catatan Review Terbaru</p>
                  <p class="mt-2">{{ item.review_note }}</p>
                </div>

                <div class="grid gap-3 lg:grid-cols-[1.05fr_0.95fr]">
                  <div class="rounded-xl border p-3">
                    <template v-if="item.field">
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Sebelumnya</p>
                      <div class="mt-3 rounded-2xl border bg-slate-50 p-4">
                        <p class="text-sm font-medium text-slate-950">{{ fieldDisplayValue(item.field.original_value) }}
                        </p>
                      </div>
                    </template>

                    <template v-else>
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">File Sebelumnya</p>

                      <button v-if="item.original_file" type="button"
                        class="mt-3 block w-full overflow-hidden rounded-2xl border bg-slate-50 text-left transition hover:border-slate-300"
                        @click="openPreview(item)">
                        <template v-if="isImageItem(item)">
                          <div class="relative">
                            <img :src="item.original_file.url" :alt="item.original_file.original_name"
                              class="h-48 w-full object-cover" />
                            <div
                              class="absolute right-3 top-3 rounded-full bg-black/70 px-3 py-1 text-xs font-medium text-white">
                              Lihat Foto
                            </div>
                          </div>
                        </template>

                        <template v-else>
                          <div
                            class="flex h-48 items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
                            <div class="flex flex-col items-center gap-3 text-center text-slate-600">
                              <FileText class="h-12 w-12" />
                              <p class="text-sm font-medium">Preview Dokumen</p>
                              <p class="max-w-xs text-xs text-slate-500">
                                Klik untuk melihat file yang sebelumnya diunggah customer.
                              </p>
                            </div>
                          </div>
                        </template>

                        <div class="space-y-1 p-4">
                          <p class="text-sm font-semibold text-slate-950">{{ item.original_file.original_name }}</p>
                          <p class="text-xs text-muted-foreground">
                            {{ formatFileSize(item.original_file.size) }} • {{
                              formatDateTime(item.original_file.created_at) }}
                          </p>
                          <div class="pt-1 text-xs font-medium text-slate-700">
                            <span class="inline-flex items-center gap-1">
                              <Eye class="h-3.5 w-3.5" />
                              Buka Preview
                            </span>
                          </div>
                        </div>
                      </button>

                      <div v-else
                        class="mt-3 flex h-48 flex-col items-center justify-center rounded-2xl border border-dashed bg-slate-50 px-4 text-center text-sm text-muted-foreground">
                        <ImageIcon class="mb-3 h-10 w-10" />
                        Tidak ada file sebelumnya. Anda perlu mengunggah file baru untuk item ini.
                      </div>
                    </template>
                  </div>

                  <div v-if="item.field" class="rounded-xl border p-3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nilai Perbaikan</p>
                    <div v-if="hasLatestSubmittedFieldValue(item)" class="mt-3 rounded-xl border bg-slate-50 p-3">
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ latestSubmittedLabel(item) }}</p>
                      <p class="mt-2 text-sm font-medium text-slate-950">
                        {{ fieldDisplayValue(latestSubmittedFieldValue(item)) }}
                      </p>
                    </div>
                    <div v-else class="mt-3 rounded-xl border border-dashed bg-slate-50 p-3">
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Belum Ada Kiriman Revisi</p>
                      <p class="mt-2 text-sm text-slate-600">
                        Isi nilai baru di bawah untuk mengganti data sebelumnya.
                      </p>
                    </div>
                    <div class="mt-3 space-y-2">
                      <Label :for="`field_${item.id}`">Nilai Baru</Label>

                      <Select v-if="fieldInputType(item) === 'select'" :model-value="String(fieldValue(item) ?? '')"
                        @update:model-value="setFieldValue(item.id, $event)">
                        <SelectTrigger :id="`field_${item.id}`">
                          <SelectValue placeholder="Pilih nilai" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem v-for="option in item.field.options || []" :key="option.value"
                            :value="String(option.value)">
                            {{ option.label }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <Textarea v-else-if="fieldInputType(item) === 'textarea'" :id="`field_${item.id}`"
                        :model-value="fieldValue(item)" rows="4"
                        :placeholder="item.field.placeholder || 'Isi nilai baru'"
                        @update:model-value="setFieldValue(item.id, $event)" />

                      <Input v-else :id="`field_${item.id}`"
                        :type="['number', 'integer'].includes(fieldInputType(item)) ? 'number' : 'text'"
                        :step="fieldInputType(item) === 'number' ? '0.0000001' : '1'" :model-value="fieldValue(item)"
                        :placeholder="item.field.placeholder || 'Isi nilai baru'"
                        @update:model-value="setFieldValue(item.id, $event)" />
                    </div>
                    <p v-if="form.errors[`field_values.${item.id}`]" class="mt-2 text-xs text-rose-600">
                      {{ form.errors[`field_values.${item.id}`] }}
                    </p>
                  </div>

                  <div v-else class="rounded-xl border p-3">
                    <Label :for="`replacement_${item.id}`">Upload File Pengganti</Label>
                    <div v-if="item.replacement_file" class="mt-3 rounded-xl border bg-slate-50 p-3">
                      <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Upload Terakhir</p>
                      <p class="mt-2 text-sm font-medium text-slate-950">{{ item.replacement_file.original_name }}</p>
                      <p class="mt-1 text-xs text-muted-foreground">
                        {{ formatFileSize(item.replacement_file.size) }} - {{
                          formatDateTime(item.replacement_file.created_at) }}
                      </p>
                      <div class="mt-3">
                        <Button variant="outline" size="sm" as-child>
                          <a :href="item.replacement_file.url" target="_blank" rel="noopener noreferrer">
                            <ExternalLink class="mr-2 h-4 w-4" />
                            Buka Upload Terakhir
                          </a>
                        </Button>
                      </div>
                    </div>
                    <div class="mt-2">
                      <ImageUpload :model-value="form.replacements[item.id] ?? null"
                        :preview-kind="isImageItem(item) ? 'image' : 'document'" :accept="item.accept" :multiple="false"
                        :title="isImageItem(item) ? 'Upload foto pengganti' : 'Upload dokumen pengganti'" :description="isImageItem(item)
                          ? 'Pilih satu foto pengganti. Preview akan tampil langsung di area ini.'
                          : 'Pilih satu file pengganti. Preview dokumen akan tampil di area ini.'"
                        @update:model-value="setReplacementFile(item.id, $event)" />
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                      Format yang diterima: {{ item.accept }}.
                    </p>
                    <p v-if="form.errors[`replacements.${item.id}`]" class="mt-2 text-xs text-rose-600">
                      {{ form.errors[`replacements.${item.id}`] }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end">
              <Button type="submit" :disabled="form.processing">
                <Upload class="mr-2 h-4 w-4" />
                Kirim Revisi
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>

    <Dialog :open="previewDialogOpen" @update:open="(open) => { if (!open) closePreview() }">
      <DialogContent class="max-h-[92vh] overflow-hidden p-0 sm:max-w-5xl">
        <DialogHeader class="border-b px-6 py-4">
          <DialogTitle class="truncate text-left">{{ currentPreviewFile?.original_name ||
            currentPreviewItem?.target_label
            || 'Preview File' }}</DialogTitle>
          <DialogDescription class="flex flex-wrap items-center gap-2 text-left">
            <span>{{ currentPreviewItem?.target_label || '-' }}</span>
            <template v-if="currentPreviewFile?.size">
              <span>•</span>
              <span>{{ formatFileSize(currentPreviewFile.size) }}</span>
            </template>
            <template v-if="currentPreviewFile?.created_at">
              <span>•</span>
              <span>{{ formatDateTime(currentPreviewFile.created_at) }}</span>
            </template>
          </DialogDescription>
        </DialogHeader>

        <div class="flex max-h-[calc(92vh-88px)] flex-col bg-muted/10">
          <div class="flex-1 overflow-auto p-4">
            <div class="flex min-h-[60vh] items-center justify-center overflow-hidden rounded-xl border bg-background">
              <template v-if="previewMode === 'image' && currentPreviewFile?.url">
                <div class="relative flex h-[68vh] w-full items-center justify-center overflow-hidden bg-slate-100">
                  <Button variant="secondary" size="icon"
                    class="absolute left-4 top-1/2 z-10 -translate-y-1/2 shadow-sm" :disabled="!hasPrevImage"
                    @click="showPrevImage">
                    <ChevronLeft class="h-5 w-5" />
                  </Button>

                  <img :src="currentPreviewFile.url" :alt="currentPreviewFile.original_name"
                    class="max-h-[68vh] w-full object-contain" />

                  <Button variant="secondary" size="icon"
                    class="absolute right-4 top-1/2 z-10 -translate-y-1/2 shadow-sm" :disabled="!hasNextImage"
                    @click="showNextImage">
                    <ChevronRight class="h-5 w-5" />
                  </Button>

                  <div
                    class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/70 px-3 py-1 text-xs font-medium text-white">
                    {{ previewImageIndex + 1 }} / {{ imagePreviewItems.length }}
                  </div>
                </div>
              </template>

              <div v-else-if="currentPreviewFile?.url" class="h-[68vh] w-full overflow-auto">
                <ReviewerFilePreview :url="currentPreviewFile.url" :name="currentPreviewFile.original_name" />
              </div>

              <div v-else class="flex flex-col items-center gap-3 px-6 text-center text-muted-foreground">
                <FileText class="h-10 w-10" />
                <p class="text-sm">Preview file tidak tersedia untuk item ini.</p>
              </div>
            </div>

            <div v-if="previewMode === 'image' && imagePreviewItems.length > 1" class="mt-4 space-y-3">
              <div class="flex gap-3 overflow-x-auto pb-1">
                <button v-for="(item, index) in imagePreviewItems" :key="`revision-thumb-${item.id}`" type="button"
                  class="relative h-20 w-28 shrink-0 overflow-hidden rounded-xl border bg-background transition"
                  :class="index === previewImageIndex ? 'border-primary ring-2 ring-primary/20' : 'border-border/60'"
                  @click="selectPreviewImage(index)">
                  <img :src="item.original_file.url" :alt="item.original_file.original_name" loading="lazy"
                    decoding="async" class="h-full w-full object-cover" />
                </button>
              </div>
            </div>

            <div v-if="currentPreviewItem?.issue_note"
              class="mt-4 rounded-xl border bg-amber-50 px-4 py-3 text-sm text-amber-900">
              <p class="text-xs font-semibold uppercase tracking-widest text-amber-700">Catatan Revisi</p>
              <p class="mt-2">{{ currentPreviewItem.issue_note }}</p>
            </div>
          </div>

          <DialogFooter class="flex items-center justify-end gap-2 border-t bg-background px-6 py-4">
            <Button v-if="currentPreviewFile?.url" variant="ghost" as-child>
              <a :href="currentPreviewFile.url" download>
                <Download class="mr-2 h-4 w-4" />
                Download
              </a>
            </Button>
            <Button v-if="currentPreviewFile?.url" variant="outline" as-child>
              <a :href="currentPreviewFile.url" target="_blank" rel="noopener noreferrer">
                <ExternalLink class="mr-2 h-4 w-4" />
                Buka di Tab Baru
              </a>
            </Button>
          </DialogFooter>
        </div>
      </DialogContent>
    </Dialog>
  </DashboardLayout>
</template>
