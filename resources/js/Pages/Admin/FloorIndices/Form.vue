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
  buildingClassOptions: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
  legacyPanelUrl: { type: String, default: '/legacy-admin/ref-guidelines/floor-indices' },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  guideline_set_id: props.record.guideline_set_id ? String(props.record.guideline_set_id) : '',
  year: props.record.year ?? '',
  building_class: props.record.building_class ?? 'DEFAULT',
  floor_count: props.record.floor_count ?? '',
  il_value: props.record.il_value ?? '',
  _method: isEditMode.value ? 'put' : 'post',
});

const selectedGuideline = computed(() => props.guidelineSetOptions.find((item) => item.value === form.guideline_set_id));

watch(
  () => form.guideline_set_id,
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
  <Head :title="isEditMode ? 'Admin - Edit Floor Index' : 'Admin - Tambah Floor Index'" />

  <AdminLayout :title="isEditMode ? 'Edit Floor Index' : 'Tambah Floor Index'">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit Floor Index' : 'Tambah Floor Index' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Referensi nilai index lantai berdasarkan class bangunan dan jumlah lantai.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
          <Button variant="outline" as-child><a :href="record.legacy_url || legacyPanelUrl">Legacy</a></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Data Floor Index</CardTitle>
            <CardDescription>Scope guideline, class bangunan, lantai, dan nilai IL.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="floor_guideline_set_id">Guideline Set</Label>
              <Select v-model="form.guideline_set_id">
                <SelectTrigger id="floor_guideline_set_id"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.guideline_set_id" class="text-xs text-rose-600">{{ form.errors.guideline_set_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="floor_year_input">Tahun</Label>
              <Input id="floor_year_input" v-model="form.year" type="number" min="2000" max="2100" />
              <p v-if="form.errors.year" class="text-xs text-rose-600">{{ form.errors.year }}</p>
            </div>

            <div class="space-y-2">
              <Label for="building_class">Building Class</Label>
              <Input id="building_class" v-model="form.building_class" list="floor-building-classes" />
              <datalist id="floor-building-classes">
                <option v-for="option in buildingClassOptions" :key="option.value" :value="option.value" />
              </datalist>
              <p v-if="form.errors.building_class" class="text-xs text-rose-600">{{ form.errors.building_class }}</p>
            </div>

            <div class="space-y-2">
              <Label for="floor_count">Floor Count</Label>
              <Input id="floor_count" v-model="form.floor_count" type="number" min="1" max="200" />
              <p v-if="form.errors.floor_count" class="text-xs text-rose-600">{{ form.errors.floor_count }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="il_value">IL Value</Label>
              <Input id="il_value" v-model="form.il_value" type="number" min="0" step="0.0001" />
              <p class="text-xs text-slate-500">Gunakan empat digit desimal bila perlu, misalnya `1.0000`.</p>
              <p v-if="form.errors.il_value" class="text-xs text-rose-600">{{ form.errors.il_value }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Floor Index' }}
          </Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
