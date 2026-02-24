<script setup>
import {
  Calendar,
  Home,
  MapPin,
  Printer,
  Eye,
  ExternalLink,
  ChevronLeft,
  ChevronRight,
} from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

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
  <div class="lg:hidden space-y-4">
    <Card
      v-for="item in items"
      :key="item.id"
      class="cursor-pointer hover:shadow-md hover:border-slate-300 transition-all"
      @click="onViewDetail(item.id)"
    >
      <CardHeader class="pb-3">
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <CardTitle class="text-base font-semibold text-slate-900 mb-1.5">
              {{ item.request_number }}
            </CardTitle>
            <CardDescription class="flex items-center gap-1.5 text-xs">
              <Calendar class="w-3.5 h-3.5" />
              {{ formatDate(item.requested_at) }}
              <span class="text-slate-400">-</span>
              {{ formatDateRelative(item.requested_at) }}
            </CardDescription>
          </div>
          <Badge
            :variant="getStatusConfig(item.status).variant"
            :class="[
              getStatusConfig(item.status).bgColor,
              getStatusConfig(item.status).color,
              getStatusConfig(item.status).borderColor,
              'gap-1 border shrink-0',
            ]"
          >
            <component :is="getStatusConfig(item.status).icon" class="w-3 h-3" />
            <span class="hidden sm:inline">{{ getStatusConfig(item.status).label }}</span>
          </Badge>
        </div>
      </CardHeader>

      <CardContent class="space-y-3">
        <div class="grid grid-cols-1 gap-3 text-sm">
          <div>
            <span class="text-xs text-slate-500 font-medium">Jenis Laporan</span>
            <p class="text-slate-900 font-medium mt-0.5">
              {{ item.report_type_label }}
            </p>
          </div>
        </div>

        <div class="pt-2 border-t space-y-2">
          <div class="flex items-center gap-2 text-sm">
            <Home class="w-4 h-4 text-slate-400 shrink-0" />
            <span class="text-slate-700">
              <span class="font-semibold">{{ item.assets_count }}</span> Unit Properti
            </span>
          </div>
          <div class="flex items-start gap-2 text-sm">
            <MapPin class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
            <span class="text-slate-700 line-clamp-2">{{ item.location }}</span>
          </div>
          <div
            v-if="item.report_format !== 'digital'"
            class="flex items-center gap-2 text-xs text-slate-500"
          >
            <Printer class="w-3.5 h-3.5" />
            Hard Copy: {{ item.physical_copies_count }}x
          </div>
        </div>

        <div class="pt-2 border-t">
          <Button
            variant="ghost"
            size="sm"
            @click.stop="onViewDetail(item.id)"
            class="w-full justify-center gap-2 hover:bg-slate-100"
          >
            <Eye class="w-4 h-4" />
            Lihat Detail Lengkap
            <ExternalLink class="w-3 h-3" />
          </Button>
        </div>
      </CardContent>
    </Card>

    <div class="flex gap-2 pt-2">
      <Button
        class="flex-1"
        variant="outline"
        :disabled="!prevLink?.url"
        @click="onPage(prevLink.url)"
      >
        <ChevronLeft class="w-4 h-4 mr-1" />
        Sebelumnya
      </Button>
      <Button
        class="flex-1"
        variant="outline"
        :disabled="!nextLink?.url"
        @click="onPage(nextLink.url)"
      >
        Selanjutnya
        <ChevronRight class="w-4 h-4 ml-1" />
      </Button>
    </div>
  </div>
</template>
