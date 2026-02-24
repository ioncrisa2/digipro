<script setup>
import { FileText, Plus, Search, X } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";

defineProps({
  hasActiveFilters: {
    type: Boolean,
    required: true,
  },
  onResetFilters: {
    type: Function,
    required: true,
  },
  onCreate: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <Empty v-if="!hasActiveFilters" class="my-12">
    <EmptyHeader>
      <EmptyMedia variant="icon" class="bg-slate-100">
        <FileText class="text-slate-500" />
      </EmptyMedia>
      <EmptyTitle class="text-2xl">Belum Ada Permohonan</EmptyTitle>
      <EmptyDescription class="text-base max-w-md mx-auto">
        Anda belum memiliki permohonan penilaian. Mulai dengan membuat permohonan baru untuk
        mendapatkan penilaian properti Anda.
      </EmptyDescription>
    </EmptyHeader>
    <EmptyContent>
      <Button @click="onCreate" size="lg" class="shadow-sm">
        <Plus class="w-5 h-5 mr-2" />
        Buat Permohonan Pertama
      </Button>
    </EmptyContent>
  </Empty>

  <Empty v-else class="my-12 border-dashed border-2">
    <EmptyHeader>
      <EmptyMedia variant="icon" class="bg-slate-100">
        <Search class="text-slate-500" />
      </EmptyMedia>
      <EmptyTitle class="text-2xl">Tidak Ada Hasil</EmptyTitle>
      <EmptyDescription class="text-base max-w-md mx-auto">
        Tidak ditemukan permohonan yang cocok dengan pencarian atau filter Anda. Coba ubah kata
        kunci atau reset filter.
      </EmptyDescription>
    </EmptyHeader>
    <EmptyContent>
      <div class="flex flex-col sm:flex-row gap-3">
        <Button @click="onResetFilters" variant="outline" size="lg">
          <X class="w-5 h-5 mr-2" />
          Reset Filter
        </Button>
        <Button @click="onCreate" size="lg" class="shadow-sm">
          <Plus class="w-5 h-5 mr-2" />
          Buat Permohonan Baru
        </Button>
      </div>
    </EmptyContent>
  </Empty>
</template>
