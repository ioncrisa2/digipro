<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  record: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  editUrl: { type: String, default: null },
  destroyUrl: { type: String, default: null },
});
</script>

<template>
  <Head title="Admin - Detail User" />

  <AdminLayout title="Detail User">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.name }}</h1>
          <p class="mt-2 text-sm text-slate-600">{{ record.email }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
          <Button v-if="editUrl" variant="outline" as-child><Link :href="editUrl">Edit User</Link></Button>
        </div>
      </section>

      <Card>
        <CardHeader><CardTitle>Profil User</CardTitle></CardHeader>
        <CardContent class="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nama</p>
            <p class="mt-1">{{ record.name }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Email</p>
            <p class="mt-1">{{ record.email }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Status Verifikasi</p>
            <p class="mt-1">{{ record.email_verified_at ? 'Verified' : 'Belum verified' }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(record.email_verified_at) }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Role</p>
            <div class="mt-2 flex flex-wrap gap-2">
              <Badge v-for="roleName in record.role_names" :key="roleName" variant="secondary">{{ roleName }}</Badge>
              <span v-if="!record.role_names.length" class="text-sm text-slate-500">Tanpa role</span>
            </div>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Terdaftar</p>
            <p class="mt-1">{{ formatDateTime(record.created_at) }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Diperbarui</p>
            <p class="mt-1">{{ formatDateTime(record.updated_at) }}</p>
          </div>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
