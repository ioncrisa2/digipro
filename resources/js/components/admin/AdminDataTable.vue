<script setup>
import { FlexRender, getCoreRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';
import { router } from '@inertiajs/vue3';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-vue-next';
import { computed, h, ref, useSlots, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  meta: { type: Object, default: null },
  emptyText: { type: String, default: 'Tidak ada data.' },
  rowKey: { type: [String, Function], default: 'id' },
  defaultPerPage: { type: Number, default: 10 },
});

const slots = useSlots();
const sorting = ref([]);
const perPageOptions = ['10', '25', '50', '100'];
const localPerPage = ref(String(props.defaultPerPage));
const localCurrentPage = ref(1);

const paginationLinks = computed(() => props.meta?.links ?? []);
const isServerPaginated = computed(() => Boolean(props.meta));
const currentPerPage = computed(() => String(props.meta?.per_page ?? localPerPage.value ?? props.defaultPerPage));
const colspan = computed(() => props.columns.length || 1);
const summaryText = computed(() => {
  if (isServerPaginated.value) {
    const from = props.meta?.from ?? 0;
    const to = props.meta?.to ?? 0;
    const total = props.meta?.total ?? 0;

    return `Menampilkan ${from}-${to} dari ${total} data`;
  }

  const total = props.rows.length;
  if (!total) {
    return 'Menampilkan 0-0 dari 0 data';
  }

  const perPage = Number(localPerPage.value || props.defaultPerPage);
  const from = (localCurrentPage.value - 1) * perPage + 1;
  const to = Math.min(total, from + displayedRows.value.length - 1);

  return `Menampilkan ${from}-${to} dari ${total} data`;
});

const localLastPage = computed(() => {
  if (!props.rows.length) {
    return 1;
  }

  return Math.max(1, Math.ceil(props.rows.length / Number(localPerPage.value || props.defaultPerPage)));
});

const displayedRows = computed(() => {
  if (isServerPaginated.value) {
    return props.rows;
  }

  const perPage = Number(localPerPage.value || props.defaultPerPage);
  const start = (localCurrentPage.value - 1) * perPage;

  return props.rows.slice(start, start + perPage);
});

const localPaginationLinks = computed(() => Array.from({ length: localLastPage.value }, (_, index) => {
  const page = index + 1;

  return {
    label: String(page),
    page,
    active: page === localCurrentPage.value,
  };
}));

const resolveRowKey = (row, index) => {
  if (typeof props.rowKey === 'function') {
    return props.rowKey(row, index);
  }

  return row?.[props.rowKey] ?? index;
};

const sortingIcon = (direction) => {
  if (direction === 'asc') {
    return h(ChevronUp, { class: 'h-3.5 w-3.5 text-slate-600' });
  }

  if (direction === 'desc') {
    return h(ChevronDown, { class: 'h-3.5 w-3.5 text-slate-600' });
  }

  return h(ChevronsUpDown, { class: 'h-3.5 w-3.5 text-slate-400' });
};

const columnDefs = computed(() => props.columns.map((column) => ({
  id: column.key,
  accessorFn: (row) => row?.[column.key],
  enableSorting: Boolean(column.sortable),
  header: ({ column: tableColumn }) => {
    const slot = slots[`head-${column.key}`];
    const headerContent = slot?.({ column }) ?? column.label;

    if (!column.sortable) {
      return headerContent;
    }

    return h(
      Button,
      {
        variant: 'ghost',
        size: 'sm',
        class: 'h-8 px-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-600 hover:bg-slate-100 hover:text-slate-950',
        onClick: () => tableColumn.toggleSorting(tableColumn.getIsSorted() === 'asc'),
      },
      {
        default: () => [
          typeof headerContent === 'string'
            ? h('span', headerContent)
            : headerContent,
          sortingIcon(tableColumn.getIsSorted()),
        ],
      },
    );
  },
  cell: ({ row }) => {
    const original = row.original;
    const slot = slots[`cell-${column.key}`];

    return slot?.({
      row: original,
      value: original?.[column.key],
      column,
    }) ?? (original?.[column.key] ?? '-');
  },
  meta: {
    headerClass: column.headerClass,
    cellClass: column.cellClass,
  },
})));

const table = useVueTable({
  get data() {
    return displayedRows.value;
  },
  get columns() {
    return columnDefs.value;
  },
  state: {
    get sorting() {
      return sorting.value;
    },
  },
  onSortingChange: (updater) => {
    sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater;
  },
  getCoreRowModel: getCoreRowModel(),
  getSortedRowModel: getSortedRowModel(),
});

watch(() => props.rows.length, () => {
  if (!isServerPaginated.value && localCurrentPage.value > localLastPage.value) {
    localCurrentPage.value = 1;
  }
});

watch(localPerPage, () => {
  localCurrentPage.value = 1;
});

const visit = (url) => {
  if (!url) return;

  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
  });
};

const updatePerPage = (value) => {
  if (!value) return;

  if (!isServerPaginated.value) {
    localPerPage.value = value;
    return;
  }

  const url = new URL(window.location.href);
  url.searchParams.set('per_page', value);
  url.searchParams.set('page', '1');

  visit(url.toString());
};

const visitLocalPage = (page) => {
  localCurrentPage.value = page;
};
</script>

<template>
  <div class="max-w-full space-y-4">
    <div class="max-w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
      <div class="w-full max-w-full overflow-x-auto">
        <Table class="w-full [&_tbody_tr:last-child]:border-b-0">
          <TableHeader class="bg-slate-50/90">
            <TableRow
              v-for="headerGroup in table.getHeaderGroups()"
              :key="headerGroup.id"
              class="hover:bg-slate-50/90"
            >
              <TableHead
                v-for="header in headerGroup.headers"
                :key="header.id"
                :class="cn('h-10 whitespace-nowrap px-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500', header.column.columnDef.meta?.headerClass)"
              >
                <FlexRender
                  v-if="!header.isPlaceholder"
                  :render="header.column.columnDef.header"
                  :props="header.getContext()"
                />
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="row in table.getRowModel().rows"
              :key="resolveRowKey(row.original, row.index)"
              class="hover:bg-slate-50/60"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                :class="cn('px-3 py-2.5 align-top text-sm text-slate-700', cell.column.columnDef.meta?.cellClass)"
              >
                <FlexRender
                  :render="cell.column.columnDef.cell"
                  :props="cell.getContext()"
                />
              </TableCell>
            </TableRow>

            <TableEmpty v-if="!table.getRowModel().rows.length" :colspan="colspan" class="text-slate-500">
              {{ emptyText }}
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <p class="text-sm text-slate-500">
        {{ summaryText }}
      </p>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
        <div class="flex items-center gap-3">
          <span class="text-sm text-slate-500">Baris per halaman</span>
          <Select :model-value="currentPerPage" @update:model-value="updatePerPage">
            <SelectTrigger class="h-9 w-[92px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="option in perPageOptions" :key="option" :value="option">
                {{ option }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div v-if="isServerPaginated ? paginationLinks.length : localPaginationLinks.length > 1" class="flex flex-wrap gap-2">
          <template v-if="isServerPaginated">
            <Button
              v-for="link in paginationLinks"
              :key="`${link.label}-${link.url}`"
              type="button"
              size="sm"
              :variant="link.active ? 'default' : 'outline'"
              :disabled="!link.url"
              @click="visit(link.url)"
            >
              <span v-html="link.label" />
            </Button>
          </template>

          <template v-else>
            <Button
              v-for="link in localPaginationLinks"
              :key="link.page"
              type="button"
              size="sm"
              :variant="link.active ? 'default' : 'outline'"
              @click="visitLocalPage(link.page)"
            >
              {{ link.label }}
            </Button>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
