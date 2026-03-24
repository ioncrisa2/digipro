<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ArrowLeft, Upload } from "lucide-vue-next";

const props = defineProps({
  record: { type: Object, required: true },
  batch: { type: Object, required: true },
  submit_url: { type: String, required: true },
  back_url: { type: String, required: true },
});

const form = useForm({
  replacements: {},
});

const formatDateTime = (value) => {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
};

const onFileChange = (itemId, event) => {
  const file = event?.target?.files?.[0] ?? null;
  if (file) {
    form.replacements[itemId] = file;
    return;
  }

  delete form.replacements[itemId];
};

const submit = () => {
  form.post(props.submit_url, {
    forceFormData: true,
    preserveScroll: true,
  });
};
</script>

<template>
  <Head :title="`Revisi Dokumen - ${record.request_number}`" />

  <DashboardLayout>
    <div class="space-y-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-2">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold">Revisi Dokumen</h1>
            <Badge variant="secondary">{{ record.request_number }}</Badge>
          </div>
          <p class="text-sm text-muted-foreground">
            Upload ulang hanya dokumen atau foto yang diminta admin. File lama tetap tersimpan sebagai histori.
          </p>
        </div>

        <Button variant="outline" as-child>
          <Link :href="back_url">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Kembali ke Detail Request
          </Link>
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Ringkasan Permintaan Revisi</CardTitle>
          <CardDescription>
            Batch #{{ batch.id }} dibuat pada {{ formatDateTime(batch.created_at) }}.
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-if="batch.admin_note" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ batch.admin_note }}
          </div>

          <form class="space-y-4" @submit.prevent="submit">
            <div
              v-for="item in batch.items"
              :key="item.id"
              class="rounded-2xl border p-4"
            >
              <div class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p class="font-medium text-slate-950">{{ item.target_label }}</p>
                    <p v-if="item.asset_address" class="text-xs text-muted-foreground">{{ item.asset_address }}</p>
                  </div>
                  <Badge variant="outline">{{ item.status === 'pending' ? 'Menunggu Upload Ulang' : item.status }}</Badge>
                </div>

                <div class="rounded-xl border bg-slate-50 px-3 py-2 text-sm text-slate-700">
                  {{ item.issue_note }}
                </div>

                <div class="grid gap-3 lg:grid-cols-2">
                  <div class="rounded-xl border p-3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">File Sebelumnya</p>
                    <div v-if="item.original_file" class="mt-2 space-y-1">
                      <p class="text-sm font-medium text-slate-950">{{ item.original_file.original_name }}</p>
                      <p class="text-xs text-muted-foreground">{{ formatDateTime(item.original_file.created_at) }}</p>
                      <Button variant="outline" size="sm" class="mt-2" as-child>
                        <a :href="item.original_file.url" target="_blank" rel="noreferrer">Buka File Lama</a>
                      </Button>
                    </div>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                      Tidak ada file sebelumnya. Anda perlu mengunggah file baru untuk item ini.
                    </p>
                  </div>

                  <div class="rounded-xl border p-3">
                    <Label :for="`replacement_${item.id}`">Upload File Pengganti</Label>
                    <Input
                      :id="`replacement_${item.id}`"
                      class="mt-2"
                      type="file"
                      :accept="item.accept"
                      @change="onFileChange(item.id, $event)"
                    />
                    <p class="mt-2 text-xs text-muted-foreground">
                      Format yang diterima: {{ item.accept }}.
                    </p>
                    <p v-if="form.errors[`replacements.${item.id}`]" class="mt-2 text-xs text-rose-600">
                      {{ form.errors[`replacements.${item.id}`] }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end">
              <Button type="submit" :disabled="form.processing">
                <Upload class="mr-2 h-4 w-4" />
                Kirim Revisi Dokumen
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </DashboardLayout>
</template>
