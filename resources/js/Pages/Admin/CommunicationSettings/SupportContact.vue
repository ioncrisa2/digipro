<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
  record: { type: Object, required: true },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
});

const form = useForm({
  name: props.record.name ?? "",
  phone: props.record.phone ?? "",
  whatsapp: props.record.whatsapp ?? "",
  email: props.record.email ?? "",
  availability_label: props.record.availability_label ?? "",
  _method: "put",
});

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};
</script>

<template>
  <Head title="Admin - Support Contact" />

  <AdminLayout title="Support Contact">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Komunikasi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Support Contact</h1>
          <p class="mt-2 text-sm text-slate-600">
            Data ini tampil di profile customer dan area bantuan saat customer membutuhkan klarifikasi langsung dari admin.
          </p>
        </div>
        <Button variant="outline" as-child>
          <Link :href="indexUrl">Kembali ke inbox</Link>
        </Button>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Kontak Publik Support</CardTitle>
            <CardDescription>
              Pastikan nomor telepon, WhatsApp, email, dan jam layanan selalu mutakhir karena customer akan melihat data ini langsung.
            </CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="support_name">Contact Person</Label>
              <Input id="support_name" v-model="form.name" />
              <p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="support_phone">Nomor Telepon</Label>
              <Input id="support_phone" v-model="form.phone" />
              <p v-if="form.errors.phone" class="text-xs text-rose-600">{{ form.errors.phone }}</p>
            </div>

            <div class="space-y-2">
              <Label for="support_whatsapp">WhatsApp</Label>
              <Input id="support_whatsapp" v-model="form.whatsapp" />
              <p v-if="form.errors.whatsapp" class="text-xs text-rose-600">{{ form.errors.whatsapp }}</p>
            </div>

            <div class="space-y-2">
              <Label for="support_email">Email</Label>
              <Input id="support_email" v-model="form.email" type="email" />
              <p v-if="form.errors.email" class="text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <Label for="support_availability">Jam Layanan</Label>
              <Input id="support_availability" v-model="form.availability_label" />
              <p class="text-xs text-slate-500">Contoh: Senin-Jumat 08:00-17:00 WIB</p>
              <p v-if="form.errors.availability_label" class="text-xs text-rose-600">{{ form.errors.availability_label }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
          <Button type="button" variant="outline" as-child>
            <Link :href="indexUrl">Batal</Link>
          </Button>
          <Button type="submit" :disabled="form.processing">
            Simpan Support Contact
          </Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
