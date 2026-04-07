<script setup>
import { computed } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { ArrowLeft } from "lucide-vue-next";
import { formatCurrency } from "@/utils/reviewer";

const props = defineProps({
  record: { type: Object, required: true },
  customer: { type: Object, required: true },
  appraisal: { type: Object, required: true },
});

const canReview = computed(() => ["pending", "in_progress"].includes(props.record.review_status));

const approveForm = useForm({
  review_note: props.record.review_note ?? "",
});

const rejectForm = useForm({
  review_note: props.record.review_note ?? "",
});

const reviewTone = computed(() => {
  switch (props.record.review_status) {
    case "pending":
      return "bg-amber-100 text-amber-900 border-amber-200";
    case "in_progress":
      return "bg-sky-100 text-sky-900 border-sky-200";
    case "approved":
      return "bg-emerald-100 text-emerald-900 border-emerald-200";
    case "rejected":
      return "bg-rose-100 text-rose-900 border-rose-200";
    default:
      return "bg-slate-100 text-slate-800 border-slate-200";
  }
});

const goBack = () => {
  router.visit(route("admin.appraisal-requests.cancellations.index"));
};

const markInProgress = () => {
  router.post(props.record.actions.mark_in_progress_url, {}, { preserveScroll: true });
};

const approve = () => {
  approveForm.post(props.record.actions.approve_url, { preserveScroll: true });
};

const reject = () => {
  rejectForm.post(props.record.actions.reject_url, { preserveScroll: true });
};
</script>

<template>
  <Head :title="`Review Pembatalan ${appraisal.request_number}`" />

  <AdminLayout title="Detail Pembatalan Request">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
          <Button variant="ghost" size="icon" @click="goBack">
            <ArrowLeft class="h-5 w-5" />
          </Button>
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="text-2xl font-semibold text-slate-950">Review Pembatalan Request</h1>
              <Badge variant="secondary">{{ appraisal.request_number }}</Badge>
              <Badge variant="outline" :class="reviewTone">{{ record.review_status_label }}</Badge>
            </div>
            <p class="mt-1 text-sm text-slate-600">
              Tinjau alasan customer, hubungi kontak yang tersedia, lalu putuskan apakah pembatalan disetujui atau ditolak.
            </p>
          </div>
        </div>

        <Button variant="outline" as-child>
          <Link :href="record.show_request_url">Buka Detail Request</Link>
        </Button>
      </div>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Pengajuan Customer</CardTitle>
              <CardDescription>Alasan pembatalan dan status request sebelum customer mengajukan review.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Status Sebelum</p>
                  <p class="mt-2 text-sm font-medium text-slate-950">{{ record.status_before_request_label }}</p>
                </div>
                <div class="rounded-xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Waktu Pengajuan</p>
                  <p class="mt-2 text-sm font-medium text-slate-950">{{ record.created_at }}</p>
                </div>
              </div>

              <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-amber-800">Alasan Customer</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-amber-950">{{ record.reason }}</p>
              </div>

              <div v-if="record.review_note" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Catatan Review Admin</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-900">{{ record.review_note }}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Ringkasan Request</CardTitle>
              <CardDescription>Informasi utama appraisal yang sedang diajukan pembatalan.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nama Client</p>
                  <p class="mt-2 text-sm font-medium text-slate-950">{{ appraisal.client_name }}</p>
                </div>
                <div class="rounded-xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Status Saat Ini</p>
                  <p class="mt-2 text-sm font-medium text-slate-950">{{ appraisal.status_label }}</p>
                </div>
                <div class="rounded-xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Tanggal Request</p>
                  <p class="mt-2 text-sm font-medium text-slate-950">{{ appraisal.requested_at }}</p>
                </div>
                <div class="rounded-xl border p-4">
                  <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Fee</p>
                  <p class="mt-2 text-sm font-medium text-slate-950">{{ formatCurrency(appraisal.fee_total) }}</p>
                </div>
              </div>

              <div class="rounded-2xl border p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Aset Terkait</p>
                <div class="mt-3 space-y-2">
                  <div
                    v-for="asset in appraisal.assets"
                    :key="asset.id"
                    class="rounded-xl border bg-slate-50 px-3 py-3"
                  >
                    <p class="text-sm font-medium text-slate-950">{{ asset.address || "-" }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ asset.asset_type_label }}</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Kontak Customer</CardTitle>
              <CardDescription>Gunakan snapshot kontak ini untuk follow-up pembatalan.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="rounded-xl border p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nama</p>
                <p class="mt-2 text-sm font-medium text-slate-950">{{ customer.name }}</p>
              </div>
              <div class="rounded-xl border p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Email</p>
                <p class="mt-2 text-sm font-medium text-slate-950">{{ customer.email }}</p>
              </div>
              <div class="rounded-xl border p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Nomor Telepon</p>
                <p class="mt-2 text-sm font-medium text-slate-950">{{ customer.phone_number }}</p>
              </div>
              <div class="rounded-xl border p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">WhatsApp</p>
                <p class="mt-2 text-sm font-medium text-slate-950">{{ customer.whatsapp_number || "-" }}</p>
              </div>
              <div class="rounded-xl border p-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Sedang Dihubungi</p>
                <p class="mt-2 text-sm font-medium text-slate-950">{{ record.contacted_at || "Belum ditandai" }}</p>
              </div>
            </CardContent>
          </Card>

          <Card v-if="canReview">
            <CardHeader>
              <CardTitle>Aksi Review</CardTitle>
              <CardDescription>Putuskan review pembatalan setelah admin menghubungi customer.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
              <Button
                variant="outline"
                class="w-full"
                @click="markInProgress"
              >
                Tandai Sedang Dihubungi
              </Button>

              <div class="space-y-2">
                <Label for="approve_review_note">Catatan Persetujuan</Label>
                <Textarea
                  id="approve_review_note"
                  v-model="approveForm.review_note"
                  rows="4"
                  placeholder="Opsional. Catatan ini akan menjadi alasan pembatalan final yang terlihat oleh customer bila diisi."
                />
                <p v-if="approveForm.errors.review_note" class="text-xs text-rose-600">{{ approveForm.errors.review_note }}</p>
                <Button class="w-full" :disabled="approveForm.processing" @click="approve">
                  Setujui Pembatalan
                </Button>
              </div>

              <div class="space-y-2">
                <Label for="reject_review_note">Catatan Penolakan</Label>
                <Textarea
                  id="reject_review_note"
                  v-model="rejectForm.review_note"
                  rows="4"
                  placeholder="Jelaskan kenapa pembatalan ditolak atau apa yang perlu dilanjutkan customer."
                />
                <p v-if="rejectForm.errors.review_note" class="text-xs text-rose-600">{{ rejectForm.errors.review_note }}</p>
                <Button variant="destructive" class="w-full" :disabled="rejectForm.processing" @click="reject">
                  Tolak Pengajuan
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
