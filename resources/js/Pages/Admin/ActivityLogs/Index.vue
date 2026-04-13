<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminDataTable from '@/components/admin/AdminDataTable.vue';
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue';
import { Badge } from '@/components/ui/badge';
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
import { formatDateTime } from '@/utils/reviewer';
import { Activity, ArrowRight, Clock3, Radar, ShieldAlert, Users } from 'lucide-vue-next';

const props = defineProps({
  filters: { type: Object, required: true },
  records: { type: Object, required: true },
  summary: { type: Object, required: true },
  workspaceOptions: { type: Array, default: () => [] },
  methodOptions: { type: Array, default: () => [] },
  eventTypeOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  topActors: { type: Array, default: () => [] },
  indexUrl: { type: String, required: true },
});

const columns = [
  { key: 'actor', label: 'Actor', cellClass: 'min-w-[220px]' },
  { key: 'activity', label: 'Activity', cellClass: 'min-w-[280px]' },
  { key: 'target', label: 'Context', cellClass: 'min-w-[220px]' },
  { key: 'status', label: 'Status', cellClass: 'min-w-[130px]' },
  { key: 'occurred_at', label: 'Occurred', cellClass: 'min-w-[160px]', sortable: true },
  { key: 'actions', label: 'Aksi', cellClass: 'min-w-[120px]' },
];

const form = reactive({
  q: props.filters.q ?? '',
  workspace: props.filters.workspace ?? 'all',
  method: props.filters.method ?? 'all',
  event_type: props.filters.event_type ?? 'all',
  status: props.filters.status ?? 'all',
  date_from: props.filters.date_from ?? '',
  date_to: props.filters.date_to ?? '',
});

const submitFilters = () => {
  router.get(props.indexUrl, {
    q: form.q || undefined,
    workspace: form.workspace === 'all' ? undefined : form.workspace,
    method: form.method === 'all' ? undefined : form.method,
    event_type: form.event_type === 'all' ? undefined : form.event_type,
    status: form.status === 'all' ? undefined : form.status,
    date_from: form.date_from || undefined,
    date_to: form.date_to || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.q = '';
  form.workspace = 'all';
  form.method = 'all';
  form.event_type = 'all';
  form.status = 'all';
  form.date_from = '';
  form.date_to = '';
  submitFilters();
};

const activeFilterCount = computed(() => {
  let count = 0;

  if (form.workspace !== 'all') count += 1;
  if (form.method !== 'all') count += 1;
  if (form.event_type !== 'all') count += 1;
  if (form.status !== 'all') count += 1;
  if (form.date_from) count += 1;
  if (form.date_to) count += 1;

  return count;
});

const statusTone = (tone) => tone || 'bg-slate-100 text-slate-800 border-slate-200';
const methodTone = (method) => {
  switch (method) {
    case 'GET':
      return 'bg-slate-100 text-slate-800 border-slate-200';
    case 'POST':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'PUT':
    case 'PATCH':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'DELETE':
      return 'bg-rose-100 text-rose-900 border-rose-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};
</script>

<template>
  <Head title="Admin - Activity Log" />

  <AdminLayout title="Activity Log">
    <div class="space-y-6">
      <section class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.6fr)]">
        <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white">
          <div class="border-b border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(15,23,42,0.06),_transparent_42%),linear-gradient(135deg,_rgba(248,250,252,1),_rgba(255,255,255,1))] p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Super Admin Monitoring</p>
            <div class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
              <div class="max-w-3xl">
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Activity log pengguna</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                  Log ini menangkap page visit penting dan mutation dari user yang sudah login, lalu menyusunnya sebagai jejak audit
                  yang bisa dipantau oleh super_admin.
                </p>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 text-sm text-slate-600 shadow-sm">
                <p class="font-medium text-slate-950">Captured terakhir</p>
                <p class="mt-1">{{ formatDateTime(summary.latest_event_at) }}</p>
              </div>
            </div>
          </div>

          <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">24h Events</p>
                  <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ summary.events_24h }}</p>
                </div>
                <Activity class="h-5 w-5 text-slate-500" />
              </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">24h Active Users</p>
                  <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ summary.unique_users_24h }}</p>
                </div>
                <Users class="h-5 w-5 text-slate-500" />
              </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">24h Actions</p>
                  <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ summary.actions_24h }}</p>
                </div>
                <Clock3 class="h-5 w-5 text-slate-500" />
              </div>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-rose-700">7d Failures</p>
                  <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ summary.failures_7d }}</p>
                </div>
                <ShieldAlert class="h-5 w-5 text-rose-600" />
              </div>
            </div>
          </div>
        </div>

        <aside class="overflow-hidden rounded-[28px] border border-slate-200 bg-slate-950 text-slate-50">
          <div class="border-b border-white/10 p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Most Active Now</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight">Top actor 24 jam terakhir</h2>
            <p class="mt-2 text-sm leading-6 text-slate-300">
              Snapshot cepat untuk melihat siapa yang paling banyak menghasilkan event dalam window operasional terbaru.
            </p>
          </div>
          <div class="space-y-3 p-4">
            <div
              v-for="(actor, index) in topActors"
              :key="actor.user_id ?? index"
              class="rounded-2xl border border-white/10 bg-white/5 p-4 transition duration-200 hover:border-white/20 hover:bg-white/10"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Rank {{ index + 1 }}</p>
                  <p class="mt-2 truncate text-sm font-medium text-white">{{ actor.name }}</p>
                  <p class="truncate text-xs text-slate-400">{{ actor.email }}</p>
                </div>
                <div class="rounded-full border border-white/15 px-3 py-1 text-sm font-semibold text-white">
                  {{ actor.total_events }}
                </div>
              </div>
            </div>
            <div
              v-if="!topActors.length"
              class="rounded-2xl border border-dashed border-white/15 px-4 py-5 text-sm text-slate-400"
            >
              Belum ada actor yang menghasilkan log dalam 24 jam terakhir.
            </div>
          </div>
        </aside>
      </section>

      <Card class="border-slate-200/80">
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Activity stream</CardTitle>
            <CardDescription>
              Cari user, path, atau route tertentu lalu persempit stream dengan workspace, method, tipe event, dan status.
            </CardDescription>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari user, route, path, atau label activity"
            filter-title="Filter activity log"
            filter-description="Saring stream audit untuk fokus ke workspace, jenis event, method, dan rentang tanggal tertentu."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; submitFilters(); }"
            @apply-filters="submitFilters"
            @reset-filters="resetFilters"
          >
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="activity_workspace_filter">Workspace</Label>
                <Select v-model="form.workspace">
                  <SelectTrigger id="activity_workspace_filter" class="w-full"><SelectValue placeholder="Pilih workspace" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in workspaceOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="activity_method_filter">Method</Label>
                <Select v-model="form.method">
                  <SelectTrigger id="activity_method_filter" class="w-full"><SelectValue placeholder="Pilih method" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in methodOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="activity_type_filter">Event Type</Label>
                <Select v-model="form.event_type">
                  <SelectTrigger id="activity_type_filter" class="w-full"><SelectValue placeholder="Pilih tipe event" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in eventTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="activity_status_filter">Status</Label>
                <Select v-model="form.status">
                  <SelectTrigger id="activity_status_filter" class="w-full"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="activity_date_from">Date From</Label>
                <Input id="activity_date_from" v-model="form.date_from" type="date" />
              </div>
              <div class="space-y-2">
                <Label for="activity_date_to">Date To</Label>
                <Input id="activity_date_to" v-model="form.date_to" type="date" />
              </div>
            </div>
          </AdminTableToolbar>
        </CardHeader>

        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            empty-text="Belum ada activity log yang cocok dengan filter saat ini."
            :default-per-page="25"
          >
            <template #cell-actor="{ row }">
              <div class="space-y-1">
                <p class="font-medium text-slate-950">{{ row.actor_name }}</p>
                <p class="text-xs text-slate-500">{{ row.actor_email }}</p>
              </div>
            </template>

            <template #cell-activity="{ row }">
              <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium text-slate-950">{{ row.action_label }}</p>
                  <Badge variant="outline" :class="methodTone(row.method)">{{ row.method }}</Badge>
                  <Badge variant="outline" class="border-slate-200 bg-slate-50 text-slate-700">{{ row.event_type_label }}</Badge>
                </div>
                <p class="text-xs text-slate-500">{{ row.route_name }}</p>
              </div>
            </template>

            <template #cell-target="{ row }">
              <div class="space-y-1">
                <p class="truncate text-sm text-slate-900">{{ row.path }}</p>
                <p class="text-xs text-slate-500">{{ row.target_summary }}</p>
                <Badge variant="outline" class="border-slate-200 bg-white text-slate-700">{{ row.workspace_label }}</Badge>
              </div>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="statusTone(row.status_tone)">
                {{ row.status_code ?? '-' }}
              </Badge>
            </template>

            <template #cell-occurred_at="{ row }">
              {{ formatDateTime(row.occurred_at) }}
            </template>

            <template #cell-actions="{ row }">
              <Button size="sm" variant="outline" class="gap-2" as-child>
                <Link :href="row.show_url">
                  Detail
                  <ArrowRight class="h-4 w-4" />
                </Link>
              </Button>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
