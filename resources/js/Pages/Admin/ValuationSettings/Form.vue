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
  keyOptions: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  guideline_set_id: props.record.guideline_set_id ? String(props.record.guideline_set_id) : '',
  year: props.record.year ?? '',
  key: props.record.key ?? '',
  label: props.record.label ?? '',
  value_number: props.record.value_number ?? '',
  value_text: props.record.value_text ?? '',
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

watch(
  () => form.key,
  (value) => {
    const selected = props.keyOptions.find((item) => item.value === value);
    if (selected && (!isEditMode.value || !form.label)) {
      form.label = selected.label;
    }
  },
  { immediate: true },
);

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit Valuation Setting' : 'Admin - Tambah Valuation Setting'" />

  <AdminLayout :title="isEditMode ? 'Edit Valuation Setting' : 'Tambah Valuation Setting'">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit Valuation Setting' : 'Tambah Valuation Setting' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Setting perhitungan yang dipakai engine reviewer dan perhitungan appraisal.
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
            <CardTitle>Data Setting</CardTitle>
            <CardDescription>Scope guideline, key, dan nilai setting.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="valuation_guideline">Guideline Set</Label>
              <Select v-model="form.guideline_set_id">
                <SelectTrigger id="valuation_guideline"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.guideline_set_id" class="text-xs text-rose-600">{{ form.errors.guideline_set_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="valuation_year">Tahun</Label>
              <Input id="valuation_year" v-model="form.year" type="number" min="2000" max="2100" />
              <p v-if="form.errors.year" class="text-xs text-rose-600">{{ form.errors.year }}</p>
            </div>

            <div class="space-y-2">
              <Label for="valuation_key">Key</Label>
              <Select v-model="form.key">
                <SelectTrigger id="valuation_key"><SelectValue placeholder="Pilih key" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in keyOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.key" class="text-xs text-rose-600">{{ form.errors.key }}</p>
            </div>

            <div class="space-y-2">
              <Label for="valuation_label">Label</Label>
              <Input id="valuation_label" v-model="form.label" />
              <p v-if="form.errors.label" class="text-xs text-rose-600">{{ form.errors.label }}</p>
            </div>

            <div class="space-y-2">
              <Label for="valuation_value_number">Nilai Angka</Label>
              <Input id="valuation_value_number" v-model="form.value_number" type="number" min="0" step="0.0001" />
              <p v-if="form.key === 'ppn_percent'" class="text-xs text-slate-500">Isi dalam persen, misalnya `11` atau `12`.</p>
              <p v-if="form.errors.value_number" class="text-xs text-rose-600">{{ form.errors.value_number }}</p>
            </div>

            <div class="space-y-2">
              <Label for="valuation_value_text">Nilai Teks</Label>
              <Input id="valuation_value_text" v-model="form.value_text" />
              <p v-if="form.errors.value_text" class="text-xs text-rose-600">{{ form.errors.value_text }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="valuation_notes">Catatan</Label>
              <Textarea id="valuation_notes" v-model="form.notes" class="min-h-[110px]" />
              <p v-if="form.errors.notes" class="text-xs text-rose-600">{{ form.errors.notes }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Setting' }}
          </Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
