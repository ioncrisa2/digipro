<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  permissionGroups: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  name: props.record.name ?? '',
  guard_name: props.record.guard_name ?? 'web',
  permissions: props.record.permissions ?? [],
  _method: isEditMode.value ? 'put' : 'post',
});

const togglePermission = (value, checked) => {
  if (checked) {
    if (!form.permissions.includes(value)) {
      form.permissions = [...form.permissions, value];
    }
    return;
  }

  form.permissions = form.permissions.filter((item) => item !== value);
};

const toggleGroup = (group, checked) => {
  const values = group.permissions.map((item) => item.value);

  if (checked) {
    form.permissions = [...new Set([...form.permissions, ...values])];
    return;
  }

  form.permissions = form.permissions.filter((item) => !values.includes(item));
};

const groupChecked = (group) => group.permissions.length > 0 && group.permissions.every((item) => form.permissions.includes(item.value));

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit Role' : 'Admin - Tambah Role'" />

  <AdminLayout :title="isEditMode ? 'Edit Role' : 'Tambah Role'">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Hak Akses</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit Role' : 'Tambah Role' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Role tetap memakai backend `spatie/permission`, hanya UI-nya dipindah ke Vue.
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
            <CardTitle>Data Role</CardTitle>
            <CardDescription>Nama dan guard dasar role.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="role_name">Nama Role</Label>
              <Input id="role_name" v-model="form.name" />
              <p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="role_guard_name">Guard Name</Label>
              <Input id="role_guard_name" v-model="form.guard_name" />
              <p v-if="form.errors.guard_name" class="text-xs text-rose-600">{{ form.errors.guard_name }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Permission Matrix</CardTitle>
            <CardDescription>Pilih permission yang akan disinkronkan ke role ini.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div v-for="group in permissionGroups" :key="group.key" class="rounded-2xl border p-4">
              <div class="mb-4 flex items-center gap-3">
                <Checkbox :model-value="groupChecked(group)" @update:model-value="toggleGroup(group, $event)" />
                <div>
                  <p class="font-medium text-slate-950">{{ group.title }}</p>
                  <p class="text-xs text-slate-500">{{ group.permissions.length }} permission</p>
                </div>
              </div>
              <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <label
                  v-for="permission in group.permissions"
                  :key="permission.value"
                  class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700"
                >
                  <Checkbox
                    :model-value="form.permissions.includes(permission.value)"
                    @update:model-value="togglePermission(permission.value, $event)"
                  />
                  <div>
                    <p>{{ permission.label }}</p>
                    <p class="text-xs text-slate-500">{{ permission.name }}</p>
                  </div>
                </label>
              </div>
            </div>
            <p v-if="form.errors.permissions" class="text-xs text-rose-600">{{ form.errors.permissions }}</p>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Role' }}
          </Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
