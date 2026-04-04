<script setup>
import { computed, reactive } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminDataTable from '@/components/admin/AdminDataTable.vue'
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue'
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
import UserDashboardLayout from '@/layouts/UserDashboardLayout.vue'

const props = defineProps({
  reports: { type: Array, default: () => [] },
})

const form = reactive({
  q: '',
  status: 'all',
})

const items = computed(() => props.reports || [])

const rows = computed(() => {
  const query = form.q.trim().toLowerCase()

  return items.value.filter((item) => {
    if (form.status !== 'all' && String(item.status_key || '') !== form.status) {
      return false
    }

    if (!query) {
      return true
    }

    return [
      item.request_number,
      item.client,
      item.report_type,
      item.address,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query))
  })
})

const hasActiveFilters = computed(() => Boolean(form.q.trim()) || form.status !== 'all')
const activeFilterCount = computed(() => (form.status !== 'all' ? 1 : 0))

const resetFilters = () => {
  form.q = ''
  form.status = 'all'
}

const statusOptions = computed(() => {
  const seen = new Set()
  const options = [{ value: 'all', label: 'Semua Status' }]

  items.value.forEach((item) => {
    const value = String(item.status_key || '').trim()
    const label = String(item.status || '').trim()

    if (!value || seen.has(value)) return
    seen.add(value)
    options.push({ value, label: label || value })
  })

  return options
})

const truncateText = (value, limit = 72) => {
  const normalized = String(value ?? '').trim()

  if (!normalized) {
    return '-'
  }

  if (normalized.length <= limit) {
    return normalized
  }

  return `${normalized.slice(0, limit).trimEnd()}...`
}

const formatDate = (value) => {
  if (!value) return '-'

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(parsed)
}

const statusTone = (statusKey) => {
  const status = String(statusKey || '').toLowerCase()

  if (['completed', 'report_ready'].includes(status)) {
    return 'bg-emerald-100 text-emerald-900 border-emerald-200'
  }

  if (['cancelled'].includes(status)) {
    return 'bg-rose-100 text-rose-900 border-rose-200'
  }

  if (['contract_signed', 'valuation_in_progress', 'valuation_completed', 'preview_ready', 'report_preparation'].includes(status)) {
    return 'bg-amber-100 text-amber-900 border-amber-200'
  }

  return 'bg-slate-100 text-slate-800 border-slate-200'
}

const readinessItems = (item) => [
  { key: 'contract', label: 'Kontrak', ready: Boolean(item.ready_contract) },
  { key: 'invoice', label: 'Invoice', ready: Boolean(item.ready_invoice) },
  { key: 'report', label: 'Laporan', ready: Boolean(item.ready_report) },
  { key: 'legal', label: 'Legal Final', ready: Boolean(item.ready_legal_documents) },
]

const columns = [
  { key: 'request', label: 'Request', cellClass: 'min-w-0 w-auto' },
  { key: 'client', label: 'Client', headerClass: 'hidden xl:table-cell', cellClass: 'hidden xl:table-cell w-[170px]' },
  { key: 'status', label: 'Status', cellClass: 'w-[150px]' },
  { key: 'documents', label: 'Arsip', headerClass: 'hidden lg:table-cell', cellClass: 'hidden lg:table-cell w-[120px]' },
  { key: 'readiness', label: 'Kesiapan', headerClass: 'hidden 2xl:table-cell', cellClass: 'hidden 2xl:table-cell w-[260px]' },
  { key: 'updated_at', label: 'Update', headerClass: 'hidden 2xl:table-cell', cellClass: 'hidden 2xl:table-cell w-[120px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'w-[1%] whitespace-nowrap' },
]
</script>

<template>
  <UserDashboardLayout>
    <template #title>Dokumen</template>

    <div class="space-y-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-semibold text-slate-900">Dokumen</h1>
        <p class="max-w-3xl text-sm text-slate-500">
          Arsip seluruh file permohonan Anda, termasuk upload aset, invoice, kontrak, laporan, dan dokumen legal final.
        </p>
      </div>

      <Card class="overflow-hidden border-slate-200/80 bg-white/90 shadow-sm">
        <CardHeader class="flex flex-col gap-4 space-y-0 border-b border-slate-200/80 bg-slate-50/60 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle class="text-slate-950">Daftar Dokumen</CardTitle>
            <CardDescription>
              Buka setiap folder request untuk melihat semua file yang tersedia.
            </CardDescription>
          </div>

          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari request, client, atau alamat"
            filter-title="Filter dokumen"
            filter-description="Saring folder dokumen berdasarkan status request."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value }"
            @apply-filters="() => {}"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="customer_documents_status">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="customer_documents_status">
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </AdminTableToolbar>
        </CardHeader>

        <CardContent class="p-5">
          <div v-if="!rows.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-6 py-12 text-sm text-slate-500">
            Belum ada dokumen yang cocok dengan filter aktif.
          </div>

          <AdminDataTable
            v-else
            :columns="columns"
            :rows="rows"
            :default-per-page="10"
            empty-text="Belum ada dokumen yang cocok dengan filter aktif."
          >
            <template #cell-request="{ row }">
              <div class="min-w-0 space-y-2">
                <div class="font-medium text-slate-950">{{ row.request_number }}</div>
                <p class="text-sm text-slate-600">{{ truncateText(row.address, 78) }}</p>
              </div>
            </template>

            <template #cell-client="{ row }">
              <div class="space-y-1">
                <div class="text-sm font-medium text-slate-900">{{ row.client }}</div>
                <div class="text-xs text-slate-500">{{ row.report_type }}</div>
              </div>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="statusTone(row.status_key)">
                {{ row.status }}
              </Badge>
            </template>

            <template #cell-documents="{ row }">
              <div class="space-y-1 text-sm text-slate-700">
                <div>Upload {{ row.customer_documents_count }}</div>
                <div>Foto {{ row.customer_photos_count }}</div>
                <div class="font-medium text-slate-900">Total {{ row.total_documents_count }}</div>
              </div>
            </template>

            <template #cell-readiness="{ row }">
              <div class="flex flex-wrap gap-2">
                <Badge
                  v-for="flag in readinessItems(row)"
                  :key="flag.key"
                  variant="outline"
                  :class="flag.ready ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'"
                >
                  {{ flag.label }}
                </Badge>
              </div>
            </template>

            <template #cell-updated_at="{ row }">
              <span class="text-sm text-slate-700">{{ formatDate(row.updated_at) }}</span>
            </template>

            <template #cell-actions="{ row }">
              <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="route('reports.show', row.id)">Lihat</Link>
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </UserDashboardLayout>
</template>
