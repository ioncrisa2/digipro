<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { CircleAlert } from 'lucide-vue-next';

const props = defineProps({
  items: { type: Array, default: () => [] },
});

const page = usePage();
const tokens = reactive({});
const flash = computed(() => page.props.flash ?? {});

const sign = (item) => {
  const token = String(tokens[item.id] || '').trim();
  if (!token || token.length < 6 || item.public_appraiser?.readiness?.overall?.is_ready !== true) return;

  router.post(item.sign_url, { keyla_token: token }, { preserveScroll: true });
};

const toneClasses = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  warning: 'border-amber-200 bg-amber-50 text-amber-800',
  danger: 'border-rose-200 bg-rose-50 text-rose-800',
  muted: 'border-slate-200 bg-slate-50 text-slate-700',
};

const badgeClassFor = (tone) => toneClasses[tone] ?? toneClasses.muted;
</script>

<template>
  <Head title="Admin - Pending Contract Signatures" />

  <AdminLayout title="Pending Contract Signatures">
    <div class="mx-auto max-w-5xl space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Kontrak Menunggu Tanda Tangan</CardTitle>
          <CardDescription>
            Daftar kontrak yang sudah ditandatangani customer dan menunggu tanda tangan Penilai Publik (Peruri SIGN-IT).
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Alert v-if="flash.error" variant="destructive" class="mb-4">
            <CircleAlert />
            <AlertTitle>Signing gagal</AlertTitle>
            <AlertDescription>{{ flash.error }}</AlertDescription>
          </Alert>

          <div v-if="!items.length" class="rounded-2xl border border-dashed p-6 text-sm text-slate-500">
            Tidak ada kontrak yang menunggu tanda tangan untuk akun Anda.
          </div>

          <div v-else class="space-y-4">
            <div v-for="item in items" :key="item.id" class="rounded-2xl border bg-white p-4">
              <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                  <p class="text-sm font-semibold text-slate-950">
                    {{ item.request_number }} - {{ item.client_name }}
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    Customer: {{ item.customer?.name || '-' }} ({{ item.customer?.email || '-' }})
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    Signed at: {{ item.customer?.signed_at || '-' }}
                  </p>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <Badge
                      variant="outline"
                      :class="badgeClassFor(item.public_appraiser?.readiness?.certificate?.tone)"
                    >
                      Sertifikat: {{ item.public_appraiser?.readiness?.certificate?.label || 'Belum Diketahui' }}
                    </Badge>
                    <Badge
                      variant="outline"
                      :class="badgeClassFor(item.public_appraiser?.readiness?.keyla?.tone)"
                    >
                      KEYLA: {{ item.public_appraiser?.readiness?.keyla?.label || 'Belum Diketahui' }}
                    </Badge>
                  </div>
                  <p v-if="item.envelope?.last_error" class="mt-2 text-xs text-amber-700">
                    Last error: {{ item.envelope.last_error }}
                  </p>
                  <p class="mt-2 text-xs text-slate-600">
                    {{ item.public_appraiser?.readiness?.overall?.message || 'Status signer internal belum tersedia.' }}
                  </p>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" as-child>
                      <Link :href="item.detail_url">Buka Detail Request</Link>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                      <a :href="route('admin.signatures.contracts.index')">Refresh</a>
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
                    Tanda Tangani (Penilai Publik)
                  </Button>
                  <p class="text-[11px] text-slate-500">
                    Token KEYLA berubah berkala. Pastikan memasukkan token yang sedang aktif.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AdminLayout>
</template>
