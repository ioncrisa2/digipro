<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue'
import AppraisalEmptyState from '@/components/appraisal/AppraisalEmptyState.vue'
import AppraisalHeader from '@/components/appraisal/AppraisalHeader.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
import { ArrowUpRight, Building2, CalendarDays, FileText, MapPin, Plus } from 'lucide-vue-next'

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
const isLoading = ref(false)
const loadError = ref('')

const navigationCallbacks = () => ({
  onStart: () => {
    isLoading.value = true
    loadError.value = ''
  },
  onError: () => {
    loadError.value = 'Daftar permohonan gagal dimuat. Silakan coba kembali.'
  },
  onFinish: () => {
    isLoading.value = false
  },
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
const paginationLinks = computed(() => paginationMeta.value?.links ?? [])
const currentPerPage = computed(() => String(paginationMeta.value?.per_page ?? props.filters.per_page ?? '10'))
const summaryText = computed(() => {
  const from = paginationMeta.value?.from ?? 0
  const to = paginationMeta.value?.to ?? 0
  const total = paginationMeta.value?.total ?? 0

  return `Menampilkan ${from}-${to} dari ${total} permohonan`
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
    ...navigationCallbacks(),
  })
}

const resetFilters = () => {
  form.q = ''
  form.status = 'all'
  applyFilters()
}

const visitPage = (url) => {
  if (!url) return

  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
    ...navigationCallbacks(),
  })
}

const updatePerPage = (value) => {
  if (!value) return

  router.get(route('appraisal.list'), {
    q: form.q || undefined,
    status: form.status === 'all' ? undefined : form.status,
    per_page: value,
    page: 1,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    ...navigationCallbacks(),
  })
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

const perPageOptions = ['10', '25', '50', '100']
</script>

<template>
  <Head title="Daftar Permohonan Penilaian" />

  <DashboardLayout>
    <template #title>Daftar Permohonan Penilaian</template>

    <div class="mx-auto max-w-7xl space-y-6">
      <AppraisalHeader :on-create="goToCreate" :show-action="false" />

      <section
        aria-label="Pencarian dan tindakan permohonan"
        class="flex flex-col gap-3 border-b border-[#e5e7eb] bg-[#f8f9fa] px-4 py-4 md:px-5 lg:flex-row lg:items-center lg:justify-between lg:px-6"
      >
        <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari nomor request atau nama pemohon"
            filter-title="Filter permohonan"
            filter-description="Saring daftar berdasarkan status request."
            :active-filter-count="activeFilterCount"
            align-start
            large-controls
            :disabled="isLoading"
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

        <Button size="lg" class="min-h-11 shrink-0 shadow-sm" :disabled="isLoading" @click="goToCreate">
          <Plus class="size-5" aria-hidden="true" />
          Buat Permohonan
        </Button>
      </section>

          <div v-if="isLoading" class="grid gap-4 md:grid-cols-2 md:gap-5 lg:grid-cols-3 lg:gap-6" aria-label="Memuat daftar permohonan" aria-live="polite">
            <article
              v-for="index in 6"
              :key="index"
              class="min-h-[420px] animate-pulse overflow-hidden rounded-xl border border-[var(--border)] bg-[var(--customer-surface)] motion-reduce:animate-none"
            >
              <div class="aspect-[16/9] bg-slate-200" />
              <div class="space-y-4 p-4">
                <div class="h-3 w-2/5 rounded bg-slate-200" />
                <div class="h-5 w-4/5 rounded bg-slate-200" />
                <div class="h-4 w-3/5 rounded bg-slate-200" />
                <div class="grid grid-cols-2 gap-3 border-t border-[var(--border)] pt-4">
                  <div class="h-4 rounded bg-slate-200" />
                  <div class="h-4 rounded bg-slate-200" />
                </div>
              </div>
            </article>
          </div>

          <div v-else-if="loadError" class="flex min-h-64 flex-col items-center justify-center gap-4 rounded-xl border border-rose-200 bg-rose-50 px-6 py-10 text-center" role="alert">
            <div>
              <h2 class="text-lg font-semibold text-rose-950">Data gagal dimuat</h2>
              <p class="mt-2 text-sm text-rose-800">{{ loadError }}</p>
            </div>
            <Button variant="outline" class="min-h-11 border-rose-300 bg-white text-rose-900 hover:bg-rose-100" @click="applyFilters">
              Coba Lagi
            </Button>
          </div>

          <AppraisalEmptyState
            v-else-if="rows.length === 0"
            :has-active-filters="hasActiveFilters"
            :on-reset-filters="resetFilters"
            :on-create="goToCreate"
          />

          <div v-else class="space-y-5">
            <div class="grid gap-4 md:grid-cols-2 md:gap-5 lg:grid-cols-3 lg:gap-6">
              <article
                v-for="row in rows"
                :key="row.id"
                class="group flex min-h-[420px] min-w-0 flex-col overflow-hidden rounded-xl border border-[var(--border)] bg-[var(--customer-surface)] shadow-[0_1px_3px_rgba(0,0,0,0.1)] transition-[border-color,box-shadow,transform] duration-200 ease-out hover:-translate-y-0.5 hover:border-[var(--customer-brand)]/45 hover:shadow-[0_8px_16px_rgba(0,0,0,0.12)] motion-reduce:transition-none motion-reduce:transform-none"
              >
                <Link
                  :href="route('appraisal.show', row.id)"
                  class="relative block aspect-[16/9] overflow-hidden bg-[var(--customer-surface-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--customer-trust)]"
                  :aria-label="`Buka detail ${row.request_number}`"
                >
                  <img
                    v-if="row.front_photo_url"
                    :src="row.front_photo_url"
                    :alt="`Foto tampak depan ${row.request_number}`"
                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:transform-none"
                    loading="lazy"
                  />
                  <span v-else class="flex h-full w-full flex-col items-center justify-center gap-2 text-xs text-[var(--customer-muted)]">
                    <Building2 class="size-7" aria-hidden="true" />
                    Belum ada foto
                  </span>
                  <span class="absolute inset-0 bg-gradient-to-t from-slate-950/30 via-transparent to-slate-950/10" aria-hidden="true" />

                  <span class="absolute left-3 top-3 inline-flex rounded-full border border-white/60 bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-slate-800 shadow-sm">
                    {{ row.report_type_label }}
                  </span>
                  <Badge
                    variant="outline"
                    :class="[
                      'absolute right-3 top-3 bg-white/95 shadow-sm',
                      getStatusConfig(row.status).bgColor,
                      getStatusConfig(row.status).color,
                      getStatusConfig(row.status).borderColor,
                    ]"
                  >
                    {{ row.status_label }}
                  </Badge>
                </Link>

                <div class="flex flex-1 flex-col p-4">
                  <p class="font-mono text-[11px] font-semibold tracking-wide text-[var(--customer-brand)]">
                    {{ row.request_number }}
                  </p>

                  <div class="mt-3 flex min-w-0 items-start gap-2">
                    <MapPin class="mt-0.5 size-4 shrink-0 text-[var(--customer-brand)]" aria-hidden="true" />
                    <p class="line-clamp-2 min-h-10 text-sm font-semibold leading-5 text-slate-950" :title="row.location">
                      {{ truncateText(row.location, 72) }}
                    </p>
                  </div>

                  <div class="mt-4 grid grid-cols-2 gap-3 border-t border-[var(--border)] pt-3 text-xs text-[var(--customer-muted)]">
                    <div class="flex items-center gap-2">
                      <Building2 class="size-3.5 shrink-0" aria-hidden="true" />
                      <span>{{ row.assets_count }} aset</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <CalendarDays class="size-3.5 shrink-0" aria-hidden="true" />
                      <span>{{ formatDate(row.requested_at) }}</span>
                    </div>
                    <div class="col-span-2 flex items-center gap-2">
                      <FileText class="size-3.5 shrink-0" aria-hidden="true" />
                      <span>Laporan {{ row.report_type_label }}</span>
                    </div>
                  </div>
                </div>

                <div class="border-t border-[var(--border)] bg-[var(--customer-surface-muted)]/35 p-3">
                  <Button variant="ghost" class="h-10 w-full justify-between text-[var(--customer-trust)] hover:bg-[var(--customer-brand-soft)] hover:text-[var(--customer-brand)]" as-child>
                    <Link :href="route('appraisal.show', row.id)">
                      Lihat Detail
                      <ArrowUpRight class="size-4" aria-hidden="true" />
                    </Link>
                  </Button>
                </div>
              </article>
            </div>

            <div class="flex flex-col gap-3 border-t border-[var(--border)] pt-5 md:flex-row md:items-center md:justify-between">
              <p class="text-sm text-[var(--customer-muted)]">{{ summaryText }}</p>

              <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="flex items-center gap-3">
                  <span class="text-sm text-[var(--customer-muted)]">Kartu per halaman</span>
                  <Select :model-value="currentPerPage" @update:model-value="updatePerPage">
                    <SelectTrigger class="h-10 w-[92px]">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="option in perPageOptions" :key="option" :value="option">
                        {{ option }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div v-if="(paginationMeta?.last_page ?? 1) > 1" class="flex flex-wrap gap-2">
                  <Button
                    v-for="link in paginationLinks"
                    :key="`${link.label}-${link.url}`"
                    type="button"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    :disabled="!link.url"
                    :aria-current="link.active ? 'page' : undefined"
                    @click="visitPage(link.url)"
                  >
                    <span v-html="link.label" />
                  </Button>
                </div>
              </div>
            </div>
          </div>
    </div>
  </DashboardLayout>
</template>
