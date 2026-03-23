<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import AdminLayout from '@/layouts/AdminLayout.vue';

const props = defineProps({
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  guidelineSetOptions: { type: Array, default: () => [] },
  formOptions: { type: Object, default: () => ({ categories: [], sub_categories: [], building_types: [], building_classes: [] }) },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  guideline_item_id: props.record.guideline_item_id ? String(props.record.guideline_item_id) : '',
  year: props.record.year ?? '',
  category: props.record.category ?? '',
  sub_category: props.record.sub_category ?? '',
  building_type: props.record.building_type ?? '',
  building_class: props.record.building_class ?? '',
  storey_min: props.record.storey_min ?? '',
  storey_max: props.record.storey_max ?? '',
  economic_life: props.record.economic_life ?? '',
  _method: isEditMode.value ? 'put' : 'post',
});

const selectedGuideline = computed(() => props.guidelineSetOptions.find((item) => item.value === form.guideline_item_id));

watch(
  () => form.guideline_item_id,
  () => {
    if (selectedGuideline.value?.year) {
      form.year = selectedGuideline.value.year;
    }
  },
);

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit BEL' : 'Admin - Tambah BEL'" />

  <AdminLayout :title="isEditMode ? 'Edit BEL' : 'Tambah BEL'">
    <div class="mx-auto max-w-5xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit BEL' : 'Tambah BEL' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Building Economic Life berdasarkan kategori, jenis bangunan, class, dan rentang lantai.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Data BEL</CardTitle>
            <CardDescription>Isi scope guideline, kategori, jenis bangunan, rentang lantai, dan umur ekonomis.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="bel_guideline_item_id">Guideline Set</Label>
              <Select v-model="form.guideline_item_id">
                <SelectTrigger id="bel_guideline_item_id"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.guideline_item_id" class="text-xs text-rose-600">{{ form.errors.guideline_item_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="bel_year">Tahun</Label>
              <Input id="bel_year" v-model="form.year" type="number" min="2000" max="2100" />
              <p v-if="form.errors.year" class="text-xs text-rose-600">{{ form.errors.year }}</p>
            </div>

            <div class="space-y-2">
              <Label for="bel_category">Kategori</Label>
              <Input id="bel_category" v-model="form.category" list="bel-categories" />
              <datalist id="bel-categories">
                <option v-for="option in formOptions.categories" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.category" class="text-xs text-rose-600">{{ form.errors.category }}</p>
            </div>

            <div class="space-y-2">
              <Label for="bel_sub_category">Sub Kategori</Label>
              <Input id="bel_sub_category" v-model="form.sub_category" list="bel-sub-categories" />
              <datalist id="bel-sub-categories">
                <option v-for="option in formOptions.sub_categories" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.sub_category" class="text-xs text-rose-600">{{ form.errors.sub_category }}</p>
            </div>

            <div class="space-y-2">
              <Label for="bel_building_type">Building Type</Label>
              <Input id="bel_building_type" v-model="form.building_type" list="bel-building-types" />
              <datalist id="bel-building-types">
                <option v-for="option in formOptions.building_types" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.building_type" class="text-xs text-rose-600">{{ form.errors.building_type }}</p>
            </div>

            <div class="space-y-2">
              <Label for="bel_building_class">Building Class</Label>
              <Input id="bel_building_class" v-model="form.building_class" list="bel-building-classes" />
              <datalist id="bel-building-classes">
                <option v-for="option in formOptions.building_classes" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.building_class" class="text-xs text-rose-600">{{ form.errors.building_class }}</p>
            </div>

            <div class="space-y-2">
              <Label for="bel_storey_min">Storey Min</Label>
              <Input id="bel_storey_min" v-model="form.storey_min" type="number" min="0" max="200" />
              <p v-if="form.errors.storey_min" class="text-xs text-rose-600">{{ form.errors.storey_min }}</p>
            </div>

            <div class="space-y-2">
              <Label for="bel_storey_max">Storey Max</Label>
              <Input id="bel_storey_max" v-model="form.storey_max" type="number" min="0" max="200" />
              <p class="text-xs text-slate-500">Biarkan kosong untuk rentang terbuka.</p>
              <p v-if="form.errors.storey_max" class="text-xs text-rose-600">{{ form.errors.storey_max }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="bel_economic_life">Economic Life</Label>
              <Input id="bel_economic_life" v-model="form.economic_life" type="number" min="1" max="200" />
              <p v-if="form.errors.economic_life" class="text-xs text-rose-600">{{ form.errors.economic_life }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah BEL' }}
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
