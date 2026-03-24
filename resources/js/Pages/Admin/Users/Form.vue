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
  roleOptions: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const isEditMode = computed(() => props.mode === 'edit');

const form = useForm({
  name: props.record.name ?? '',
  email: props.record.email ?? '',
  password: '',
  email_verified_at: props.record.email_verified_at ?? '',
  roles: props.record.roles ?? [],
  _method: isEditMode.value ? 'put' : 'post',
});

const toggleRole = (roleName, checked) => {
  if (checked) {
    if (!form.roles.includes(roleName)) {
      form.roles = [...form.roles, roleName];
    }
    return;
  }

  form.roles = form.roles.filter((item) => item !== roleName);
};

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit User' : 'Admin - Tambah User'" />

  <AdminLayout :title="isEditMode ? 'Edit User' : 'Tambah User'">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit User' : 'Tambah User' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Form user terdaftar dengan role berbasis Spatie Permission.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Data User</CardTitle>
            <CardDescription>Field inti sama seperti resource legacy.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="user_name">Nama</Label>
              <Input id="user_name" v-model="form.name" />
              <p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="user_email">Email</Label>
              <Input id="user_email" v-model="form.email" type="email" />
              <p v-if="form.errors.email" class="text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-2">
              <Label for="user_password">Password</Label>
              <Input id="user_password" v-model="form.password" type="password" />
              <p class="text-xs text-slate-500">
                {{ isEditMode ? 'Kosongkan jika tidak ingin mengubah password.' : 'Wajib diisi minimal 8 karakter.' }}
              </p>
              <p v-if="form.errors.password" class="text-xs text-rose-600">{{ form.errors.password }}</p>
            </div>

            <div class="space-y-2">
              <Label for="user_verified_at">Email Terverifikasi</Label>
              <Input id="user_verified_at" v-model="form.email_verified_at" type="datetime-local" />
              <p v-if="form.errors.email_verified_at" class="text-xs text-rose-600">{{ form.errors.email_verified_at }}</p>
            </div>

            <div class="space-y-3 md:col-span-2">
              <Label>Role</Label>
              <div class="grid gap-3 md:grid-cols-2">
                <label
                  v-for="option in roleOptions"
                  :key="option.value"
                  class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700"
                >
                  <Checkbox
                    :model-value="form.roles.includes(option.value)"
                    @update:model-value="toggleRole(option.value, $event)"
                  />
                  <span>{{ option.label }}</span>
                </label>
              </div>
              <p v-if="form.errors.roles" class="text-xs text-rose-600">{{ form.errors.roles }}</p>
              <p v-if="form.errors['roles.0']" class="text-xs text-rose-600">{{ form.errors['roles.0'] }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah User' }}
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>
