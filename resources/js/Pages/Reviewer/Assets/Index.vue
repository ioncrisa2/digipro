<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import StatusBadge from '@/components/reviewer/StatusBadge.vue';
import PaginationBar from '@/components/reviewer/PaginationBar.vue';
import { formatArea, formatCurrency } from '@/utils/reviewer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Search, SlidersHorizontal } from 'lucide-vue-next';

const props = defineProps({
  filters: Object,
  statusOptions: Array,
  assets: Object,
});

const form = reactive({
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? 'all',
  needs_adjustment: Boolean(props.filters?.needs_adjustment),
});

const submit = () => {
  router.get(route('reviewer.assets.index'), form, {
    preserveState: true,
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Reviewer Assets" />

  <ReviewerLayout title="Assets">
    <div class="space-y-6">
      <Card>
        <CardHeader class="pb-4">
          <CardTitle>Filter Aset Reviewer</CardTitle>
          <CardDescription>Filter alamat, status request, dan aset yang masih perlu adjustment.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid gap-4 lg:grid-cols-[1fr_220px_auto_auto] lg:items-end">
            <div class="grid gap-2">
              <label class="text-sm font-medium">Cari</label>
              <Input v-model="form.q" type="text" placeholder="Cari alamat atau nomor request" />
            </div>
            <div class="grid gap-2">
              <label class="text-sm font-medium">Status</label>
              <Select v-model="form.status">
                <SelectTrigger>
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <label class="flex h-10 items-center gap-2 rounded-md border px-3 text-sm">
              <Checkbox :model-value="form.needs_adjustment" @update:modelValue="(value) => form.needs_adjustment = Boolean(value)" />
              <span>Hanya butuh adjustment</span>
            </label>
            <Button @click="submit">
              <Search class="mr-2 h-4 w-4" />
              Terapkan
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-4">
          <div class="flex items-center justify-between gap-3">
            <div>
              <CardTitle>Daftar Aset</CardTitle>
              <CardDescription>Aset reviewer aktif dan jalur cepat ke detail atau adjustment matrix.</CardDescription>
            </div>
            <SlidersHorizontal class="h-4 w-4 text-muted-foreground" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Request</TableHead>
                  <TableHead>Alamat</TableHead>
                  <TableHead>Jenis</TableHead>
                  <TableHead>LT / LB</TableHead>
                  <TableHead>Pembanding</TableHead>
                  <TableHead>Range</TableHead>
                  <TableHead>Aksi</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="asset in assets.data" :key="asset.id">
                  <TableCell>
                    <p class="font-medium text-foreground">{{ asset.request_number }}</p>
                    <div class="mt-2"><StatusBadge :status="asset.request_status" /></div>
                  </TableCell>
                  <TableCell>{{ asset.address }}</TableCell>
                  <TableCell>{{ asset.asset_type?.label }}</TableCell>
                  <TableCell>{{ formatArea(asset.land_area) }} / {{ formatArea(asset.building_area) }}</TableCell>
                  <TableCell>{{ asset.selected_comparables_count }} dipilih / {{ asset.comparables_count }} total</TableCell>
                  <TableCell>{{ formatCurrency(asset.estimated_value_low) }} - {{ formatCurrency(asset.estimated_value_high) }}</TableCell>
                  <TableCell>
                    <div class="flex flex-wrap gap-2">
                      <Button variant="link" class="h-auto px-0" as-child>
                        <Link :href="asset.detail_url">Detail</Link>
                      </Button>
                      <Button variant="link" class="h-auto px-0" as-child>
                        <Link :href="asset.adjustment_url">Adjustment</Link>
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
                <TableRow v-if="!assets.data?.length">
                  <TableCell :colspan="7" class="text-center text-muted-foreground">Belum ada aset reviewer yang sesuai filter.</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
          <div class="mt-4 border-t pt-4">
            <PaginationBar :links="assets.links || []" />
          </div>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>
