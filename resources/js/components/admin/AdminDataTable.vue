<script setup>
import { FlexRender, getCoreRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';
import { router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-vue-next';
import { computed, h, ref, useSlots } from 'vue';
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
});

const slots = useSlots();
const sorting = ref([]);

const paginationLinks = computed(() => props.meta?.links ?? []);
const colspan = computed(() => props.columns.length || 1);

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
    return props.rows;
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

const visit = (url) => {
  if (!url) return;

  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
  });
};
</script>

<template>
  <div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
      <div class="overflow-x-auto">
        <Table class="[&_tbody_tr:last-child]:border-b-0">
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

    <div v-if="paginationLinks.length" class="flex flex-wrap gap-2">
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
    </div>
  </div>
</template>
