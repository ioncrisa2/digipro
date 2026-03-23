<script setup>
import { computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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
  filters: { type: Object, default: () => ({ guideline_set_id: '', year: '', province_id: '' }) },
  guidelineSetOptions: { type: Array, default: () => [] },
  provinceOptions: { type: Array, default: () => [] },
  items: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
});

const form = useForm({
  guideline_set_id: props.filters.guideline_set_id || '',
  year: props.filters.year || '',
  province_id: props.filters.province_id || '',
  items: props.items.map((item) => ({
    region_code: item.region_code,
    regency_name: item.regency_name,
    ikk_value: item.ikk_value ?? '',
  })),
});

const selectedGuideline = computed(() => props.guidelineSetOptions.find((item) => item.value === form.guideline_set_id));
const canEdit = computed(() => Boolean(form.guideline_set_id && form.year && form.province_id && form.items.length));

const reload = () => {
  router.get(route('admin.ref-guidelines.ikk-by-province.index'), {
    guideline_set_id: form.guideline_set_id || undefined,
    year: form.year || undefined,
    province_id: form.province_id || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const onGuidelineChange = (value) => {
  form.guideline_set_id = value;
  if (selectedGuideline.value?.year) {
    form.year = String(selectedGuideline.value.year);
  }
  reload();
};

const onProvinceChange = (value) => {
  form.province_id = value;
  reload();
};

const onYearChange = (event) => {
  form.year = event.target.value;
  reload();
};

watch(
  () => props.items,
  (items) => {
    form.items = items.map((item) => ({
      region_code: item.region_code,
      regency_name: item.regency_name,
      ikk_value: item.ikk_value ?? '',
    }));
  },
);

const resetIkk = () => {
  form.items = form.items.map((item) => ({ ...item, ikk_value: '' }));
};

const resetProvince = () => {
  form.province_id = '';
  form.items = [];
  reload();
};

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head title="Admin - IKK by Province" />

  <AdminLayout title="IKK by Province">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">IKK by Province</h1>
          <p class="mt-2 text-sm text-slate-600">
            Bulk editor nilai IKK seluruh kabupaten/kota dalam satu provinsi untuk guideline dan tahun tertentu.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Parameter</CardTitle>
            <CardDescription>Pilih guideline set, tahun, dan provinsi untuk memuat daftar kabupaten/kota.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-3">
            <div class="space-y-2">
              <Label for="ikkbp_guideline">Guideline Set</Label>
              <Select :model-value="form.guideline_set_id" @update:model-value="onGuidelineChange">
                <SelectTrigger id="ikkbp_guideline"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.guideline_set_id" class="text-xs text-rose-600">{{ form.errors.guideline_set_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="ikkbp_year">Tahun</Label>
              <Input id="ikkbp_year" :model-value="form.year" type="number" min="2000" max="2100" @change="onYearChange" />
              <p v-if="form.errors.year" class="text-xs text-rose-600">{{ form.errors.year }}</p>
            </div>

            <div class="space-y-2">
              <Label for="ikkbp_province">Provinsi</Label>
              <Select :model-value="form.province_id" @update:model-value="onProvinceChange">
                <SelectTrigger id="ikkbp_province"><SelectValue placeholder="Pilih provinsi" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in provinceOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.province_id" class="text-xs text-rose-600">{{ form.errors.province_id }}</p>
            </div>
          </CardContent>
        </Card>

        <Card v-if="canEdit">
          <CardHeader>
            <CardTitle>Daftar Kab/Kota</CardTitle>
            <CardDescription>Edit nilai IKK per kabupaten/kota lalu simpan sekaligus.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex flex-wrap justify-end gap-2">
              <Button type="button" variant="outline" @click="resetIkk">Reset IKK</Button>
              <Button type="button" variant="outline" @click="resetProvince">Reset Provinsi</Button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
              <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kode</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kab/Kota</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">IKK</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(item, index) in form.items" :key="item.region_code">
                    <td class="px-4 py-3 font-mono text-slate-600">{{ item.region_code }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ item.regency_name }}</td>
                    <td class="px-4 py-3">
                      <Input v-model="form.items[index].ikk_value" type="number" min="0" step="0.0001" />
                      <p v-if="form.errors[`items.${index}.ikk_value`]" class="mt-1 text-xs text-rose-600">
                        {{ form.errors[`items.${index}.ikk_value`] }}
                      </p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>

        <Card v-else>
          <CardContent class="p-6 text-sm text-slate-600">
            Pilih guideline set, tahun, dan provinsi untuk memuat editor `IKK by Province`.
          </CardContent>
        </Card>

        <div v-if="canEdit" class="flex justify-end">
          <Button type="submit" :disabled="form.processing">Simpan</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
