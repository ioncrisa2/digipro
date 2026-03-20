<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const props = defineProps({
  filters: {
    type: Object,
    default: () => ({ q: '', status: 'all' }),
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  summary: {
    type: Object,
    default: () => ({ total: 0, active: 0, inactive: 0 }),
  },
  records: {
    type: Array,
    default: () => [],
  },
  createUrl: {
    type: String,
    required: true,
  },
  paymentsUrl: {
    type: String,
    required: true,
  },
  legacyPanelUrl: {
    type: String,
    default: '/legacy-admin',
  },
});

const applyFilters = (patch = {}) => {
  router.get(route('admin.finance.office-bank-accounts.index'), {
    ...props.filters,
    ...patch,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyRecord = (item) => {
  if (!window.confirm(`Hapus rekening ${item.bank_name} (${item.account_number})?`)) {
    return;
  }

  router.delete(item.destroy_url, {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Admin - Rekening Kantor" />

  <AdminLayout title="Rekening Kantor">
    <div class="space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Batch 8</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Rekening Kantor</h1>
          <p class="mt-2 text-sm text-slate-600">
            Slice read-only untuk data rekening kantor sebelum operasi create/edit dipindah penuh dari Filament.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button as-child>
            <Link :href="createUrl">Tambah Rekening</Link>
          </Button>
          <Button variant="outline" as-child>
            <Link :href="paymentsUrl">Kembali ke Pembayaran</Link>
          </Button>
          <Button variant="outline" as-child>
            <a :href="legacyPanelUrl">Buka di Legacy Admin</a>
          </Button>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Total Rekening</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.total }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aktif</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.active }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nonaktif</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary.inactive }}</p>
          </CardContent>
        </Card>
      </section>

      <Card>
        <CardHeader>
          <CardTitle>Filter Rekening</CardTitle>
          <CardDescription>Filter dasar untuk membaca rekening aktif/nonaktif tanpa membuka resource legacy.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="space-y-2">
            <Label for="bank_q">Cari</Label>
            <Input
              id="bank_q"
              :model-value="filters.q"
              type="text"
              placeholder="Cari bank, nomor rekening, atau nama pemilik"
              @change="applyFilters({ q: $event.target.value })"
            />
          </div>

          <div class="space-y-2">
            <Label for="bank_status">Status</Label>
            <Select :model-value="filters.status" @update:model-value="applyFilters({ status: $event })">
              <SelectTrigger id="bank_status">
                <SelectValue placeholder="Pilih status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in statusOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Daftar Rekening</CardTitle>
          <CardDescription>CRUD rekening kantor sekarang berjalan di workspace admin Vue.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div
            v-for="item in records"
            :key="item.id"
            class="rounded-2xl border p-4"
          >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-medium text-slate-950">{{ item.bank_name }}</p>
                  <Badge variant="outline" :class="item.is_active ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-800 border-slate-200'">
                    {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                  </Badge>
                </div>
                <p class="mt-1 text-sm text-slate-700">{{ item.account_number }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ item.account_holder }}<span v-if="item.branch"> - {{ item.branch }}</span></p>
              </div>
              <div class="text-right text-sm text-slate-600">
                <p>{{ item.currency }}</p>
                <p class="mt-1">Urutan: {{ item.sort_order }}</p>
                <p class="mt-1">{{ item.updated_at ? new Date(item.updated_at).toLocaleString('id-ID') : '-' }}</p>
              </div>
            </div>
            <p v-if="item.notes" class="mt-3 text-sm text-slate-600">{{ item.notes }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <Button variant="outline" size="sm" as-child>
                <Link :href="item.edit_url">Edit</Link>
              </Button>
              <Button variant="outline" size="sm" @click="destroyRecord(item)">
                Hapus
              </Button>
              <Button v-if="item.legacy_url" variant="outline" size="sm" as-child>
                <a :href="item.legacy_url">Lihat di Legacy</a>
              </Button>
            </div>
          </div>

          <div v-if="!records.length" class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">
            Tidak ada rekening kantor yang cocok dengan filter saat ini.
          </div>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
