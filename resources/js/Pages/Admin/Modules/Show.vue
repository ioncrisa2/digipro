<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

defineProps({
  module: {
    type: Object,
    required: true,
  },
});

const statusTone = (status) => {
  switch (status) {
    case 'in_progress':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'bridge':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};
</script>

<template>
  <Head :title="`Admin - ${module.title}`" />

  <AdminLayout :title="module.title">
    <div class="space-y-6">
      <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="max-w-3xl">
            <div class="flex flex-wrap items-center gap-2">
              <Badge variant="outline" :class="statusTone(module.status)">{{ module.status_label }}</Badge>
            </div>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ module.title }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">{{ module.description }}</p>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <Card>
          <CardHeader>
            <CardTitle>Surface Area</CardTitle>
            <CardDescription>Daftar resource lama yang sudah digantikan oleh halaman Vue.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div
              v-for="resource in module.legacy_resources"
              :key="resource"
              class="rounded-2xl border bg-slate-50 px-4 py-3 text-sm text-slate-700"
            >
              {{ resource }}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Dependency Backend</CardTitle>
            <CardDescription>Catatan backend dan integrasi yang perlu dijaga untuk modul ini.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div
              v-for="dependency in module.dependencies"
              :key="dependency"
              class="rounded-2xl border px-4 py-3 text-sm leading-6 text-slate-700"
            >
              {{ dependency }}
            </div>
          </CardContent>
        </Card>
      </section>
    </div>
  </AdminLayout>
</template>
