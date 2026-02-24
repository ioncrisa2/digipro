<script setup>
import { computed } from "vue";
import { Filter, Search, X } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const props = defineProps({
  searchQuery: {
    type: String,
    default: "",
  },
  statusFilter: {
    type: String,
    default: "all",
  },
  statusFilterOptions: {
    type: Array,
    required: true,
  },
  hasActiveFilters: {
    type: Boolean,
    required: true,
  },
  resetFilters: {
    type: Function,
    required: true,
  },
  getStatusConfig: {
    type: Function,
    required: true,
  },
});

const emit = defineEmits(["update:searchQuery", "update:statusFilter"]);

const localSearch = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});

const localStatus = computed({
  get: () => props.statusFilter,
  set: (value) => emit("update:statusFilter", value),
});
</script>

<template>
  <Card class="shadow-sm">
    <CardContent class="p-4">
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
          <Search class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <Input
            v-model="localSearch"
            placeholder="Cari nomor permohonan atau lokasi..."
            class="pl-10 h-11"
          />
        </div>

        <Select v-model="localStatus">
          <SelectTrigger class="w-full sm:w-60 h-11">
            <div class="flex items-center gap-2">
              <Filter class="w-4 h-4" />
              <SelectValue placeholder="Semua Status" />
            </div>
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in statusFilterOptions"
              :key="option.value"
              :value="option.value"
            >
              <div class="flex items-center gap-2">
                <div :class="['w-2 h-2 rounded-full', option.dotColor]"></div>
                {{ option.label }}
              </div>
            </SelectItem>
          </SelectContent>
        </Select>

        <Button
          v-if="hasActiveFilters"
          variant="outline"
          @click="resetFilters"
          class="sm:w-auto h-11"
        >
          <X class="w-4 h-4 mr-2" />
          Reset
        </Button>
      </div>

      <div v-if="hasActiveFilters" class="flex flex-wrap gap-2 mt-3 pt-3 border-t">
        <div class="text-xs text-slate-500 flex items-center gap-2">
          <Filter class="w-3 h-3" />
          Filter aktif:
        </div>
        <Badge
          v-if="localSearch"
          variant="secondary"
          class="gap-1 cursor-pointer hover:bg-slate-200"
          @click="emit('update:searchQuery', '')"
        >
          Pencarian: "{{ localSearch }}"
          <X class="w-3 h-3" />
        </Badge>
        <Badge
          v-if="localStatus !== 'all'"
          variant="secondary"
          class="gap-1 cursor-pointer hover:bg-slate-200"
          @click="emit('update:statusFilter', 'all')"
        >
          Status: {{ getStatusConfig(localStatus).label }}
          <X class="w-3 h-3" />
        </Badge>
      </div>
    </CardContent>
  </Card>
</template>
