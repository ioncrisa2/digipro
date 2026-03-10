<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import StatusBadge from '@/components/reviewer/StatusBadge.vue';
import PaginationBar from '@/components/reviewer/PaginationBar.vue';
import { formatDateTime } from '@/utils/reviewer';
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Search } from 'lucide-vue-next';

const props = defineProps({
  filters: Object,
  statusOptions: Array,
  reviews: Object,
});

const form = reactive({
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? 'all',
});

const submit = () => {
  router.get(route('reviewer.reviews.index'), form, {
    preserveState: true,
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Review Queue" />

  <ReviewerLayout title="Review Queue">
    <div class="space-y-6">
      <Card>
        <CardHeader class="pb-4">
          <CardTitle>Filter Review Queue</CardTitle>
          <CardDescription>Cari permohonan berdasarkan request, klien, dan status review.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid gap-4 lg:grid-cols-[1fr_220px_auto] lg:items-end">
            <div class="grid gap-2">
              <label class="text-sm font-medium">Cari</label>
              <Input v-model="form.q" type="text" placeholder="Cari request, klien, nomor..." />
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
            <Button @click="submit">
              <Search class="mr-2 h-4 w-4" />
              Terapkan
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-4">
          <CardTitle>Daftar Review</CardTitle>
          <CardDescription>Queue permohonan yang aktif di sisi reviewer.</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Request</TableHead>
                  <TableHead>Klien</TableHead>
                  <TableHead>Aset</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Kontrak</TableHead>
                  <TableHead>Tanggal</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="item in reviews.data" :key="item.id">
                  <TableCell>
                    <Button variant="link" class="h-auto px-0 font-medium" as-child>
                      <Link :href="item.detail_url">{{ item.request_number }}</Link>
                    </Button>
                  </TableCell>
                  <TableCell>{{ item.client_name }}</TableCell>
                  <TableCell>{{ item.assets_count }}</TableCell>
                  <TableCell><StatusBadge :status="item.status" /></TableCell>
                  <TableCell>{{ item.contract_number || '-' }}</TableCell>
                  <TableCell>{{ formatDateTime(item.requested_at) }}</TableCell>
                </TableRow>
                <TableRow v-if="!reviews.data?.length">
                  <TableCell :colspan="6" class="text-center text-muted-foreground">Belum ada review yang sesuai filter.</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
          <div class="mt-4 border-t pt-4">
            <PaginationBar :links="reviews.links || []" />
          </div>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>
