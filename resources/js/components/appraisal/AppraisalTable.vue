<script setup>
import { ArrowUpDown, ChevronLeft, ChevronRight } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import AppraisalTableRow from "@/components/appraisal/AppraisalTableRow.vue";

defineProps({
  items: {
    type: Array,
    required: true,
  },
  getStatusConfig: {
    type: Function,
    required: true,
  },
  formatDate: {
    type: Function,
    required: true,
  },
  formatDateRelative: {
    type: Function,
    required: true,
  },
  prevLink: {
    type: Object,
    default: null,
  },
  nextLink: {
    type: Object,
    default: null,
  },
  onViewDetail: {
    type: Function,
    required: true,
  },
  onPage: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <Card class="hidden lg:block shadow-sm">
    <Table>
      <TableHeader>
        <TableRow class="bg-slate-50/50 hover:bg-slate-50/50">
          <TableHead class="font-semibold">
            <div class="flex items-center gap-1">
              Nomor Permohonan
              <ArrowUpDown class="w-3 h-3 text-slate-400" />
            </div>
          </TableHead>
          <TableHead class="font-semibold">Jenis Laporan</TableHead>
          <TableHead class="font-semibold">Properti</TableHead>
          <TableHead class="font-semibold">
            <div class="flex items-center gap-1">
              Tanggal Pengajuan
              <ArrowUpDown class="w-3 h-3 text-slate-400" />
            </div>
          </TableHead>
          <TableHead class="font-semibold">Status</TableHead>
          <TableHead class="text-right font-semibold">Aksi</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <AppraisalTableRow
          v-for="item in items"
          :key="item.id"
          :item="item"
          :get-status-config="getStatusConfig"
          :format-date="formatDate"
          :format-date-relative="formatDateRelative"
          @view="onViewDetail"
        />
      </TableBody>
    </Table>

    <div class="flex items-center justify-between border-t bg-slate-50/50 px-6 py-4">
      <div class="text-sm text-slate-600">
        Menampilkan <span class="font-semibold">{{ items.length }}</span> permohonan
      </div>
      <div class="flex gap-2">
        <Button
          variant="outline"
          size="sm"
          :disabled="!prevLink?.url"
          @click="onPage(prevLink.url)"
          class="gap-1"
        >
          <ChevronLeft class="w-4 h-4" />
          Sebelumnya
        </Button>
        <Button
          variant="outline"
          size="sm"
          :disabled="!nextLink?.url"
          @click="onPage(nextLink.url)"
          class="gap-1"
        >
          Selanjutnya
          <ChevronRight class="w-4 h-4" />
        </Button>
      </div>
    </div>
  </Card>
</template>
