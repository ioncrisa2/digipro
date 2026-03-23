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
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/AdminLayout.vue';

const props = defineProps({
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  guidelineSetOptions: { type: Array, default: () => [] },
  formOptions: { type: Object, default: () => ({}) },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  guideline_set_id: props.record.guideline_set_id ? String(props.record.guideline_set_id) : '',
  year: props.record.year ?? '',
  reference_region: props.record.reference_region ?? 'DKI Jakarta',
  building_type: props.record.building_type ?? '',
  building_class: props.record.building_class ?? '',
  storey_pattern: props.record.storey_pattern ?? '',
  rcn_value: props.record.rcn_value ?? '',
  notes: props.record.notes ?? '',
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
  <Head :title="isEditMode ? 'Admin - Edit MAPPI RCN' : 'Admin - Tambah MAPPI RCN'" />

  <AdminLayout :title="isEditMode ? 'Edit MAPPI RCN' : 'Tambah MAPPI RCN'">
    <div class="mx-auto max-w-5xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit MAPPI RCN' : 'Tambah MAPPI RCN' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Standar Total Biaya Pembangunan Baru MAPPI untuk referensi region DKI Jakarta.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Data MAPPI RCN</CardTitle>
            <CardDescription>Field inti standar RCN yang dipakai engine reviewer.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="mappi_guideline">Guideline Set</Label>
              <Select v-model="form.guideline_set_id">
                <SelectTrigger id="mappi_guideline"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.guideline_set_id" class="text-xs text-rose-600">{{ form.errors.guideline_set_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="mappi_year">Tahun</Label>
              <Input id="mappi_year" v-model="form.year" type="number" min="2000" max="2100" />
              <p v-if="form.errors.year" class="text-xs text-rose-600">{{ form.errors.year }}</p>
            </div>

            <div class="space-y-2">
              <Label for="reference_region">Reference Region</Label>
              <Input id="reference_region" v-model="form.reference_region" readonly class="bg-slate-50 text-slate-500" />
              <p class="text-xs text-slate-500">Untuk saat ini dikunci ke DKI Jakarta mengikuti resource legacy.</p>
              <p v-if="form.errors.reference_region" class="text-xs text-rose-600">{{ form.errors.reference_region }}</p>
            </div>

            <div class="space-y-2">
              <Label for="building_type">Building Type</Label>
              <Input id="building_type" v-model="form.building_type" list="mappi-building-types" />
              <datalist id="mappi-building-types">
                <option v-for="option in formOptions.building_types ?? []" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.building_type" class="text-xs text-rose-600">{{ form.errors.building_type }}</p>
            </div>

            <div class="space-y-2">
              <Label for="building_class">Building Class</Label>
              <Input id="building_class" v-model="form.building_class" list="mappi-building-classes" placeholder="Opsional" />
              <datalist id="mappi-building-classes">
                <option v-for="option in formOptions.building_classes ?? []" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.building_class" class="text-xs text-rose-600">{{ form.errors.building_class }}</p>
            </div>

            <div class="space-y-2">
              <Label for="storey_pattern">Storey Pattern</Label>
              <Input id="storey_pattern" v-model="form.storey_pattern" list="mappi-storey-patterns" placeholder="Contoh: 1-2, 3-5, >=6" />
              <datalist id="mappi-storey-patterns">
                <option v-for="option in formOptions.storey_patterns ?? []" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.storey_pattern" class="text-xs text-rose-600">{{ form.errors.storey_pattern }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="rcn_value">RCN Value</Label>
              <Input id="rcn_value" v-model="form.rcn_value" type="number" min="0" step="1" />
              <p class="text-xs text-slate-500">Nilai final Total Biaya Pembangunan Baru (A + B) untuk region referensi.</p>
              <p v-if="form.errors.rcn_value" class="text-xs text-rose-600">{{ form.errors.rcn_value }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="notes">Notes</Label>
              <Textarea id="notes" v-model="form.notes" rows="5" />
              <p v-if="form.errors.notes" class="text-xs text-rose-600">{{ form.errors.notes }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah MAPPI RCN' }}
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
