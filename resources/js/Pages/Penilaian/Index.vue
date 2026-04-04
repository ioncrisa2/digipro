<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminDataTable from '@/components/admin/AdminDataTable.vue'
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue'
import AppraisalEmptyState from '@/components/appraisal/AppraisalEmptyState.vue'
import AppraisalHeader from '@/components/appraisal/AppraisalHeader.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import DashboardLayout from '@/layouts/UserDashboardLayout.vue'
import { useAppraisalStatus } from '@/composables/useAppraisalStatus'

const props = defineProps({
  appraisals: { type: Object, required: true },
  statsCards: {
    type: Array,
    default: () => [],
  },
  filters: { type: Object, default: () => ({ q: '', status: 'all', per_page: '10' }) },
})

const { getStatusConfig, statusFilterOptions } = useAppraisalStatus()

const form = reactive({
  q: props.filters.q ?? '',
  status: props.filters.status ?? 'all',
})

const rows = computed(() => props.appraisals?.data ?? [])
const paginationMeta = computed(() => {
  const meta = props.appraisals?.meta

  if (!meta) {
    return null
  }

  return {
    ...meta,
    links: props.appraisals?.links ?? meta.links ?? [],
  }
})

const hasActiveFilters = computed(() => Boolean(form.q.trim()) || form.status !== 'all')
const activeFilterCount = computed(() => (form.status !== 'all' ? 1 : 0))

const goToCreate = () => router.get('/buat-permohonan')

const applyFilters = () => {
  router.get(route('appraisal.list'), {
    q: form.q || undefined,
    status: form.status === 'all' ? undefined : form.status,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const resetFilters = () => {
  form.q = ''
  form.status = 'all'
  applyFilters()
}

const formatDate = (dateString) => {
  if (!dateString) return '-'

  const date = new Date(dateString)

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

const truncateText = (value, limit = 88) => {
  const normalized = String(value ?? '').trim()

  if (!normalized) {
    return '-'
  }

  if (normalized.length <= limit) {
    return normalized
  }

  return `${normalized.slice(0, limit).trimEnd()}...`
}

const columns = [
  { key: 'request', label: 'Request', cellClass: 'min-w-0 w-auto' },
  { key: 'report_type', label: 'Laporan', headerClass: 'hidden xl:table-cell', cellClass: 'hidden xl:table-cell w-[150px]' },
  { key: 'assets_count', label: 'Aset', headerClass: 'hidden lg:table-cell', cellClass: 'hidden lg:table-cell w-[80px]' },
  { key: 'status', label: 'Status', cellClass: 'w-[160px]' },
  { key: 'requested_at', label: 'Diajukan', headerClass: 'hidden 2xl:table-cell', cellClass: 'hidden 2xl:table-cell w-[130px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'w-[1%] whitespace-nowrap' },
]
</script>

<template>
  <Head title="Daftar Permohonan Penilaian" />

  <DashboardLayout>
    <template #title>Daftar Permohonan Penilaian</template>

    <div class="space-y-6">
      <AppraisalHeader :on-create="goToCreate" />

      <Card class="overflow-hidden border-slate-200/80 bg-white/90 shadow-sm">
        <CardHeader class="flex flex-col gap-4 space-y-0 border-b border-slate-200/80 bg-slate-50/60 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle class="text-slate-950">Daftar Request</CardTitle>
            <CardDescription>
              Pantau status permohonan, jumlah aset, dan tindak lanjut dari satu tabel kerja.
            </CardDescription>
          </div>

          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nomor request atau nama pemohon"
            filter-title="Filter permohonan"
            filter-description="Saring daftar berdasarkan status request."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; applyFilters() }"
            @apply-filters="applyFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="customer_request_status">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="customer_request_status">
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in statusFilterOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </AdminTableToolbar>
        </CardHeader>

        <CardContent class="p-5">
          <AppraisalEmptyState
            v-if="rows.length === 0"
            :has-active-filters="hasActiveFilters"
            :on-reset-filters="resetFilters"
            :on-create="goToCreate"
          />

          <AdminDataTable
            v-else
            :columns="columns"
            :rows="rows"
            :meta="paginationMeta"
            empty-text="Tidak ada permohonan yang cocok dengan filter saat ini."
          >
            <template #cell-request="{ row }">
              <div class="min-w-0 space-y-2">
                <Button variant="link" class="h-auto max-w-full px-0 text-left font-medium text-slate-950" as-child>
                  <Link :href="route('appraisal.show', row.id)" class="block max-w-full break-words leading-6">
                    {{ row.request_number }}
                  </Link>
                </Button>
                <p class="text-sm text-slate-600" :title="row.location">
                  {{ truncateText(row.location, 72) }}
                </p>
              </div>
            </template>

            <template #cell-report_type="{ row }">
              <span class="text-sm text-slate-700">{{ row.report_type_label }}</span>
            </template>

            <template #cell-assets_count="{ row }">
              <span class="text-sm font-medium text-slate-900">{{ row.assets_count }}</span>
            </template>

            <template #cell-status="{ row }">
              <Badge
                variant="outline"
                :class="[
                  getStatusConfig(row.status).bgColor,
                  getStatusConfig(row.status).color,
                  getStatusConfig(row.status).borderColor,
                ]"
              >
                {{ row.status_label }}
              </Badge>
            </template>

            <template #cell-requested_at="{ row }">
              <span class="text-sm text-slate-700">{{ formatDate(row.requested_at) }}</span>
            </template>

            <template #cell-actions="{ row }">
              <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="route('appraisal.show', row.id)">Lihat Detail</Link>
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </DashboardLayout>
</template>
