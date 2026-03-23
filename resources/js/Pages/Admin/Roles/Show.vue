<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  record: { type: Object, required: true },
  canUpdate: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  indexUrl: { type: String, required: true },
  editUrl: { type: String, required: true },
  deleteUrl: { type: String, required: true },
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});

const destroyRole = () => {
  if (!window.confirm(`Hapus role "${props.record.name}"?`)) return;
  router.delete(props.deleteUrl, { preserveScroll: true });
};
</script>

<template>
  <Head title="Admin - Detail Role" />

  <AdminLayout title="Detail Role">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Hak Akses</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.name }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
          <Button v-if="canUpdate" variant="outline" as-child><Link :href="editUrl">Edit Role</Link></Button>
          <Button v-if="canDelete" variant="outline" @click="destroyRole">Hapus</Button>
          <Button variant="outline" as-child><a :href="record.legacy_url || legacyPanelUrl">Legacy</a></Button>
        </div>
      </section>

      <Card>
        <CardHeader><CardTitle>Ringkasan Role</CardTitle></CardHeader>
        <CardContent class="grid gap-4 text-sm text-slate-700 md:grid-cols-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nama</p>
            <p class="mt-1">{{ record.name }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Guard</p>
            <p class="mt-1">{{ record.guard_name }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Permission</p>
            <p class="mt-1">{{ record.permissions_count }}</p>
          </div>
          <div class="md:col-span-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Diperbarui</p>
            <p class="mt-1">{{ formatDateTime(record.updated_at) }}</p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Permission Matrix</CardTitle></CardHeader>
        <CardContent class="space-y-4">
          <div v-for="group in record.permission_groups" :key="group.key" class="rounded-2xl border p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <p class="font-medium text-slate-950">{{ group.title }}</p>
              <Badge variant="outline">{{ group.permissions.length }} permission</Badge>
            </div>
            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="permission in group.permissions"
                :key="permission.name"
                class="rounded-xl border bg-slate-50 px-4 py-3"
              >
                <p class="text-sm font-medium text-slate-900">{{ permission.label }}</p>
                <p class="text-xs text-slate-500">{{ permission.name }}</p>
              </div>
            </div>
          </div>

          <div v-if="!record.permission_groups.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
            Role ini belum memiliki permission.
          </div>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
