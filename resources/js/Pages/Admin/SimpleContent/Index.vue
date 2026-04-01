<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import AdminCardList from '@/components/admin/AdminCardList.vue';
import AdminEntityCard from '@/components/admin/AdminEntityCard.vue';
import AdminSortableOrderingPanel from '@/components/admin/AdminSortableOrderingPanel.vue';
import { useAdminConfirmDialog } from '@/composables/useAdminConfirmDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';

const props = defineProps({
  resource: { type: Object, required: true },
  filters: { type: Object, default: () => ({ q: '', status: 'all' }) },
  statusOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, active: 0 }) },
  records: { type: Array, default: () => [] },
  createUrl: { type: String, required: true },
  reorderUrl: { type: String, default: '' },
  heroMedia: { type: Object, default: () => ({ image_url: null }) },
  heroUploadUrl: { type: String, default: '' },
  platformPreviewMedia: { type: Array, default: () => [] },
  links: { type: Array, default: () => [] },
});

const heroForm = useForm({
  image: null,
});

const previewForms = {
  1: useForm({ image: null }),
  2: useForm({ image: null }),
  3: useForm({ image: null }),
};

const { confirmDelete } = useAdminConfirmDialog();

const plainText = (value) => String(value ?? '')
  .replace(/<[^>]*>/g, ' ')
  .replace(/\s+/g, ' ')
  .trim();

const applyFilters = (patch = {}) => {
  const routeNameMap = {
    faqs: 'admin.content.legal.faqs.index',
    features: 'admin.content.legal.features.index',
    testimonials: 'admin.content.legal.testimonials.index',
  };

  router.get(route(routeNameMap[props.resource.key]), { ...props.filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = async (item) => {
  const confirmed = await confirmDelete({
    entityLabel: props.resource.singular ?? props.resource.title.toLowerCase(),
    entityName: item.title || item.question || item.name,
  });

  if (!confirmed) return;
  router.delete(item.destroy_url, { preserveScroll: true });
};

const reorderItems = computed(() => props.records.map((item) => ({
  id: item.id,
  title: item.title || item.question || item.name,
  subtitle: plainText(item.description || item.answer || item.quote),
  is_active: item.is_active,
})));

const submitHeroImage = () => {
  if (!props.heroUploadUrl) return;

  heroForm.post(props.heroUploadUrl, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      heroForm.reset();
    },
  });
};

const submitPlatformPreviewImage = (slot, uploadUrl) => {
  const form = previewForms[slot];
  if (!form || !uploadUrl) return;

  form.post(uploadUrl, {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      form.reset();
    },
  });
};
</script>

<template>
  <Head :title="`Admin - ${resource.title}`" />

  <AdminLayout :title="resource.title">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ resource.title }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child><Link :href="createUrl">{{ resource.create_label }}</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2">
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p></CardContent></Card>
        <Card><CardContent class="p-5"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aktif</p><p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active }}</p></CardContent></Card>
      </section>

      <Card v-if="resource.key === 'features'">
        <CardHeader>
          <CardTitle>Background Hero Landing</CardTitle>
          <CardDescription>Sediakan gambar utama untuk hero landing page. Jika belum diisi, landing akan memakai placeholder.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-6 lg:grid-cols-[1.12fr_0.88fr]">
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
            <img
              v-if="heroMedia.image_url"
              :src="heroMedia.image_url"
              alt="Hero background"
              class="h-64 w-full object-cover"
            />
            <div v-else class="flex h-64 items-center justify-center bg-[linear-gradient(135deg,#0f172a,#334155)] px-6 text-center text-sm text-white/75">
              Belum ada gambar hero. Upload file landscape untuk mengganti placeholder landing.
            </div>
          </div>

          <form class="space-y-4" @submit.prevent="submitHeroImage">
            <div class="space-y-2">
              <Label for="hero_image">Upload Background Hero</Label>
              <Input id="hero_image" type="file" accept="image/*" @input="heroForm.image = $event.target.files?.[0] ?? null" />
              <p class="text-xs text-slate-500">Rekomendasi gambar horizontal resolusi tinggi agar hero full-bleed tetap tajam. Maksimal 20 MB.</p>
              <p v-if="heroForm.errors.image" class="text-xs text-rose-600">{{ heroForm.errors.image }}</p>
            </div>

            <Button type="submit" :disabled="heroForm.processing || !heroForm.image">
              {{ heroForm.processing ? 'Mengunggah...' : 'Simpan Background Hero' }}
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card v-if="resource.key === 'features'">
        <CardHeader>
          <CardTitle>Platform Preview Slides</CardTitle>
          <CardDescription>Ganti gambar untuk tiga slide preview di landing. Narasi slide tetap diatur dari kode, tetapi visualnya bisa Anda ubah dari sini.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-6 xl:grid-cols-3">
          <article
            v-for="item in platformPreviewMedia"
            :key="item.slot"
            class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
          >
            <div class="space-y-1">
              <div class="text-sm font-semibold text-slate-950">{{ item.label }}</div>
              <div class="text-xs text-slate-500">Gunakan gambar landscape agar overlay teks tetap terbaca.</div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
              <img
                v-if="item.image_url"
                :src="item.image_url"
                :alt="item.label"
                class="h-44 w-full object-cover"
              />
              <div v-else class="flex h-44 items-center justify-center bg-[linear-gradient(135deg,#0f172a,#334155)] px-4 text-center text-xs text-white/75">
                Belum ada gambar untuk {{ item.label.toLowerCase() }}.
              </div>
            </div>

            <form class="space-y-3" @submit.prevent="submitPlatformPreviewImage(item.slot, item.upload_url)">
              <div class="space-y-2">
                <Label :for="`platform_preview_${item.slot}`">Upload Gambar</Label>
                <Input
                  :id="`platform_preview_${item.slot}`"
                  type="file"
                  accept="image/*"
                  @input="previewForms[item.slot].image = $event.target.files?.[0] ?? null"
                />
                <p v-if="previewForms[item.slot].errors.image" class="text-xs text-rose-600">{{ previewForms[item.slot].errors.image }}</p>
              </div>

              <Button type="submit" :disabled="previewForms[item.slot].processing || !previewForms[item.slot].image">
                {{ previewForms[item.slot].processing ? 'Mengunggah...' : `Simpan ${item.label}` }}
              </Button>
            </form>
          </article>
        </CardContent>
      </Card>

      <AdminSortableOrderingPanel
        v-if="reorderUrl && records.length > 1"
        :title="`Urutkan ${resource.title}`"
        description="Drag and drop urutan item agar tampilan konten publik lebih mudah diatur dari UI."
        :items="reorderItems"
        :save-url="reorderUrl"
      />

      <Card>
        <CardHeader><CardTitle>Filter</CardTitle><CardDescription>Kelola {{ resource.title.toLowerCase() }} dari admin Vue.</CardDescription></CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-2"><Label for="q">Cari</Label><Input id="q" :model-value="filters.q" placeholder="Cari data" @change="applyFilters({ q: $event.target.value })" /></div>
          <div class="space-y-2">
            <Label for="status">Status</Label>
            <Select :model-value="filters.status" @update:model-value="applyFilters({ status: $event })">
              <SelectTrigger id="status"><SelectValue placeholder="Pilih status" /></SelectTrigger>
              <SelectContent><SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem></SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <AdminCardList :items="records" empty-text="Belum ada data." grid-class="grid gap-4 lg:grid-cols-2">
        <template #item="{ item }">
          <AdminEntityCard
            :title="item.title || item.question || item.name"
            :description="plainText(item.description || item.answer || item.quote)"
          >
            <template #media v-if="resource.key === 'features' && item.image_url">
              <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                <img :src="item.image_url" :alt="item.title" class="h-40 w-full object-cover" />
              </div>
            </template>
            <template #media v-else-if="resource.key === 'testimonials' && item.photo_url">
              <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                <img :src="item.photo_url" :alt="item.name" class="h-52 w-full object-cover" />
              </div>
            </template>

            <template #badges>
              <Badge variant="outline" :class="item.is_active ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-800 border-slate-200'">{{ item.is_active ? 'Aktif' : 'Nonaktif' }}</Badge>
            </template>

            <template #meta>
              <p v-if="item.icon">Icon: {{ item.icon }}</p>
              <p v-if="item.role">{{ item.role }}</p>
              <p>Urutan: {{ item.sort_order }}</p>
            </template>

            <template #footer>
              <Button variant="outline" size="sm" as-child><Link :href="item.edit_url"><Pencil class="h-4 w-4" />Edit</Link></Button>
              <Button variant="destructive" size="sm" @click="destroyRecord(item)"><Trash2 class="h-4 w-4" />Hapus</Button>
            </template>
          </AdminEntityCard>
        </template>
      </AdminCardList>
    </div>
  </AdminLayout>
</template>
