<script setup>
import { Calendar, Home, MapPin, Printer, Eye } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { TableRow, TableCell } from "@/components/ui/table";

const props = defineProps({
  item: {
    type: Object,
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
});

const emit = defineEmits(["view"]);
</script>

<template>
  <TableRow
    class="cursor-pointer hover:bg-slate-50 transition-colors"
    @click="emit('view', item.id)"
  >
    <TableCell>
      <div class="flex flex-col gap-1">
        <span class="font-semibold text-slate-900">{{ item.request_number }}</span>
        <span
          v-if="item.report_format !== 'digital'"
          class="text-xs text-slate-500 flex items-center gap-1"
        >
          <Printer class="w-3 h-3" />
          {{ item.physical_copies_count }}x Hard Copy
        </span>
      </div>
    </TableCell>

    <TableCell>
      <Badge variant="outline" class="font-normal">
        {{ item.report_type_label }}
      </Badge>
    </TableCell>

    <TableCell>
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-1.5 text-slate-900">
          <Home class="w-4 h-4 text-slate-400" />
          <span class="font-medium">{{ item.assets_count }} Unit</span>
        </div>
        <div class="flex items-center gap-1.5 text-xs text-slate-500">
          <MapPin class="w-3.5 h-3.5" />
          <span class="line-clamp-1">{{ item.location }}</span>
        </div>
      </div>
    </TableCell>

    <TableCell>
      <div class="flex flex-col gap-0.5">
        <div class="flex items-center gap-1.5 text-sm text-slate-900">
          <Calendar class="w-4 h-4 text-slate-400" />
          <span>{{ formatDate(item.requested_at) }}</span>
        </div>
        <span class="text-xs text-slate-500 pl-5">
          {{ formatDateRelative(item.requested_at) }}
        </span>
      </div>
    </TableCell>

    <TableCell>
      <Badge
        :variant="getStatusConfig(item.status).variant"
        :class="[
          getStatusConfig(item.status).bgColor,
          getStatusConfig(item.status).color,
          getStatusConfig(item.status).borderColor,
          'gap-1.5 px-3 py-1 border',
        ]"
      >
        <component :is="getStatusConfig(item.status).icon" class="w-3.5 h-3.5" />
        {{ getStatusConfig(item.status).label }}
      </Badge>
    </TableCell>

    <TableCell class="text-right">
      <Button
        variant="ghost"
        size="sm"
        @click.stop="emit('view', item.id)"
        class="hover:bg-slate-100"
      >
        <Eye class="w-4 h-4 mr-1.5" />
        Lihat Detail
      </Button>
    </TableCell>
  </TableRow>
</template>
