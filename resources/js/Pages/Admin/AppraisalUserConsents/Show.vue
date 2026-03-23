<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  record: { type: Object, required: true },
  indexUrl: { type: String, required: true },
  links: { type: Array, default: () => [] },
  legacyPanelUrl: { type: String, default: '/legacy-admin' },
});
</script>

<template>
  <Head title="Admin - Detail Persetujuan Pengguna" />
  <AdminLayout title="Detail Persetujuan Pengguna">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 9</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.user_name }}</h1></div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
          <Button v-if="record.legacy_url" variant="outline" as-child><a :href="record.legacy_url">Legacy</a></Button>
          <Button v-else variant="outline" as-child><a :href="legacyPanelUrl">Legacy</a></Button>
        </div>
      </section>

      <Card>
        <CardHeader><CardTitle>Detail Persetujuan</CardTitle></CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
          <div><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Pengguna</p><p class="mt-1">{{ record.user_name }}</p><p class="mt-1">{{ record.user_email }}</p></div>
          <div><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dokumen</p><p class="mt-1">{{ record.document_title }}</p><p class="mt-1">{{ record.code }} · {{ record.version }}</p></div>
          <div><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Accepted At</p><p class="mt-1">{{ formatDateTime(record.accepted_at) }}</p></div>
          <div><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">IP</p><p class="mt-1">{{ record.ip || '-' }}</p></div>
          <div class="md:col-span-2"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">User Agent</p><p class="mt-1 break-all">{{ record.user_agent || '-' }}</p></div>
          <div class="md:col-span-2"><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Hash</p><p class="mt-1 break-all">{{ record.hash || '-' }}</p></div>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
