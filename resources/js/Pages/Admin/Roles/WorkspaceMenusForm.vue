<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps({
  record: { type: Object, required: true },
  sections: { type: Array, default: () => [] },
  indexUrl: { type: String, required: true },
  roleShowUrl: { type: String, required: true },
  submitUrl: { type: String, required: true },
});

const form = useForm({
  workspace_permissions: props.record.workspace_permissions ?? [],
  _method: 'put',
});

const activeCount = computed(() => form.workspace_permissions.length);

const toggleSection = (permission, checked) => {
  if (checked) {
    if (!form.workspace_permissions.includes(permission)) {
      form.workspace_permissions = [...form.workspace_permissions, permission];
    }
    return;
  }

  form.workspace_permissions = form.workspace_permissions.filter((item) => item !== permission);
};

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head :title="`Admin - Menu Sistem ${record.name}`" />

  <AdminLayout :title="`Menu Sistem ${record.name}`">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Hak Akses</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Atur Menu Sistem {{ record.name }}</h1>
          <p class="mt-2 text-sm text-slate-600">
            Toggle section reviewer, menu bersama, dan admin yang boleh terlihat sekaligus dapat diakses oleh role ini.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar menu</Link></Button>
          <Button variant="outline" as-child><Link :href="roleShowUrl">Detail Role</Link></Button>
        </div>
      </section>

      <Card>
        <CardHeader>
          <CardTitle>Ringkasan Role</CardTitle>
          <CardDescription>Menu sistem ini membungkus permission section-level untuk reviewer dan admin.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Role</p>
            <p class="mt-1 text-sm text-slate-900">{{ record.name }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guard</p>
            <p class="mt-1 text-sm text-slate-900">{{ record.guard_name }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Menu Aktif</p>
            <div class="mt-1">
              <Badge variant="secondary">{{ activeCount }} section</Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Section Sistem</CardTitle>
            <CardDescription>Pilih section yang boleh muncul di sidebar sesuai role ini.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-4 xl:grid-cols-2">
            <label
              v-for="section in sections"
              :key="section.permission"
              class="rounded-2xl border p-4 transition hover:border-slate-300"
            >
              <div class="flex items-start gap-3">
                <Checkbox
                  :model-value="form.workspace_permissions.includes(section.permission)"
                  @update:model-value="toggleSection(section.permission, $event)"
                />
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between gap-3">
                    <p class="font-medium text-slate-950">{{ section.label }}</p>
                    <Badge variant="outline">{{ section.items.length }} menu</Badge>
                  </div>
                  <p class="mt-1 text-xs font-medium uppercase tracking-widest text-slate-400">{{ section.surface }}</p>
                  <p class="mt-1 text-sm text-slate-600">{{ section.description }}</p>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <Badge v-for="item in section.items" :key="`${section.key}-${item}`" variant="secondary">
                      {{ item }}
                    </Badge>
                  </div>
                  <p class="mt-3 text-xs text-slate-400">{{ section.permission }}</p>
                </div>
              </div>
            </label>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">Simpan Menu Sistem</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
