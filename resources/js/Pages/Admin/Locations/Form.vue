<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

const props = defineProps({
  resource: { type: Object, required: true },
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  selectFields: { type: Array, default: () => [] },
  generator: { type: Object, default: null },
  showIdField: { type: Boolean, default: true },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  id: props.record.id ?? '',
  name: props.record.name ?? '',
  province_id: props.record.province_id ?? '',
  regency_id: props.record.regency_id ?? '',
  district_id: props.record.district_id ?? '',
  _method: isEditMode.value ? 'put' : 'post',
});

const generatorError = ref('');
const isGeneratingId = ref(false);
const fieldOptions = ref(
  Object.fromEntries(props.selectFields.map((field) => [field.key, field.options ?? []])),
);
const fieldLoading = ref({});

const resolvePreviewParams = () => {
  if (!props.generator) {
    return null;
  }

  const params = new URLSearchParams({ type: props.generator.type });
  const parentField = props.generator.parent_field;

  if (!parentField) {
    return params;
  }

  const parentValue = form[parentField];

  if (!parentValue) {
    return null;
  }

  params.set(parentField, parentValue);

  return params;
};

const loadGeneratedId = async () => {
  if (!props.generator || isEditMode.value) {
    return;
  }

  const params = resolvePreviewParams();

  if (!params) {
    form.id = '';
    generatorError.value = props.generator.parent_field
      ? 'Pilih data parent terlebih dahulu agar kode bisa digenerate.'
      : '';
    return;
  }

  isGeneratingId.value = true;
  generatorError.value = '';

  try {
    const response = await fetch(`${props.generator.preview_url}?${params.toString()}`, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });

    const payload = await response.json();

    if (!response.ok) {
      form.id = '';
      generatorError.value = payload.message ?? 'Gagal membuat kode lokasi.';
      return;
    }

    form.id = payload.id ?? '';
  } catch (_error) {
    form.id = '';
    generatorError.value = 'Gagal mengambil preview kode lokasi.';
  } finally {
    isGeneratingId.value = false;
  }
};

const loadFieldOptions = async (field) => {
  if (!field.endpoint_type || !field.depends_on || !field.parent_param) {
    return;
  }

  const parentValue = form[field.depends_on];

  if (!parentValue) {
    fieldOptions.value = {
      ...fieldOptions.value,
      [field.key]: [],
    };
    form[field.key] = '';
    return;
  }

  fieldLoading.value = {
    ...fieldLoading.value,
    [field.key]: true,
  };

  try {
    const params = new URLSearchParams({
      type: field.endpoint_type,
      [field.parent_param]: parentValue,
    });

    const response = await fetch(`${route('admin.master-data.locations.options')}?${params.toString()}`, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });

    const payload = await response.json();
    const options = Array.isArray(payload.options) ? payload.options : [];

    fieldOptions.value = {
      ...fieldOptions.value,
      [field.key]: options,
    };

    if (!options.some((option) => option.value === form[field.key])) {
      form[field.key] = '';
    }
  } catch (_error) {
    fieldOptions.value = {
      ...fieldOptions.value,
      [field.key]: [],
    };
    form[field.key] = '';
  } finally {
    fieldLoading.value = {
      ...fieldLoading.value,
      [field.key]: false,
    };
  }
};

watch(
  () => props.generator?.parent_field ? form[props.generator.parent_field] : '__no_parent__',
  () => {
    loadGeneratedId();
  },
  { immediate: true },
);

props.selectFields
  .filter((field) => field.endpoint_type && field.depends_on)
  .forEach((field) => {
    watch(
      () => form[field.depends_on],
      async (value, oldValue) => {
        if (oldValue !== undefined && value !== oldValue) {
          form[field.key] = '';
        }

        await loadFieldOptions(field);
      },
      { immediate: true },
    );
  });

const submit = () => {
  form.post(props.submitUrl, {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head :title="isEditMode ? `Admin - Edit ${resource.singular}` : `Admin - Tambah ${resource.singular}`" />

  <AdminLayout :title="isEditMode ? `Edit ${resource.singular}` : `Tambah ${resource.singular}`">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? `Edit ${resource.singular}` : `Tambah ${resource.singular}` }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">{{ resource.description }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child>
            <Link :href="indexUrl">Kembali ke daftar</Link>
          </Button>


        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>{{ resource.singular }}</CardTitle>
            <CardDescription>Form master data {{ resource.singular.toLowerCase() }}.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <input v-if="!showIdField" v-model="form.id" type="hidden" />

            <div v-if="showIdField" class="space-y-2">
              <Label for="location_id">{{ resource.code_label }}</Label>
              <Input
                id="location_id"
                v-model="form.id"
                readonly
                class="bg-slate-50 text-slate-500"
              />
              <p class="text-xs text-slate-500">
                {{
                  isEditMode
                    ? 'Kode lokasi dibuat tetap agar relasi existing tidak berubah.'
                    : isGeneratingId
                      ? 'Sistem sedang menyiapkan kode lokasi berikutnya.'
                      : 'Kode lokasi digenerate otomatis oleh sistem saat create.'
                }}
              </p>
              <p v-if="generatorError" class="text-xs text-amber-600">{{ generatorError }}</p>
              <p v-if="form.errors.id" class="text-xs text-rose-600">{{ form.errors.id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="location_name">Nama</Label>
              <Input id="location_name" v-model="form.name" />
              <p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div
              v-for="field in selectFields"
              :key="field.key"
              class="space-y-2 md:col-span-2"
            >
              <Label :for="`location_${field.key}`">{{ field.label }}</Label>
              <Select
                :model-value="form[field.key]"
                @update:model-value="form[field.key] = $event"
              >
                <SelectTrigger :id="`location_${field.key}`">
                  <SelectValue :placeholder="field.placeholder" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="option in fieldOptions[field.key] ?? []"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="fieldLoading[field.key]" class="text-xs text-slate-500">Memuat opsi {{ field.label.toLowerCase() }}...</p>
              <p v-if="form.errors[field.key]" class="text-xs text-rose-600">{{ form.errors[field.key] }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child>
            <Link :href="indexUrl">Batal</Link>
          </Button>


          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : resource.create_label }}
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
