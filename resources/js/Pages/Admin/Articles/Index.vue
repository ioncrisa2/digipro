<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Eye, Pencil, Trash2 } from 'lucide-vue-next'
import AdminDataTable from '@/components/admin/AdminDataTable.vue'
import AdminTableToolbar from '@/components/admin/AdminTableToolbar.vue'
import { useAdminConfirmDialog } from '@/composables/useAdminConfirmDialog'
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
import AdminLayout from '@/layouts/AdminLayout.vue'
import { formatDateTime } from '@/utils/reviewer'

const props = defineProps({
  filters: { type: Object, default: () => ({ q: '', status: 'all', category: 'all', per_page: '10' }) },
  statusOptions: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, published: 0, scheduled: 0, draft: 0, categories: 0 }) },
  records: { type: Object, required: true },
  createUrl: { type: String, required: true },
  categoriesUrl: { type: String, required: true },
  tagsUrl: { type: String, required: true },
})

const { confirmDelete } = useAdminConfirmDialog()

const form = reactive({
  q: props.filters.q ?? '',
  status: props.filters.status ?? 'all',
  category: props.filters.category ?? 'all',
})

const applyFilters = () => {
  router.get(route('admin.content.articles.index'), {
    q: form.q || undefined,
    status: form.status === 'all' ? undefined : form.status,
    category: form.category === 'all' ? undefined : form.category,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const resetFilters = () => {
  form.q = ''
  form.status = 'all'
  form.category = 'all'
  applyFilters()
}

const activeFilterCount = computed(() => {
  let count = 0

  if (form.status !== 'all') count += 1
  if (form.category !== 'all') count += 1

  return count
})

const destroyRecord = async (item) => {
  const confirmed = await confirmDelete({
    entityLabel: 'artikel',
    entityName: item.title,
  })

  if (!confirmed) {
    return
  }

  router.delete(item.destroy_url, {
    preserveScroll: true,
  })
}

const statusTone = (value) => {
  switch (value) {
    case 'published':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200'
    case 'scheduled':
      return 'bg-amber-100 text-amber-900 border-amber-200'
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200'
  }
}

const truncateTitle = (value, limit = 128) => {
  const normalized = String(value ?? '').trim()

  if (normalized.length <= limit) {
    return normalized
  }

  return `${normalized.slice(0, limit).trimEnd()}.....`
}

const summaryCards = [
  { key: 'total', label: 'Total Artikel' },
  { key: 'published', label: 'Published' },
  { key: 'scheduled', label: 'Scheduled' },
  { key: 'draft', label: 'Draft' },
]

const columns = [
  { key: 'title', label: 'Artikel', cellClass: 'min-w-0 w-auto' },
  { key: 'status', label: 'Status', cellClass: 'w-[120px]' },
  { key: 'category', label: 'Kategori', headerClass: 'hidden xl:table-cell', cellClass: 'hidden xl:table-cell w-[130px]' },
  { key: 'published_at', label: 'Publikasi', headerClass: 'hidden 2xl:table-cell', cellClass: 'hidden 2xl:table-cell w-[170px]' },
  { key: 'views', label: 'Views', headerClass: 'hidden lg:table-cell', cellClass: 'hidden lg:table-cell w-[90px]' },
  { key: 'tags', label: 'Tag', headerClass: 'hidden 2xl:table-cell', cellClass: 'hidden 2xl:table-cell w-[180px]' },
  { key: 'actions', label: 'Aksi', cellClass: 'w-[1%] whitespace-nowrap' },
]
</script>

<template>
  <Head title="Admin - Artikel" />

  <AdminLayout title="Artikel">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">CMS Artikel</h1>
          <p class="mt-2 text-sm text-slate-600">
            Workspace editorial untuk menulis, menjadwalkan, dan mempublikasikan artikel DigiPro.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="categoriesUrl">Kategori</Link></Button>
          <Button variant="outline" as-child><Link :href="tagsUrl">Tag</Link></Button>
          <Button as-child><Link :href="createUrl">Tulis Artikel</Link></Button>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        <Card v-for="card in summaryCards" :key="card.key">
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ card.label }}</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary[card.key] ?? 0 }}</p>
          </CardContent>
        </Card>
      </section>

      <Card>
        <CardHeader class="flex flex-col gap-4 space-y-0 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <CardTitle>Daftar Artikel</CardTitle>
            <CardDescription>Kelola draft, jadwal tayang, dan publikasi dari satu tabel kerja.</CardDescription>
          </div>
          <AdminTableToolbar
            :search-value="form.q"
            search-placeholder="Cari judul, slug, atau ringkasan"
            filter-title="Filter artikel"
            filter-description="Saring artikel berdasarkan status editorial dan kategori."
            :active-filter-count="activeFilterCount"
            @search="(value) => { form.q = value; applyFilters() }"
            @apply-filters="applyFilters"
            @reset-filters="resetFilters"
          >
            <div class="space-y-2">
              <Label for="article_status_filter">Status</Label>
              <Select v-model="form.status">
                <SelectTrigger id="article_status_filter">
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua status</SelectItem>
                  <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="article_category_filter">Kategori</Label>
              <Select v-model="form.category">
                <SelectTrigger id="article_category_filter">
                  <SelectValue placeholder="Semua kategori" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua kategori</SelectItem>
                  <SelectItem v-for="option in categoryOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </AdminTableToolbar>
        </CardHeader>
        <CardContent>
          <AdminDataTable
            :columns="columns"
            :rows="records.data"
            :meta="records.meta"
            empty-text="Tidak ada artikel yang cocok dengan filter saat ini."
          >
            <template #cell-title="{ row }">
              <div class="max-w-full space-y-2">
                <div class="flex min-w-0 items-start gap-3">
                  <img
                    v-if="row.cover_url"
                    :src="row.cover_url"
                    :alt="row.title"
                    class="h-12 w-16 shrink-0 rounded-xl object-cover ring-1 ring-black/5 sm:h-14 sm:w-20"
                  />
                  <div class="min-w-0 flex-1">
                    <Button variant="link" class="h-auto max-w-full px-0 text-left font-medium text-slate-950" as-child>
                      <Link :href="row.edit_url" :title="row.title" class="block max-w-full break-words leading-6">
                        {{ truncateTitle(row.title) }}
                      </Link>
                    </Button>
                  </div>
                </div>
              </div>
            </template>

            <template #cell-status="{ row }">
              <Badge variant="outline" :class="statusTone(row.editorial_status_value)">
                {{ row.editorial_status_label }}
              </Badge>
            </template>

            <template #cell-category="{ row }">
              <span class="text-sm text-slate-700">{{ row.category_name || '-' }}</span>
            </template>

            <template #cell-published_at="{ row }">
              <div class="space-y-1">
                <p class="text-sm text-slate-700">{{ formatDateTime(row.published_at) }}</p>
                <p v-if="row.editorial_status_value === 'scheduled'" class="text-xs text-amber-700">
                  Menunggu jadwal tayang
                </p>
              </div>
            </template>

            <template #cell-views="{ row }">
              <span class="text-sm font-medium text-slate-900">{{ row.views }}</span>
            </template>

            <template #cell-tags="{ row }">
              <div class="flex flex-wrap gap-2">
                <Badge v-for="tagName in row.tag_names" :key="`${row.id}-${tagName}`" variant="secondary">
                  {{ tagName }}
                </Badge>
                <span v-if="!row.tag_names.length" class="text-xs text-slate-400">Tanpa tag</span>
              </div>
            </template>

            <template #cell-actions="{ row }">
              <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="row.edit_url"><Pencil class="h-4 w-4" />Edit</Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                  <a :href="row.preview_url" target="_blank" rel="noreferrer"><Eye class="h-4 w-4" />Preview</a>
                </Button>
                <Button variant="destructive" size="sm" @click="destroyRecord(row)">
                  <Trash2 class="h-4 w-4" />Hapus
                </Button>
              </div>
            </template>
          </AdminDataTable>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
