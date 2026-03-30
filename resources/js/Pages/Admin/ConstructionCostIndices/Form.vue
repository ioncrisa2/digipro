<script setup>
import { computed, ref, watch } from 'vue';
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
  provinceOptions: { type: Array, default: () => [] },
  regencyOptions: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
  ikkByProvinceUrl: { type: String, default: '' },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  guideline_set_id: props.record.guideline_set_id ? String(props.record.guideline_set_id) : '',
  year: props.record.year ?? '',
  province_id: props.record.province_id ? String(props.record.province_id) : '',
  region_code: props.record.region_code ? String(props.record.region_code) : '',
  ikk_value: props.record.ikk_value ?? '',
  _method: isEditMode.value ? 'put' : 'post',
});

const regencyOptions = ref(props.regencyOptions ?? []);
const isLoadingRegencies = ref(false);

const selectedGuideline = computed(() => props.guidelineSetOptions.find((item) => item.value === form.guideline_set_id));

watch(
  () => form.guideline_set_id,
  () => {
    if (selectedGuideline.value?.year) {
      form.year = selectedGuideline.value.year;
    }
  },
);

const loadRegencies = async () => {
  if (!form.province_id) {
    regencyOptions.value = [];
    form.region_code = '';
    return;
  }

  isLoadingRegencies.value = true;

  try {
    const params = new URLSearchParams({
      type: 'regencies',
      province_id: form.province_id,
    });

    const response = await fetch(`${route('admin.master-data.locations.options')}?${params.toString()}`, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });

    const payload = await response.json();
    regencyOptions.value = Array.isArray(payload.options) ? payload.options : [];

    if (!regencyOptions.value.some((option) => option.value === form.region_code)) {
      form.region_code = '';
    }
  } catch (_error) {
    regencyOptions.value = [];
    form.region_code = '';
  } finally {
    isLoadingRegencies.value = false;
  }
};

watch(
  () => form.province_id,
  async (value, oldValue) => {
    if (oldValue !== undefined && value !== oldValue) {
      form.region_code = '';
    }

    await loadRegencies();
  },
  { immediate: true },
);

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit IKK' : 'Admin - Tambah IKK'" />

  <AdminLayout :title="isEditMode ? 'Edit IKK' : 'Tambah IKK'">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit IKK' : 'Tambah IKK' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Nilai indeks kemahalan konstruksi per kabupaten/kota untuk guideline appraisal.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button v-if="ikkByProvinceUrl" variant="outline" as-child><Link :href="ikkByProvinceUrl">Input IKK by Provinsi</Link></Button>
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Data IKK</CardTitle>
            <CardDescription>Guideline, wilayah, dan nilai IKK untuk perhitungan biaya konstruksi.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="ikk_guideline_set_id">Guideline Set</Label>
              <Select v-model="form.guideline_set_id">
                <SelectTrigger id="ikk_guideline_set_id" class="w-full"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.guideline_set_id" class="text-xs text-rose-600">{{ form.errors.guideline_set_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="ikk_year_input">Tahun</Label>
              <Input id="ikk_year_input" v-model="form.year" type="number" min="2000" max="2100" />
              <p v-if="form.errors.year" class="text-xs text-rose-600">{{ form.errors.year }}</p>
            </div>

            <div class="space-y-2">
              <Label for="ikk_province_id">Provinsi</Label>
              <Select v-model="form.province_id">
                <SelectTrigger id="ikk_province_id" class="w-full"><SelectValue placeholder="Pilih provinsi" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in provinceOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="ikk_region_code">Kabupaten/Kota</Label>
              <Select v-model="form.region_code">
                <SelectTrigger id="ikk_region_code" class="w-full"><SelectValue placeholder="Pilih kabupaten/kota" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in regencyOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="isLoadingRegencies" class="text-xs text-slate-500">Memuat kabupaten/kota...</p>
              <p v-if="form.errors.region_code" class="text-xs text-rose-600">{{ form.errors.region_code }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="ikk_value_input">IKK Value</Label>
              <Input id="ikk_value_input" v-model="form.ikk_value" type="number" min="0" step="0.0001" />
              <p class="text-xs text-slate-500">Gunakan empat digit desimal bila perlu, misalnya `1.0000` atau `0.9875`.</p>
              <p v-if="form.errors.ikk_value" class="text-xs text-rose-600">{{ form.errors.ikk_value }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah IKK' }}
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
