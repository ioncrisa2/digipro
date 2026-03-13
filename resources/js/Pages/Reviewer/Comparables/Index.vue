<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import PaginationBar from '@/components/reviewer/PaginationBar.vue';
import { formatArea, formatCurrency, formatPercent } from '@/utils/reviewer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Search, Database } from 'lucide-vue-next';

const props = defineProps({
  filters: Object,
  comparables: Object,
});

const form = reactive({
  q: props.filters?.q ?? '',
  asset_id: props.filters?.asset_id ?? '',
  is_selected: props.filters?.is_selected ?? 'all',
});

const groupedComparables = computed(() => {
  const groups = new Map();

  (props.comparables?.data || []).forEach((item) => {
    const key = String(item.appraisal_asset_id ?? 'unknown');

    if (!groups.has(key)) {
      groups.set(key, {
        assetId: item.appraisal_asset_id,
        requestNumber: item.request_number,
        assetAddress: item.asset_address,
        assetDetailUrl: item.asset_detail_url,
        items: [],
      });
    }

    groups.get(key).items.push(item);
  });

  return Array.from(groups.values()).map((group) => ({
    ...group,
    selectedCount: group.items.filter((item) => item.is_selected).length,
  }));
});

const defaultExpandedGroups = computed(() => {
  const firstGroup = groupedComparables.value[0];

  return firstGroup ? [String(firstGroup.assetId)] : [];
});

const submit = () => {
  router.get(route('reviewer.comparables.index'), form, {
    preserveState: true,
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Reviewer Comparables" />

  <ReviewerLayout title="Comparables">
    <div class="space-y-6">
      <Card>
        <CardHeader class="pb-4">
          <CardTitle>Filter Comparable</CardTitle>
          <CardDescription>Telusuri pembanding berdasarkan ext id, aset, dan status pemakaian.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid gap-4 lg:grid-cols-[1fr_220px_220px_auto] lg:items-end">
            <div class="grid gap-2">
              <label class="text-sm font-medium">Cari</label>
              <Input v-model="form.q" type="text" placeholder="Cari ext id, peruntukan, alamat" />
            </div>
            <div class="grid gap-2">
              <label class="text-sm font-medium">Asset ID</label>
              <Input v-model="form.asset_id" type="number" min="1" placeholder="Filter asset id" />
            </div>
            <div class="grid gap-2">
              <label class="text-sm font-medium">Dipakai</label>
              <Select v-model="form.is_selected">
                <SelectTrigger>
                  <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua</SelectItem>
                  <SelectItem value="1">Dipakai</SelectItem>
                  <SelectItem value="0">Tidak dipakai</SelectItem>
                </SelectContent>
              </Select>
            </div>
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
              <CardTitle>Daftar Comparable</CardTitle>
              <CardDescription>Pembanding ditampilkan per aset agar reviewer bisa membaca konteksnya lebih cepat.</CardDescription>
            </div>
            <Database class="h-4 w-4 text-muted-foreground" />
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="groupedComparables.length" class="space-y-3">
            <Accordion type="multiple" :default-value="defaultExpandedGroups" class="space-y-3">
              <AccordionItem
                v-for="group in groupedComparables"
                :key="`group-${group.assetId}`"
                :value="String(group.assetId)"
                class="overflow-hidden rounded-xl border bg-card px-0"
              >
                <AccordionTrigger class="px-5 py-4 hover:no-underline">
                  <div class="flex w-full flex-col gap-3 text-left md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" class="font-mono">Asset {{ group.assetId }}</Badge>
                        <Badge variant="secondary">{{ group.requestNumber }}</Badge>
                      </div>
                      <p class="mt-2 truncate text-sm font-semibold text-foreground">
                        {{ group.assetAddress }}
                      </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground md:justify-end">
                      <Badge variant="outline">{{ group.items.length }} comparable</Badge>
                      <Badge variant="outline">{{ group.selectedCount }} dipakai</Badge>
                    </div>
                  </div>
                </AccordionTrigger>

                <AccordionContent class="px-5 pb-5 pt-0">
                  <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/30 px-4 py-3">
                    <div>
                      <p class="text-sm font-medium text-foreground">Comparable terasosiasi dengan aset {{ group.assetId }}</p>
                      <p class="text-xs text-muted-foreground">Expand grup untuk melihat seluruh data pembanding yang terkait dengan aset ini.</p>
                    </div>
                    <Button v-if="group.assetDetailUrl" variant="outline" size="sm" as-child>
                      <Link :href="group.assetDetailUrl">Buka Aset</Link>
                    </Button>
                  </div>

                  <div class="overflow-x-auto rounded-xl border">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Ext ID</TableHead>
                          <TableHead>Dipakai</TableHead>
                          <TableHead>Score</TableHead>
                          <TableHead>LT / LB</TableHead>
                          <TableHead>Adj</TableHead>
                          <TableHead>Nilai</TableHead>
                          <TableHead>Aksi</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        <TableRow v-for="item in group.items" :key="item.id">
                          <TableCell class="font-medium">{{ item.external_id }}</TableCell>
                          <TableCell>
                            <Badge :variant="item.is_selected ? 'default' : 'outline'">
                              {{ item.is_selected ? 'Dipakai' : 'Tidak' }}
                            </Badge>
                          </TableCell>
                          <TableCell>{{ item.score ?? '-' }}</TableCell>
                          <TableCell>{{ formatArea(item.raw_land_area) }} / {{ formatArea(item.raw_building_area) }}</TableCell>
                          <TableCell>{{ formatPercent(item.total_adjustment_percent ?? 0) }}</TableCell>
                          <TableCell>
                            <p>{{ formatCurrency(item.adjusted_unit_value) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Indikasi {{ formatCurrency(item.indication_value) }}</p>
                          </TableCell>
                          <TableCell>
                            <div class="flex flex-wrap gap-2">
                              <Button variant="link" class="h-auto px-0" as-child>
                                <Link :href="item.detail_url">Detail</Link>
                              </Button>
                              <Button variant="link" class="h-auto px-0" as-child>
                                <Link :href="item.adjustment_url">Adjust Harga Tanah</Link>
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      </TableBody>
                    </Table>
                  </div>
                </AccordionContent>
              </AccordionItem>
            </Accordion>
          </div>

          <div v-else class="rounded-xl border border-dashed px-6 py-12 text-center text-muted-foreground">
            Belum ada comparable yang sesuai filter.
          </div>

          <div class="mt-4 border-t pt-4">
            <PaginationBar :links="comparables.links || []" />
          </div>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>
