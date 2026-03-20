<script setup>
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import StatusBadge from '@/components/reviewer/StatusBadge.vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
  Files,
  MapPinned,
  Search,
  Save,
  Scale,
  ExternalLink,
  Database,
  FileText,
  Settings2,
  BarChart3,
  CheckCircle2,
  AlertCircle,
  Info,
  FileImage,
  Eye,
  ChevronLeft,
  ChevronRight,
  ZoomIn,
  ZoomOut,
  RotateCcw,
} from 'lucide-vue-next';
import { formatArea, formatCurrency, formatDateTime, formatNumber } from '@/utils/reviewer';

const props = defineProps({
  asset: Object,
  fieldOptions: Object,
  searchDefaults: Object,
});

const ReviewerFilePreview = defineAsyncComponent(() => import('@/components/reviewer/ReviewerFilePreview.vue'));

const assetState = ref(props.asset);
const generalForm = reactive({ ...props.asset.general_data });
const searchForm = reactive({
  range_km: props.searchDefaults?.range_km ?? 10,
  limit: props.searchDefaults?.limit ?? 100,
});
const searchResults = ref([]);
const selectedIds = ref([]);
const busyGeneral = ref(false);
const busySearch = ref(false);
const busySync = ref(false);
const busyComparableUpdates = ref({});
const feedback = ref('');
const feedbackTone = ref('default');
const previewFile = ref(null);
const imageZoom = ref(1);

const fileCount = computed(() => assetState.value.files?.length ?? 0);
const comparablesCount = computed(() => assetState.value.comparables?.length ?? 0);

const isImageFile = (file) => {
  const mime = String(file?.mime || '').toLowerCase();
  const type = String(file?.type || '').toLowerCase();
  const name = String(file?.original_name || '').toLowerCase();

  return mime.startsWith('image/')
    || type.includes('image')
    || type.includes('foto')
    || type.includes('photo')
    || /\.(jpg|jpeg|png|webp|gif)$/i.test(name);
};

const isPdfFile = (file) => {
  const mime = String(file?.mime || '').toLowerCase();
  const name = String(file?.original_name || '').toLowerCase();

  return mime.includes('pdf') || name.endsWith('.pdf');
};

const documentFiles = computed(() => (assetState.value.files || []).filter((file) => !isImageFile(file)));
const imageFiles = computed(() => (assetState.value.files || []).filter((file) => isImageFile(file)));
const previewIsImage = computed(() => isImageFile(previewFile.value));
const previewImageIndex = computed(() => {
  if (!previewFile.value || !previewIsImage.value) {
    return -1;
  }

  return imageFiles.value.findIndex((file) => file.id === previewFile.value.id);
});
const hasPrevImage = computed(() => previewImageIndex.value > 0);
const hasNextImage = computed(() => previewImageIndex.value > -1 && previewImageIndex.value < imageFiles.value.length - 1);
const groupedImageFiles = computed(() => {
  const groups = new Map();

  imageFiles.value.forEach((file) => {
    const label = getFileTypeMeta(file).label;

    if (!groups.has(label)) {
      groups.set(label, []);
    }

    groups.get(label).push(file);
  });

  return Array.from(groups.entries()).map(([label, files]) => ({
    label,
    files,
  }));
});

const selectedItemsPayload = computed(() => {
  return searchResults.value
    .filter((item) => selectedIds.value.includes(item.id))
    .map((item) => item.raw);
});

const setFeedback = (message, tone = 'default') => {
  feedback.value = message;
  feedbackTone.value = tone;
};

const saveGeneralData = async () => {
  busyGeneral.value = true;
  setFeedback('');
  try {
    const response = await axios.post(assetState.value.general_data_update_url, generalForm);
    assetState.value = {
      ...assetState.value,
      ...response.data.asset,
      comparables: assetState.value.comparables,
      files: assetState.value.files,
    };
    setFeedback(response.data.message, 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Gagal menyimpan data umum.', 'error');
  } finally {
    busyGeneral.value = false;
  }
};

const searchComparables = async () => {
  busySearch.value = true;
  setFeedback('');
  try {
    const response = await axios.post(assetState.value.comparables_search_url, searchForm);
    searchResults.value = response.data.results || [];
    selectedIds.value = searchResults.value.filter((item) => item.is_selected).map((item) => item.id);
    setFeedback(response.data.message, 'success');
  } catch (error) {
    searchResults.value = [];
    setFeedback(error.response?.data?.message || 'Pencarian pembanding gagal.', 'error');
  } finally {
    busySearch.value = false;
  }
};

const syncComparables = async () => {
  busySync.value = true;
  setFeedback('');
  try {
    const response = await axios.post(assetState.value.comparables_sync_url, {
      items: selectedItemsPayload.value,
    });
    assetState.value = {
      ...assetState.value,
      comparables: response.data.comparables || [],
    };
    setFeedback(response.data.message, 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Sinkron pembanding gagal.', 'error');
  } finally {
    busySync.value = false;
  }
};

const toggleSelectedId = (id, checked) => {
  if (checked) {
    selectedIds.value = Array.from(new Set([...selectedIds.value, id]));
    return;
  }
  selectedIds.value = selectedIds.value.filter((itemId) => itemId !== id);
};

const updateSavedComparableSelection = async (comparable, checked) => {
  if (!comparable?.update_url) {
    return;
  }

  const comparableId = String(comparable.id);
  busyComparableUpdates.value = {
    ...busyComparableUpdates.value,
    [comparableId]: true,
  };

  setFeedback('');

  try {
    const response = await axios.post(comparable.update_url, {
      is_selected: checked === true,
      manual_rank: comparable.manual_rank ?? comparable.rank ?? null,
    });

    const updatedComparable = response.data?.comparable;

    if (updatedComparable) {
      assetState.value = {
        ...assetState.value,
        comparables: (assetState.value.comparables || []).map((item) => (
          item.id === comparable.id
            ? { ...item, ...updatedComparable }
            : item
        )),
      };
    }

    setFeedback(response.data?.message || 'Status pembanding diperbarui.', 'success');
  } catch (error) {
    setFeedback(error.response?.data?.message || 'Gagal memperbarui status pembanding.', 'error');
  } finally {
    busyComparableUpdates.value = {
      ...busyComparableUpdates.value,
      [comparableId]: false,
    };
  }
};

const feedbackIcon = computed(() => {
  if (feedbackTone.value === 'error') return AlertCircle;
  if (feedbackTone.value === 'success') return CheckCircle2;
  return Info;
});

const feedbackClasses = computed(() => {
  if (feedbackTone.value === 'error') return 'border-red-200 bg-red-50 text-red-900';
  if (feedbackTone.value === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
  return 'border-slate-200 bg-slate-50 text-slate-900';
});

const selectOptions = (options = []) =>
  options.filter((option) => option?.value !== null && option?.value !== undefined && option?.value !== '');

// File type icon helper
const getFileIcon = (type) => {
  if (!type) return FileText;
  const t = type.toLowerCase();
  if (t.includes('image') || t.includes('foto') || t.includes('photo')) return FileImage;
  return FileText;
};

const getFileTypeMeta = (file) => {
  const type = String(file?.type || '').toLowerCase();

  if (type === 'doc_pbb') {
    return {
      label: 'PBB',
      badgeClass: 'border-amber-200 bg-amber-50 text-amber-700',
    };
  }

  if (type === 'doc_imb') {
    return {
      label: 'IMB / PBG',
      badgeClass: 'border-sky-200 bg-sky-50 text-sky-700',
    };
  }

  if (type === 'doc_certs') {
    return {
      label: 'Sertifikat',
      badgeClass: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    };
  }

  if (type === 'photo_access_road') {
    return {
      label: 'Foto Akses Jalan',
      badgeClass: 'border-orange-200 bg-orange-50 text-orange-700',
    };
  }

  if (type === 'photo_front') {
    return {
      label: 'Foto Depan',
      badgeClass: 'border-blue-200 bg-blue-50 text-blue-700',
    };
  }

  if (type === 'photo_interior') {
    return {
      label: 'Foto Dalam',
      badgeClass: 'border-teal-200 bg-teal-50 text-teal-700',
    };
  }

  return {
    label: file?.type || 'Lainnya',
    badgeClass: 'border-slate-200 bg-slate-50 text-slate-700',
  };
};

// Format file size
const formatFileSize = (bytes) => {
  if (!bytes) return '0 B';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const previewSource = (file) => {
  if (!file?.url) return null;

  if (isPdfFile(file)) {
    return `${file.url}#toolbar=0&navpanes=0&scrollbar=0&view=FitH`;
  }

  return file.url;
};

const resetImageZoom = () => {
  imageZoom.value = 1;
};

const clampZoom = (zoom) => Math.min(3, Math.max(0.5, Number(zoom) || 1));

const zoomInImage = () => {
  imageZoom.value = clampZoom(imageZoom.value + 0.25);
};

const zoomOutImage = () => {
  imageZoom.value = clampZoom(imageZoom.value - 0.25);
};

const openPreview = (file) => {
  if (!file?.url) return;
  resetImageZoom();
  previewFile.value = file;
};

const closePreview = () => {
  resetImageZoom();
  previewFile.value = null;
};

const showPrevImage = () => {
  if (!hasPrevImage.value) {
    return;
  }

  resetImageZoom();
  previewFile.value = imageFiles.value[previewImageIndex.value - 1] ?? previewFile.value;
};

const showNextImage = () => {
  if (!hasNextImage.value) {
    return;
  }

  resetImageZoom();
  previewFile.value = imageFiles.value[previewImageIndex.value + 1] ?? previewFile.value;
};

const selectPreviewImage = (file) => {
  if (!file?.url) {
    return;
  }

  resetImageZoom();
  previewFile.value = file;
};

const handleCardKeydown = (event, file) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    openPreview(file);
  }
};

const handlePreviewKeydown = (event) => {
  if (!previewFile.value || !previewIsImage.value) {
    return;
  }

  if (event.key === 'ArrowLeft') {
    event.preventDefault();
    showPrevImage();
    return;
  }

  if (event.key === 'ArrowRight') {
    event.preventDefault();
    showNextImage();
    return;
  }

  if (event.key === '+' || event.key === '=') {
    event.preventDefault();
    zoomInImage();
    return;
  }

  if (event.key === '-') {
    event.preventDefault();
    zoomOutImage();
    return;
  }

  if (event.key === '0') {
    event.preventDefault();
    resetImageZoom();
  }
};

onMounted(() => {
  window.addEventListener('keydown', handlePreviewKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handlePreviewKeydown);
});
</script>

<template>
  <Head :title="`Aset ${asset.request_number}`" />

  <ReviewerLayout :title="`Aset ${asset.request_number}`">
    <div class="space-y-6">

      <!-- ─── Hero Header ─── -->
      <div class="rounded-2xl border bg-card shadow-sm overflow-hidden">
        <!-- Top accent bar -->
        <div class="h-1.5 w-full bg-gradient-to-r from-primary/80 via-primary to-primary/60" />

        <div class="p-6 md:p-8">
          <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <!-- Left: Identity -->
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 mb-3">
                <Badge variant="secondary" class="text-xs font-mono">{{ assetState.request_number }}</Badge>
                <Badge variant="outline" class="text-xs">{{ assetState.asset_type?.label }}</Badge>
                <StatusBadge :status="assetState.request_status" />
              </div>
              <h1 class="text-2xl font-semibold leading-snug text-foreground md:text-3xl">
                {{ assetState.address }}
              </h1>
            </div>

            <!-- Right: Quick Actions -->
            <div class="flex flex-wrap gap-2 shrink-0">
              <Button v-if="assetState.maps_link" variant="outline" size="sm" as-child>
                <a :href="assetState.maps_link" target="_blank" rel="noopener noreferrer">
                  <MapPinned class="mr-2 h-4 w-4" />
                  Buka Maps
                </a>
              </Button>
              <Button size="sm" as-child>
                <Link :href="assetState.land_adjustment_url || assetState.adjustment_url">
                  <Scale class="mr-2 h-4 w-4" />
                  Adjust Harga Tanah
                </Link>
              </Button>
              <Button v-if="assetState.has_btb && assetState.btb_url" variant="outline" size="sm" as-child>
                <Link :href="assetState.btb_url">
                  <BarChart3 class="mr-2 h-4 w-4" />
                  BTB Bangunan
                </Link>
              </Button>
            </div>
          </div>

          <!-- Stats Row -->
          <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border bg-muted/40 px-4 py-3">
              <p class="text-[11px] font-medium uppercase tracking-widest text-muted-foreground">Luas Tanah</p>
              <p class="mt-1.5 text-base font-semibold text-foreground">{{ formatArea(assetState.land_area) }}</p>
            </div>
            <div class="rounded-xl border bg-muted/40 px-4 py-3">
              <p class="text-[11px] font-medium uppercase tracking-widest text-muted-foreground">Luas Bangunan</p>
              <p class="mt-1.5 text-base font-semibold text-foreground">{{ formatArea(assetState.building_area) }}</p>
            </div>
            <div class="rounded-xl border bg-muted/40 px-4 py-3">
              <p class="text-[11px] font-medium uppercase tracking-widest text-muted-foreground">Range Bawah</p>
              <p class="mt-1.5 text-base font-semibold text-foreground">{{ formatCurrency(assetState.values?.estimated_value_low) }}</p>
            </div>
            <div class="rounded-xl border bg-muted/40 px-4 py-3">
              <p class="text-[11px] font-medium uppercase tracking-widest text-muted-foreground">Range Atas</p>
              <p class="mt-1.5 text-base font-semibold text-foreground">{{ formatCurrency(assetState.values?.estimated_value_high) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ─── Feedback Alert ─── -->
      <Alert v-if="feedback" :class="feedbackClasses">
        <component :is="feedbackIcon" class="h-4 w-4" />
        <AlertTitle>Notifikasi</AlertTitle>
        <AlertDescription>{{ feedback }}</AlertDescription>
      </Alert>

      <!-- ─── Main Tabs ─── -->
      <Tabs default-value="documents" class="space-y-5">
        <TabsList class="h-auto w-full justify-start gap-1 rounded-xl border bg-muted/50 p-1.5 flex-wrap">
          <TabsTrigger value="documents" class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            <Files class="h-4 w-4" />
            Dokumen Aset
            <Badge v-if="fileCount" variant="secondary" class="ml-1 h-5 px-1.5 text-[11px]">{{ fileCount }}</Badge>
          </TabsTrigger>
          <TabsTrigger value="general" class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            <Settings2 class="h-4 w-4" />
            Data Umum
          </TabsTrigger>
          <TabsTrigger value="comparables" class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            <Search class="h-4 w-4" />
            Cari Pembanding
          </TabsTrigger>
          <TabsTrigger value="saved" class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm">
            <BarChart3 class="h-4 w-4" />
            Pembanding Tersimpan
            <Badge v-if="comparablesCount" variant="secondary" class="ml-1 h-5 px-1.5 text-[11px]">{{ comparablesCount }}</Badge>
          </TabsTrigger>
        </TabsList>

        <!-- ── Tab: Dokumen Aset ── -->
        <TabsContent value="documents" class="mt-0">
          <Card>
            <CardHeader class="pb-2">
              <CardTitle>Dokumen Aset</CardTitle>
              <CardDescription>
                Semua file aset ditampilkan sebagai kartu preview. Klik kartu untuk melihat isi file.
              </CardDescription>
            </CardHeader>
            <CardContent class="pt-4">
              <!-- Empty state -->
              <div v-if="!assetState.files?.length" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-14 text-center">
                <Files class="mb-3 h-10 w-10 text-muted-foreground/40" />
                <p class="font-medium text-muted-foreground">Belum ada file aset</p>
                <p class="mt-1 text-sm text-muted-foreground/70">File akan muncul di sini setelah diunggah.</p>
              </div>

              <div v-else class="space-y-8">
                <section class="space-y-3">
                  <div class="flex items-center justify-between gap-3">
                    <div>
                      <h3 class="text-sm font-semibold text-foreground">Dokumen Kelengkapan</h3>
                      <p class="text-xs text-muted-foreground">PDF dan dokumen administratif.</p>
                    </div>
                    <Badge variant="outline">{{ documentFiles.length }} file</Badge>
                  </div>

                  <div v-if="documentFiles.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div
                      v-for="file in documentFiles"
                      :key="file.id"
                      role="button"
                      tabindex="0"
                      class="group overflow-hidden rounded-2xl border bg-card text-left transition hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/30"
                      @click="openPreview(file)"
                      @keydown="handleCardKeydown($event, file)"
                    >
                      <div class="flex items-center justify-between border-b bg-muted/40 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-2">
                          <div class="flex h-7 w-7 items-center justify-center rounded-md bg-red-100 text-red-600">
                            <component :is="getFileIcon(file.type)" class="h-4 w-4" />
                          </div>
                          <p class="truncate text-sm font-semibold text-foreground">{{ file.original_name }}</p>
                        </div>
                        <Eye class="h-4 w-4 text-muted-foreground transition group-hover:text-foreground" />
                      </div>

                      <div class="h-48 overflow-hidden bg-muted/20">
                        <iframe
                          v-if="isPdfFile(file) && file.url"
                          :src="previewSource(file)"
                          class="h-full w-full scale-[1.02] pointer-events-none"
                          title="Preview PDF"
                        />
                        <div v-else class="flex h-full flex-col items-center justify-center gap-3 text-muted-foreground">
                          <component :is="getFileIcon(file.type)" class="h-10 w-10" />
                          <span class="text-xs">Preview tidak tersedia</span>
                        </div>
                      </div>

                      <div class="space-y-2 px-4 py-3">
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                          <span>Tipe</span>
                          <Badge variant="outline" :class="getFileTypeMeta(file).badgeClass" class="text-[11px]">
                            {{ getFileTypeMeta(file).label }}
                          </Badge>
                        </div>
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                          <span>Ukuran</span>
                          <span class="font-medium text-foreground">{{ formatFileSize(file.size) }}</span>
                        </div>
                        <div v-if="file.created_at" class="flex items-center justify-between text-xs text-muted-foreground">
                          <span>Diunggah</span>
                          <span class="font-medium text-foreground">{{ formatDateTime(file.created_at) }}</span>
                        </div>
                        <div class="flex items-center gap-2 pt-2">
                          <Button variant="outline" size="sm" class="flex-1" @click.stop="openPreview(file)">
                            <Eye class="mr-2 h-3.5 w-3.5" />
                            Preview
                          </Button>
                          <Button v-if="file.url" variant="ghost" size="sm" class="flex-1" as-child>
                            <a :href="file.url" download @click.stop>
                              <ExternalLink class="mr-2 h-3.5 w-3.5" />
                              Download
                            </a>
                          </Button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-else class="rounded-xl border border-dashed px-4 py-6 text-sm text-muted-foreground">
                    Belum ada dokumen kelengkapan.
                  </div>
                </section>

                <Separator />

                <section class="space-y-3">
                  <div class="flex items-center justify-between gap-3">
                    <div>
                      <h3 class="text-sm font-semibold text-foreground">Gambar & Foto Aset</h3>
                      <p class="text-xs text-muted-foreground">Dibedakan berdasarkan tipe file foto yang diunggah.</p>
                    </div>
                    <Badge variant="outline">{{ imageFiles.length }} file</Badge>
                  </div>

                  <div v-if="imageFiles.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div
                      v-for="file in imageFiles"
                      :key="file.id"
                      role="button"
                      tabindex="0"
                      class="group overflow-hidden rounded-2xl border bg-card text-left transition hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/30"
                      @click="openPreview(file)"
                      @keydown="handleCardKeydown($event, file)"
                    >
                      <div class="relative h-52 overflow-hidden bg-muted/20">
                        <img
                          v-if="file.url"
                          :src="file.url"
                          :alt="file.original_name"
                          loading="lazy"
                          decoding="async"
                          class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                        />
                        <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                          <FileImage class="h-10 w-10" />
                        </div>

                        <div class="absolute inset-x-0 top-0 flex items-center justify-between bg-gradient-to-b from-black/55 to-transparent px-4 py-3">
                          <Badge variant="secondary" :class="getFileTypeMeta(file).badgeClass">
                            {{ getFileTypeMeta(file).label }}
                          </Badge>
                          <Eye class="h-4 w-4 text-white" />
                        </div>
                      </div>

                      <div class="space-y-2 px-4 py-3">
                        <p class="truncate text-sm font-semibold text-foreground">{{ file.original_name }}</p>
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                          <span>Ukuran</span>
                          <span class="font-medium text-foreground">{{ formatFileSize(file.size) }}</span>
                        </div>
                        <div v-if="file.created_at" class="flex items-center justify-between text-xs text-muted-foreground">
                          <span>Diunggah</span>
                          <span class="font-medium text-foreground">{{ formatDateTime(file.created_at) }}</span>
                        </div>
                        <div class="flex items-center gap-2 pt-2">
                          <Button variant="outline" size="sm" class="flex-1" @click.stop="openPreview(file)">
                            <Eye class="mr-2 h-3.5 w-3.5" />
                            Preview
                          </Button>
                          <Button v-if="file.url" variant="ghost" size="sm" class="flex-1" as-child>
                            <a :href="file.url" download @click.stop>
                              <ExternalLink class="mr-2 h-3.5 w-3.5" />
                              Download
                            </a>
                          </Button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-else class="rounded-xl border border-dashed px-4 py-6 text-sm text-muted-foreground">
                    Belum ada gambar atau foto aset.
                  </div>
                </section>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- ── Tab: Data Umum ── -->
        <TabsContent value="general" class="mt-0">
          <Card>
            <CardHeader class="pb-2">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <CardTitle>Data Umum Aset</CardTitle>
                  <CardDescription>Lengkapi field yang dipakai di comparison matrix. Semua perubahan perlu disimpan secara manual.</CardDescription>
                </div>
                <Button :disabled="busyGeneral" @click="saveGeneralData" class="shrink-0">
                  <Save class="mr-2 h-4 w-4" />
                  {{ busyGeneral ? 'Menyimpan...' : 'Simpan Data' }}
                </Button>
              </div>
            </CardHeader>
            <CardContent class="pt-6">
              <!-- Section: Informasi Lahan -->
              <div class="mb-8">
                <div class="mb-4 flex items-center gap-2">
                  <div class="h-5 w-1 rounded-full bg-primary" />
                  <h3 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Informasi Lahan</h3>
                </div>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                  <div class="grid gap-2">
                    <Label class="text-sm">Peruntukan</Label>
                    <Select v-model="generalForm.peruntukan">
                      <SelectTrigger class="h-10">
                        <SelectValue placeholder="Pilih peruntukan" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="option in selectOptions(fieldOptions.usageOptions)" :key="option.value" :value="option.value">
                          {{ option.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="grid gap-2">
                    <Label class="text-sm">Dokumen Tanah</Label>
                    <Select v-model="generalForm.title_document">
                      <SelectTrigger class="h-10">
                        <SelectValue placeholder="Pilih dokumen tanah" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="option in selectOptions(fieldOptions.titleDocumentOptions)" :key="option.value" :value="option.value">
                          {{ option.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="grid gap-2">
                    <Label class="text-sm">Bentuk Tanah</Label>
                    <Select v-model="generalForm.land_shape">
                      <SelectTrigger class="h-10">
                        <SelectValue placeholder="Pilih bentuk tanah" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="option in selectOptions(fieldOptions.landShapeOptions)" :key="option.value" :value="option.value">
                          {{ option.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="grid gap-2">
                    <Label class="text-sm">Posisi Tanah</Label>
                    <Select v-model="generalForm.land_position">
                      <SelectTrigger class="h-10">
                        <SelectValue placeholder="Pilih posisi tanah" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="option in selectOptions(fieldOptions.landPositionOptions)" :key="option.value" :value="option.value">
                          {{ option.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="grid gap-2">
                    <Label class="text-sm">Kondisi Tanah</Label>
                    <Select v-model="generalForm.land_condition">
                      <SelectTrigger class="h-10">
                        <SelectValue placeholder="Pilih kondisi tanah" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="option in selectOptions(fieldOptions.landConditionOptions)" :key="option.value" :value="option.value">
                          {{ option.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="grid gap-2">
                    <Label class="text-sm">Topografi</Label>
                    <Select v-model="generalForm.topography">
                      <SelectTrigger class="h-10">
                        <SelectValue placeholder="Pilih topografi" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="option in selectOptions(fieldOptions.topographyOptions)" :key="option.value" :value="option.value">
                          {{ option.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </div>

              <Separator class="mb-8" />

              <!-- Section: Dimensi & Akses -->
              <div>
                <div class="mb-4 flex items-center gap-2">
                  <div class="h-5 w-1 rounded-full bg-primary" />
                  <h3 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Dimensi & Akses</h3>
                </div>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                  <div class="grid gap-2">
                    <Label class="text-sm">Lebar Muka <span class="text-muted-foreground">(meter)</span></Label>
                    <Input v-model="generalForm.frontage_width" type="number" min="0" step="0.01" class="h-10" placeholder="0.00" />
                  </div>
                  <div class="grid gap-2">
                    <Label class="text-sm">Lebar Akses Jalan <span class="text-muted-foreground">(meter)</span></Label>
                    <Input v-model="generalForm.access_road_width" type="number" min="0" step="0.01" class="h-10" placeholder="0.00" />
                  </div>
                  <div class="grid gap-2">
                    <Label class="text-sm">Tahun Bangun</Label>
                    <Input v-model="generalForm.build_year" type="number" min="1900" :max="new Date().getFullYear()" class="h-10" placeholder="YYYY" />
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- ── Tab: Cari Pembanding ── -->
        <TabsContent value="comparables" class="mt-0">
          <Card>
            <CardHeader class="pb-2">
              <CardTitle>Cari & Pilih Pembanding</CardTitle>
              <CardDescription>
                Tentukan radius dan limit pencarian, lalu pilih data pembanding yang paling relevan. Simpan pilihan untuk digunakan di penyesuaian harga tanah.
              </CardDescription>
            </CardHeader>
            <CardContent class="pt-6">
              <!-- Search Controls -->
              <div class="mb-6 rounded-xl border bg-muted/30 p-5">
                <h3 class="mb-4 text-sm font-semibold text-foreground">Parameter Pencarian</h3>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                  <div class="grid gap-2 flex-1">
                    <Label class="text-sm">Range Jarak <span class="text-muted-foreground">(km)</span></Label>
                    <Input v-model="searchForm.range_km" type="number" min="0.1" max="100" step="0.1" class="h-10" />
                  </div>
                  <div class="grid gap-2 flex-1">
                    <Label class="text-sm">Jumlah Hasil (Limit)</Label>
                    <Input v-model="searchForm.limit" type="number" min="1" max="200" class="h-10" />
                  </div>
                  <Button :disabled="busySearch" @click="searchComparables" class="h-10 shrink-0">
                    <Search class="mr-2 h-4 w-4" />
                    {{ busySearch ? 'Mencari...' : 'Cari Pembanding' }}
                  </Button>
                  <Button
                    :disabled="busySync || selectedItemsPayload.length === 0"
                    @click="syncComparables"
                    variant="default"
                    class="h-10 shrink-0"
                  >
                    <Save class="mr-2 h-4 w-4" />
                    Simpan {{ selectedIds.length > 0 ? `(${selectedIds.length})` : '' }}
                  </Button>
                </div>
              </div>

              <!-- Results Header -->
              <div v-if="searchResults.length" class="mb-3 flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                  Menampilkan <span class="font-semibold text-foreground">{{ searchResults.length }}</span> hasil •
                  <span class="font-semibold text-foreground">{{ selectedIds.length }}</span> dipilih
                </p>
              </div>

              <!-- Results List -->
              <div class="space-y-3">
                <div
                  v-for="item in searchResults"
                  :key="item.id"
                  :class="[
                    'rounded-xl border p-4 transition-colors',
                    selectedIds.includes(item.id) ? 'border-primary/40 bg-primary/5' : 'bg-card hover:bg-muted/30',
                  ]"
                >
                  <div class="flex items-start gap-4">
                    <Checkbox
                      :model-value="selectedIds.includes(item.id)"
                      @update:modelValue="(checked) => toggleSelectedId(item.id, checked)"
                      class="mt-0.5"
                    />
                    <div class="flex-1 min-w-0">
                      <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                          <p class="font-semibold text-foreground">{{ item.address }}</p>
                          <p class="mt-0.5 text-xs text-muted-foreground">
                            ID: <span class="font-mono">{{ item.id }}</span>
                            <span v-if="item.peruntukan"> • {{ item.peruntukan }}</span>
                          </p>
                        </div>
                        <Badge
                          :variant="item.score >= 80 ? 'default' : 'secondary'"
                          class="shrink-0"
                        >
                          Score {{ item.score ?? '-' }}
                        </Badge>
                      </div>
                      <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs sm:grid-cols-4">
                        <div>
                          <span class="text-muted-foreground">Harga</span>
                          <p class="mt-0.5 font-semibold text-foreground">{{ formatCurrency(item.price) }}</p>
                        </div>
                        <div>
                          <span class="text-muted-foreground">Luas Tanah</span>
                          <p class="mt-0.5 font-semibold text-foreground">{{ formatArea(item.land_area) }}</p>
                        </div>
                        <div>
                          <span class="text-muted-foreground">Luas Bangunan</span>
                          <p class="mt-0.5 font-semibold text-foreground">{{ formatArea(item.building_area) }}</p>
                        </div>
                        <div>
                          <span class="text-muted-foreground">Jarak</span>
                          <p class="mt-0.5 font-semibold text-foreground">{{ formatNumber(item.distance || 0, 0) }} m</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Empty state -->
                <div v-if="!searchResults.length" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-14 text-center">
                  <Search class="mb-3 h-10 w-10 text-muted-foreground/40" />
                  <p class="font-medium text-muted-foreground">Belum ada hasil pencarian</p>
                  <p class="mt-1 text-sm text-muted-foreground/70">Atur parameter di atas lalu klik "Cari Pembanding".</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- ── Tab: Pembanding Tersimpan ── -->
        <TabsContent value="saved" class="mt-0">
          <Card>
            <CardHeader class="pb-2">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <CardTitle>Pembanding Tersimpan</CardTitle>
                  <CardDescription>Data pembanding yang telah dipilih dan digunakan untuk aset ini.</CardDescription>
                </div>
                <Button variant="outline" size="sm" as-child>
                  <Link :href="assetState.land_adjustment_url || assetState.adjustment_url">
                    <Scale class="mr-2 h-4 w-4" />
                    Buka Adjust Harga Tanah
                  </Link>
                </Button>
                <Button v-if="assetState.has_btb && assetState.btb_url" variant="outline" size="sm" as-child>
                  <Link :href="assetState.btb_url">
                    <BarChart3 class="mr-2 h-4 w-4" />
                    Buka BTB Bangunan
                  </Link>
                </Button>
              </div>
            </CardHeader>
            <CardContent class="pt-4">
              <!-- Empty -->
              <div v-if="!assetState.comparables?.length" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-14 text-center">
                <Database class="mb-3 h-10 w-10 text-muted-foreground/40" />
                <p class="font-medium text-muted-foreground">Belum ada pembanding tersimpan</p>
                <p class="mt-1 text-sm text-muted-foreground/70">Pilih pembanding dari tab "Cari Pembanding" lalu simpan.</p>
              </div>

              <!-- Table -->
              <div v-else class="overflow-x-auto rounded-xl border">
                <Table>
                  <TableHeader>
                    <TableRow class="bg-muted/40">
                      <TableHead class="pl-5 font-semibold">Dipakai</TableHead>
                      <TableHead class="pl-5 font-semibold">Ext ID</TableHead>
                      <TableHead class="font-semibold">Peruntukan</TableHead>
                      <TableHead class="font-semibold">Score</TableHead>
                      <TableHead class="font-semibold">Rank</TableHead>
                      <TableHead class="font-semibold">Jarak</TableHead>
                      <TableHead class="font-semibold">Nilai /m²</TableHead>
                      <TableHead class="pr-5 font-semibold">Aksi</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow
                      v-for="comparable in assetState.comparables"
                      :key="comparable.id"
                      class="hover:bg-muted/20"
                    >
                      <TableCell class="pl-5">
                        <div class="flex items-center gap-3">
                          <Checkbox
                            :model-value="Boolean(comparable.is_selected)"
                            :disabled="busyComparableUpdates[String(comparable.id)] === true"
                            @update:modelValue="(checked) => updateSavedComparableSelection(comparable, checked)"
                          />
                          <Badge :variant="comparable.is_selected ? 'default' : 'outline'" class="text-[11px]">
                            {{ comparable.is_selected ? 'Dipakai' : 'Tidak' }}
                          </Badge>
                        </div>
                      </TableCell>
                      <TableCell class="pl-5 font-mono text-sm font-medium">{{ comparable.external_id }}</TableCell>
                      <TableCell>{{ comparable.raw_peruntukan || '-' }}</TableCell>
                      <TableCell>
                        <Badge :variant="comparable.score >= 80 ? 'default' : 'secondary'" class="text-xs">
                          {{ comparable.score ?? '-' }}
                        </Badge>
                      </TableCell>
                      <TableCell class="font-semibold">{{ comparable.manual_rank ?? comparable.rank ?? '-' }}</TableCell>
                      <TableCell class="text-muted-foreground">{{ formatNumber(comparable.distance_meters || 0, 0) }} m</TableCell>
                      <TableCell class="font-semibold text-foreground">{{ formatCurrency(comparable.adjusted_unit_value) }}</TableCell>
                      <TableCell class="pr-5">
                        <div class="flex items-center gap-3">
                          <Button variant="link" size="sm" class="h-auto px-0 text-xs" as-child>
                            <Link :href="comparable.detail_url">Detail</Link>
                          </Button>
                          <span class="text-muted-foreground/40">|</span>
                          <Button variant="link" size="sm" class="h-auto px-0 text-xs" as-child>
                            <Link :href="comparable.adjustment_url">Adjust Harga Tanah</Link>
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

    </div>

    <Dialog :open="Boolean(previewFile)" @update:open="(open) => { if (!open) closePreview() }">
      <DialogContent class="max-h-[92vh] overflow-hidden p-0 sm:max-w-5xl">
        <DialogHeader class="border-b px-6 py-4">
          <DialogTitle class="truncate text-left">{{ previewFile?.original_name || 'Preview File' }}</DialogTitle>
          <DialogDescription class="flex flex-wrap items-center gap-2 text-left">
            <span>{{ previewFile ? getFileTypeMeta(previewFile).label : 'Lainnya' }}</span>
            <span>•</span>
            <span>{{ formatFileSize(previewFile?.size || 0) }}</span>
            <template v-if="previewFile?.created_at">
              <span>•</span>
              <span>{{ formatDateTime(previewFile.created_at) }}</span>
            </template>
          </DialogDescription>
        </DialogHeader>

        <div class="flex max-h-[calc(92vh-88px)] flex-col bg-muted/10">
          <div class="flex-1 overflow-auto p-4">
            <div
              class="flex min-h-[65vh] items-center justify-center overflow-hidden rounded-xl border bg-background"
              :class="previewIsImage ? 'relative' : ''"
            >
              <template v-if="previewFile?.url && previewIsImage">
                <Button
                  variant="secondary"
                  size="icon"
                  class="absolute left-4 top-1/2 z-10 -translate-y-1/2 shadow-sm"
                  :disabled="!hasPrevImage"
                  @click="showPrevImage"
                >
                  <ChevronLeft class="h-5 w-5" />
                </Button>

                <div class="absolute right-4 top-4 z-10 flex items-center gap-2">
                  <Button
                    variant="secondary"
                    size="icon"
                    class="shadow-sm"
                    :disabled="imageZoom <= 0.5"
                    @click="zoomOutImage"
                  >
                    <ZoomOut class="h-4 w-4" />
                  </Button>
                  <div class="rounded-full bg-black/65 px-3 py-1 text-xs font-medium text-white">
                    {{ Math.round(imageZoom * 100) }}%
                  </div>
                  <Button
                    variant="secondary"
                    size="icon"
                    class="shadow-sm"
                    :disabled="imageZoom >= 3"
                    @click="zoomInImage"
                  >
                    <ZoomIn class="h-4 w-4" />
                  </Button>
                  <Button
                    variant="secondary"
                    size="icon"
                    class="shadow-sm"
                    :disabled="imageZoom === 1"
                    @click="resetImageZoom"
                  >
                    <RotateCcw class="h-4 w-4" />
                  </Button>
                </div>

                <div class="flex h-[70vh] w-full items-center justify-center overflow-auto p-4">
                  <img
                    :src="previewFile.url"
                    :alt="previewFile.original_name"
                    class="max-h-none max-w-none object-contain transition-transform duration-200 ease-out"
                    :style="{
                      transform: `scale(${imageZoom})`,
                      transformOrigin: 'center center',
                    }"
                  />
                </div>

                <Button
                  variant="secondary"
                  size="icon"
                  class="absolute right-4 top-1/2 z-10 -translate-y-1/2 shadow-sm"
                  :disabled="!hasNextImage"
                  @click="showNextImage"
                >
                  <ChevronRight class="h-5 w-5" />
                </Button>

                <div class="absolute bottom-4 left-1/2 z-10 -translate-x-1/2 rounded-full bg-black/65 px-3 py-1 text-xs text-white">
                  {{ previewImageIndex + 1 }} / {{ imageFiles.length }}
                </div>
              </template>

              <div v-else-if="previewFile?.url" class="h-[70vh] w-full overflow-auto">
                <ReviewerFilePreview
                  :url="previewFile.url"
                  :name="previewFile.original_name"
                />
              </div>

              <div v-else class="flex flex-col items-center gap-3 px-6 text-center text-muted-foreground">
                <FileText class="h-10 w-10" />
                <p class="text-sm">Preview file tidak tersedia untuk tipe ini.</p>
              </div>
            </div>

            <div
              v-if="previewIsImage && imageFiles.length > 1"
              class="mt-4 space-y-4"
            >
              <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span>Shortcut:</span>
                <Badge variant="outline">← / → pindah gambar</Badge>
                <Badge variant="outline">+ / - zoom</Badge>
                <Badge variant="outline">0 reset</Badge>
              </div>

              <div
                v-for="group in groupedImageFiles"
                :key="`preview-group-${group.label}`"
                class="space-y-2"
              >
                <div class="flex items-center justify-between gap-3">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                    {{ group.label }}
                  </p>
                  <Badge variant="secondary" class="text-[11px]">
                    {{ group.files.length }} gambar
                  </Badge>
                </div>

                <div class="flex gap-3 overflow-x-auto pb-1">
                  <button
                    v-for="file in group.files"
                    :key="`preview-thumb-${file.id}`"
                    type="button"
                    class="relative h-20 w-28 shrink-0 overflow-hidden rounded-xl border bg-background transition"
                    :class="file.id === previewFile?.id ? 'border-primary ring-2 ring-primary/20' : 'border-border/60'"
                    @click="selectPreviewImage(file)"
                  >
                    <img
                      :src="file.url"
                      :alt="file.original_name"
                      loading="lazy"
                      decoding="async"
                      class="h-full w-full object-cover"
                    />
                    <div class="absolute inset-x-0 bottom-0 truncate bg-black/55 px-2 py-1 text-[11px] text-white">
                      {{ file.original_name }}
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 border-t bg-background px-6 py-4">
            <Button v-if="previewFile?.url" variant="ghost" as-child>
              <a :href="previewFile.url" download>
                <ExternalLink class="mr-2 h-4 w-4" />
                Download
              </a>
            </Button>
            <Button v-if="previewFile?.url" variant="outline" as-child>
              <a :href="previewFile.url" target="_blank" rel="noopener noreferrer">
                <ExternalLink class="mr-2 h-4 w-4" />
                Buka di Tab Baru
              </a>
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </ReviewerLayout>
</template>
