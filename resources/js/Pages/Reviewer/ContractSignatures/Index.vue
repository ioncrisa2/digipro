<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ReviewerLayout from '@/layouts/ReviewerLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CircleAlert } from 'lucide-vue-next';

const props = defineProps({
  signer: { type: Object, default: null },
  bulkSignUrl: { type: String, default: null },
  summary: { type: Object, default: () => ({ siap_sign: 0, gagal: 0, selesai: 0 }) },
  items: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const bulkResult = computed(() => flash.value.bulk_sign_result ?? null);
const tokens = reactive({});
const selectedIds = ref([]);
const bulkToken = ref('');

const summaryCards = [
  { key: 'siap_sign', label: 'Siap Sign' },
  { key: 'gagal', label: 'Perlu Diulang' },
  { key: 'selesai', label: 'Selesai' },
];

const toneClasses = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  warning: 'border-amber-200 bg-amber-50 text-amber-800',
  danger: 'border-rose-200 bg-rose-50 text-rose-800',
  muted: 'border-slate-200 bg-slate-50 text-slate-700',
};

const badgeClassFor = (tone) => toneClasses[tone] ?? toneClasses.muted;
const selectableItems = computed(() => props.items.filter((item) => item.can_bulk_sign === true));
const allSelectableIds = computed(() => selectableItems.value.map((item) => item.id));
const selectedCount = computed(() => selectedIds.value.length);
const allSelected = computed(() => selectableItems.value.length > 0 && selectedIds.value.length === selectableItems.value.length);

const sign = (item) => {
  const token = String(tokens[item.id] || '').trim();
  if (!token || token.length < 6 || item.public_appraiser?.readiness?.overall?.is_ready !== true) return;

  router.post(item.sign_url, { keyla_token: token }, { preserveScroll: true });
};

const toggleSelection = (itemId) => {
  if (selectedIds.value.includes(itemId)) {
    selectedIds.value = selectedIds.value.filter((id) => id !== itemId);
    return;
  }

  selectedIds.value = [...selectedIds.value, itemId];
};

const toggleSelectAll = () => {
  selectedIds.value = allSelected.value ? [] : [...allSelectableIds.value];
};

const bulkSign = () => {
  const token = bulkToken.value.trim();
  if (!props.bulkSignUrl || !token || token.length < 6 || selectedIds.value.length === 0) return;

  router.post(props.bulkSignUrl, {
    keyla_token: token,
    appraisal_request_ids: selectedIds.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedIds.value = [];
      bulkToken.value = '';
    },
  });
};
</script>

<template>
  <Head title="Reviewer - Kontrak Penilai Publik" />

  <ReviewerLayout title="Kontrak Penilai Publik">
    <div class="space-y-6">
      <section class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Queue Kontrak Penilai Publik</h1>
          <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Antrean kontrak yang sudah selesai ditandatangani customer dan menunggu token KEYLA dari akun Anda.
          </p>
        </div>
        <div v-if="signer" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
          <div class="font-medium text-slate-950">{{ signer.name }}</div>
          <div>{{ signer.email || '-' }}</div>
          <div class="mt-2 text-xs text-slate-500">{{ signer.position_title || 'Penilai publik aktif' }}</div>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-3">
        <Card v-for="card in summaryCards" :key="card.key" class="border-slate-200/80 shadow-sm">
          <CardContent class="p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ card.label }}</p>
            <p class="mt-3 text-4xl font-semibold text-slate-950">{{ summary[card.key] ?? 0 }}</p>
          </CardContent>
        </Card>
      </section>

      <Card class="border-slate-200/80 shadow-sm">
        <CardHeader>
          <CardTitle>Daftar Request Siap Sign</CardTitle>
          <CardDescription>
            Anda bisa tetap sign satu per satu, atau memilih beberapa kontrak dan memprosesnya dalam satu sesi bulk sign. Eksekusi tetap diproses per dokumen.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Alert v-if="flash.error" variant="destructive" class="mb-4">
            <CircleAlert />
            <AlertTitle>Signing gagal</AlertTitle>
            <AlertDescription>{{ flash.error }}</AlertDescription>
          </Alert>

          <Alert v-else-if="flash.success" class="mb-4 border-emerald-200 bg-emerald-50 text-emerald-900">
            <CircleAlert />
            <AlertTitle>Signing berhasil</AlertTitle>
            <AlertDescription>{{ flash.success }}</AlertDescription>
          </Alert>

          <Alert v-if="bulkResult" class="mb-4 border-sky-200 bg-sky-50 text-sky-950">
            <CircleAlert />
            <AlertTitle>Hasil Bulk Sign</AlertTitle>
            <AlertDescription>
              {{ bulkResult.success_count ?? 0 }} berhasil dari {{ bulkResult.selected_count ?? 0 }} kontrak yang dipilih.
            </AlertDescription>
            <div v-if="(bulkResult.failed_items ?? []).length" class="mt-4 space-y-2 text-sm">
              <div class="font-medium">Kontrak yang gagal:</div>
              <div
                v-for="failedItem in bulkResult.failed_items"
                :key="`${failedItem.request_id}-${failedItem.request_number}`"
                class="rounded-xl border border-sky-200 bg-white px-3 py-2"
              >
                <div class="font-medium text-slate-950">{{ failedItem.request_number }}</div>
                <div class="mt-1 text-slate-700">{{ failedItem.message }}</div>
              </div>
            </div>
          </Alert>

          <div v-if="!signer" class="rounded-2xl border border-dashed p-6 text-sm text-slate-500">
            Akun ini belum terhubung ke profil penilai publik aktif. Minta admin mengaitkan akun internal ke master signer terlebih dahulu.
          </div>

          <div v-else-if="!items.length" class="rounded-2xl border border-dashed p-6 text-sm text-slate-500">
            Tidak ada kontrak aktif yang menunggu tanda tangan Anda.
          </div>

          <div v-else class="space-y-4">
            <div class="rounded-2xl border bg-slate-50 p-4">
              <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <div>
                  <div class="text-sm font-medium text-slate-950">Bulk Sign Session</div>
                  <p class="mt-1 text-sm text-slate-600">
                    Pilih beberapa kontrak yang siap sign. Sistem tetap memproses satu per satu dan menyimpan hasil individual.
                  </p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <Button type="button" variant="outline" size="sm" :disabled="!selectableItems.length" @click="toggleSelectAll">
                    {{ allSelected ? 'Batalkan Pilih Semua' : 'Pilih Semua Yang Siap' }}
                  </Button>
                </div>
              </div>

              <div class="mt-4 grid gap-3 xl:grid-cols-[minmax(0,1fr)_280px_auto] xl:items-end">
                <div class="rounded-xl border bg-white px-4 py-3 text-sm text-slate-700">
                  {{ selectedCount }} kontrak dipilih dari {{ selectableItems.length }} kontrak yang eligible untuk bulk sign.
                </div>
                <div class="space-y-2">
                  <Label class="text-xs">Token KEYLA</Label>
                  <Input v-model="bulkToken" placeholder="Masukkan token KEYLA untuk sesi bulk" autocomplete="one-time-code" />
                </div>
                <Button
                  type="button"
                  class="w-full xl:w-auto"
                  :disabled="selectedCount === 0 || bulkToken.trim().length < 6"
                  @click="bulkSign"
                >
                  Bulk Sign
                </Button>
              </div>
            </div>

            <div v-for="item in items" :key="item.id" class="rounded-2xl border bg-white p-4">
              <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <input
                      v-if="item.can_bulk_sign"
                      :checked="selectedIds.includes(item.id)"
                      type="checkbox"
                      class="h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-300"
                      @change="toggleSelection(item.id)"
                    />
                    <p class="text-sm font-semibold text-slate-950">{{ item.request_number }} - {{ item.client_name }}</p>
                    <Badge variant="outline" :class="badgeClassFor(item.queue_status?.tone)">
                      {{ item.queue_status?.label || 'Menunggu' }}
                    </Badge>
                    <Badge v-if="item.can_bulk_sign" variant="outline" class="border-sky-200 bg-sky-50 text-sky-800">
                      Eligible Bulk
                    </Badge>
                  </div>

                  <p class="mt-2 text-xs text-slate-500">
                    Customer: {{ item.customer?.name || '-' }} ({{ item.customer?.email || '-' }})
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    Status customer: {{ item.customer?.status_label || '-' }} <span v-if="item.customer?.signed_at">- {{ item.customer?.signed_at }}</span>
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    No. kontrak: {{ item.contract_number || '-' }}
                  </p>

                  <div class="mt-3 flex flex-wrap gap-2">
                    <Badge variant="outline" :class="badgeClassFor(item.public_appraiser?.readiness?.certificate?.tone)">
                      Sertifikat: {{ item.public_appraiser?.readiness?.certificate?.label || 'Belum Diketahui' }}
                    </Badge>
                    <Badge variant="outline" :class="badgeClassFor(item.public_appraiser?.readiness?.keyla?.tone)">
                      KEYLA: {{ item.public_appraiser?.readiness?.keyla?.label || 'Belum Diketahui' }}
                    </Badge>
                  </div>

                  <p class="mt-2 text-xs text-slate-600">
                    {{ item.public_appraiser?.readiness?.overall?.message || 'Status signer belum tersedia.' }}
                  </p>
                  <p v-if="item.envelope?.last_error" class="mt-2 text-xs text-amber-700">
                    Error terakhir: {{ item.envelope.last_error }}
                  </p>

                  <div class="mt-4 flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" as-child>
                      <Link :href="item.detail_url">Detail</Link>
                    </Button>
                  </div>
                </div>

                <div class="w-full max-w-sm space-y-2 rounded-2xl border bg-slate-50 p-3">
                  <Label class="text-xs">Token KEYLA</Label>
                  <Input
                    v-model="tokens[item.id]"
                    placeholder="Masukkan token KEYLA"
                    autocomplete="one-time-code"
                  />
                  <Button
                    size="sm"
                    class="w-full"
                    :disabled="item.public_appraiser?.readiness?.overall?.is_ready !== true || String(tokens[item.id] || '').trim().length < 6"
                    @click="sign(item)"
                  >
                    Sign Kontrak
                  </Button>
                  <p class="text-[11px] text-slate-500">
                    Token KEYLA berubah berkala. Gunakan token terbaru dari aplikasi KEYLA Anda.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </ReviewerLayout>
</template>
