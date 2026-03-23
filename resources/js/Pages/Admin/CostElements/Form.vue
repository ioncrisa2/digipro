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
  base_region: props.record.base_region ?? 'DKI Jakarta',
  group: props.record.group ?? '',
  element_code: props.record.element_code ?? '',
  element_name: props.record.element_name ?? '',
  building_type: props.record.building_type ?? '',
  building_class: props.record.building_class ?? '',
  storey_pattern: props.record.storey_pattern ?? '',
  unit: props.record.unit ?? 'm2',
  unit_cost: props.record.unit_cost ?? '',
  spec_json: props.record.spec_json ?? '',
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
  <Head :title="isEditMode ? 'Admin - Edit Cost Element' : 'Admin - Tambah Cost Element'" />

  <AdminLayout :title="isEditMode ? 'Edit Cost Element' : 'Tambah Cost Element'">
    <div class="mx-auto max-w-5xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Pedoman Referensi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit Cost Element' : 'Tambah Cost Element' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Referensi elemen biaya konstruksi per guideline, jenis bangunan, dan pola lantai.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Data Cost Element</CardTitle>
            <CardDescription>Field inti yang dipakai engine BTB reviewer.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="cost_guideline">Guideline Set</Label>
              <Select v-model="form.guideline_set_id">
                <SelectTrigger id="cost_guideline"><SelectValue placeholder="Pilih guideline set" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in guidelineSetOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.guideline_set_id" class="text-xs text-rose-600">{{ form.errors.guideline_set_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="cost_year">Tahun</Label>
              <Input id="cost_year" v-model="form.year" type="number" min="2000" max="2100" />
              <p v-if="form.errors.year" class="text-xs text-rose-600">{{ form.errors.year }}</p>
            </div>

            <div class="space-y-2">
              <Label for="base_region">Base Region</Label>
              <Input id="base_region" v-model="form.base_region" readonly class="bg-slate-50 text-slate-500" />
              <p class="text-xs text-slate-500">Untuk saat ini base region dikunci ke DKI Jakarta mengikuti resource legacy.</p>
              <p v-if="form.errors.base_region" class="text-xs text-rose-600">{{ form.errors.base_region }}</p>
            </div>

            <div class="space-y-2">
              <Label for="group">Group</Label>
              <Input id="group" v-model="form.group" list="cost-element-groups" />
              <datalist id="cost-element-groups">
                <option v-for="option in formOptions.groups ?? []" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.group" class="text-xs text-rose-600">{{ form.errors.group }}</p>
            </div>

            <div class="space-y-2">
              <Label for="element_code">Element Code</Label>
              <Input id="element_code" v-model="form.element_code" list="cost-element-codes" />
              <datalist id="cost-element-codes">
                <option v-for="option in formOptions.element_codes ?? []" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.element_code" class="text-xs text-rose-600">{{ form.errors.element_code }}</p>
            </div>

            <div class="space-y-2">
              <Label for="element_name">Element Name</Label>
              <Input id="element_name" v-model="form.element_name" list="cost-element-names" />
              <datalist id="cost-element-names">
                <option v-for="option in formOptions.element_names ?? []" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.element_name" class="text-xs text-rose-600">{{ form.errors.element_name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="building_type">Building Type</Label>
              <Input id="building_type" v-model="form.building_type" list="cost-building-types" placeholder="Opsional" />
              <datalist id="cost-building-types">
                <option v-for="option in formOptions.building_types ?? []" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.building_type" class="text-xs text-rose-600">{{ form.errors.building_type }}</p>
            </div>

            <div class="space-y-2">
              <Label for="building_class">Building Class</Label>
              <Input id="building_class" v-model="form.building_class" list="cost-building-classes" placeholder="Opsional" />
              <datalist id="cost-building-classes">
                <option v-for="option in formOptions.building_classes ?? []" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.building_class" class="text-xs text-rose-600">{{ form.errors.building_class }}</p>
            </div>

            <div class="space-y-2">
              <Label for="storey_pattern">Storey Pattern</Label>
              <Input id="storey_pattern" v-model="form.storey_pattern" list="cost-storey-patterns" placeholder="Contoh: 1-2, 3-5, >=6" />
              <datalist id="cost-storey-patterns">
                <option v-for="option in formOptions.storey_patterns ?? []" :key="option" :value="option" />
              </datalist>
              <p v-if="form.errors.storey_pattern" class="text-xs text-rose-600">{{ form.errors.storey_pattern }}</p>
            </div>

            <div class="space-y-2">
              <Label for="unit">Unit</Label>
              <Input id="unit" v-model="form.unit" />
              <p v-if="form.errors.unit" class="text-xs text-rose-600">{{ form.errors.unit }}</p>
            </div>

            <div class="space-y-2">
              <Label for="unit_cost">Unit Cost (IDR)</Label>
              <Input id="unit_cost" v-model="form.unit_cost" type="number" min="0" step="1" />
              <p class="text-xs text-slate-500">Biaya per unit, biasanya per m2.</p>
              <p v-if="form.errors.unit_cost" class="text-xs text-rose-600">{{ form.errors.unit_cost }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="spec_json">Spec JSON</Label>
              <Textarea id="spec_json" v-model="form.spec_json" rows="10" placeholder='{"line_order": 1, "material_spec": "Beton bertulang"}' />
              <p class="text-xs text-slate-500">Kosongkan jika tidak ada metadata tambahan. Format harus JSON valid.</p>
              <p v-if="form.errors.spec_json" class="text-xs text-rose-600">{{ form.errors.spec_json }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Cost Element' }}
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
