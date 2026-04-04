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
import { Input } from '@/components/ui/input'
import UserDashboardLayout from '@/layouts/UserDashboardLayout.vue'

const props = defineProps({
  payments: { type: Array, default: () => [] },
})

const form = reactive({
  q: '',
  status: 'all',
  date_from: '',
  date_to: '',
})

const items = computed(() => props.payments || [])

const statusMeta = (status) => {
  const normalized = String(status || '').toLowerCase()

  if (normalized.includes('dibayar')) {
    return {
      value: 'paid',
      label: status,
      className: 'bg-emerald-100 text-emerald-900 border-emerald-200',
    }
  }

  if (normalized.includes('gagal')) {
    return {
      value: 'failed',
      label: status,
      className: 'bg-rose-100 text-rose-900 border-rose-200',
    }
  }

  if (normalized.includes('kedaluwarsa')) {
    return {
      value: 'expired',
      label: status,
      className: 'bg-amber-100 text-amber-900 border-amber-200',
    }
  }

  return {
    value: 'pending',
    label: status,
    className: 'bg-slate-100 text-slate-800 border-slate-200',
  }
}

const rows = computed(() => {
  const query = form.q.trim().toLowerCase()

  return items.value.filter((item) => {
    if (form.status !== 'all' && statusMeta(item.status).value !== form.status) {
      return false
    }

    if (!query) {
      const dueDate = item?.due_date ? new Date(`${item.due_date}T00:00:00`) : null
      const fromDate = form.date_from ? new Date(`${form.date_from}T00:00:00`) : null
      const toDate = form.date_to ? new Date(`${form.date_to}T23:59:59`) : null

      if (fromDate && (!dueDate || Number.isNaN(dueDate.getTime()) || dueDate < fromDate)) {
        return false
      }

      if (toDate && (!dueDate || Number.isNaN(dueDate.getTime()) || dueDate > toDate)) {
        return false
      }

      return true
    }

    const matched = [
      item.invoice_number,
      item.request_number,
      item.client,
      item.amount,
      item.bank,
      item.method,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query))

    if (!matched) {
      return false
    }

    const dueDate = item?.due_date ? new Date(`${item.due_date}T00:00:00`) : null
    const fromDate = form.date_from ? new Date(`${form.date_from}T00:00:00`) : null
    const toDate = form.date_to ? new Date(`${form.date_to}T23:59:59`) : null

    if (fromDate && (!dueDate || Number.isNaN(dueDate.getTime()) || dueDate < fromDate)) {
      return false
    }

    if (toDate && (!dueDate || Number.isNaN(dueDate.getTime()) || dueDate > toDate)) {
      return false
    }

    return true
  })
})

const hasActiveFilters = computed(() => Boolean(form.q.trim()) || form.status !== 'all' || form.date_from || form.date_to)
const activeFilterCount = computed(() => {
  let count = 0

  if (form.status !== 'all') count += 1
  if (form.date_from || form.date_to) count += 1

  return count
})

const statusOptions = [
  { value: 'all', label: 'Semua Status' },
  { value: 'paid', label: 'Dibayar' },
  { value: 'pending', label: 'Menunggu' },
  { value: 'expired', label: 'Kedaluwarsa' },
  { value: 'failed', label: 'Gagal' },
]

const resetFilters = () => {
  form.q = ''
  form.status = 'all'
  form.date_from = ''
  form.date_to = ''
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

const resolveInvoicePdfUrl = (item) => {
  if (item?.invoice_pdf_url) return item.invoice_pdf_url

  try {
    return route('appraisal.invoice.pdf', item?.id)
  } catch (_) {
    return `/permohonan-penilaian/${item?.id}/invoice/pdf`
  }
}

const canDownloadInvoice = (item) => Boolean(item?.is_paid)

const columns = [
  { key: 'invoice', label: 'Invoice', cellClass: 'min-w-0 w-auto' },
  { key: 'request', label: 'Request', headerClass: 'hidden xl:table-cell', cellClass: 'hidden xl:table-cell w-[160px]' },
  { key: 'amount', label: 'Jumlah', cellClass: 'w-[150px]' },
  { key: 'status', label: 'Status', cellClass: 'w-[150px]' },
  { key: 'due_date', label: 'Jatuh Tempo', headerClass: 'hidden 2xl:table-cell', cellClass: 'hidden 2xl:table-cell w-[130px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'w-[1%] whitespace-nowrap' },
]
</script>

<template>
  <UserDashboardLayout>
    <template #title>Pembayaran</template>

    <div class="space-y-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-semibold text-slate-900">Pembayaran</h1>
        <p class="text-sm text-slate-500">
          Pantau status tagihan, lanjutkan pembayaran, dan unduh invoice yang sudah lunas.
        </p>
      </div>

      <Card class="overflow-hidden border-slate-200/80 bg-white/90 shadow-sm">
        <CardHeader class="flex flex-col gap-4 space-y-0 border-b border-slate-200/80 bg-slate-50/60 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle class="text-slate-950">Daftar Tagihan</CardTitle>
            <CardDescription>
              Gunakan tabel ini untuk melihat invoice aktif dan membuka halaman pembayaran terkait.
            </CardDescription>
          </div>

          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari invoice, request, atau client"
            filter-title="Filter pembayaran"
            filter-description="Saring daftar tagihan berdasarkan status pembayaran."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value }"
            @apply-filters="() => {}"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="customer_payment_status">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="customer_payment_status">
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="customer_payment_date_from">Dari Tanggal</Label>
              <Input id="customer_payment_date_from" v-model="form.date_from" type="date" />
            </div>

            <div class="space-y-2">
              <Label for="customer_payment_date_to">Sampai Tanggal</Label>
              <Input id="customer_payment_date_to" v-model="form.date_to" type="date" />
            </div>
          </AdminTableToolbar>
        </CardHeader>

        <CardContent class="p-5">
          <div v-if="!rows.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-6 py-12 text-sm text-slate-500">
            Belum ada pembayaran yang cocok dengan filter aktif.
          </div>

          <AdminDataTable
            v-else
            :columns="columns"
            :rows="rows"
            :default-per-page="10"
            empty-text="Belum ada pembayaran yang cocok dengan filter aktif."
          >
            <template #cell-invoice="{ row }">
              <div class="min-w-0 space-y-2">
                <div class="font-medium text-slate-950">{{ row.invoice_number }}</div>
                <p class="text-sm text-slate-600">{{ truncateText(row.client, 52) }}</p>
              </div>
            </template>

            <template #cell-request="{ row }">
              <div class="space-y-1">
                <div class="text-sm font-medium text-slate-900">{{ row.request_number }}</div>
                <div class="text-xs text-slate-500">{{ row.method || '-' }}</div>
              </div>
            </template>

            <template #cell-amount="{ row }">
              <div class="space-y-1">
                <div class="text-sm font-medium text-slate-900">{{ row.amount }}</div>
                <div class="text-xs text-slate-500">{{ truncateText(row.bank, 24) }}</div>
              </div>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="statusMeta(row.status).className">
                {{ statusMeta(row.status).label }}
              </Badge>
            </template>

            <template #cell-due_date="{ row }">
              <span class="text-sm text-slate-700">{{ formatDate(row.due_date) }}</span>
            </template>

            <template #cell-actions="{ row }">
              <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                <Button v-if="canDownloadInvoice(row)" variant="outline" size="sm" as-child>
                  <a :href="resolveInvoicePdfUrl(row)" target="_blank" rel="noreferrer">Invoice</a>
                </Button>
                <Button variant="outline" size="sm" as-child>
                  <Link :href="route('payments.show', row.id)">Lihat</Link>
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </UserDashboardLayout>
</template>
