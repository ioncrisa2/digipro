<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const props = defineProps({
  mode: { type: String, required: true },
  record: { type: Object, required: true },
  roleOptions: { type: Array, default: () => [] },
  userOptions: { type: Array, default: () => [] },
  submitUrl: { type: String, required: true },
  indexUrl: { type: String, required: true },
  refreshReadinessUrl: { type: String, default: null },
  peruriActions: {
    type: Object,
    default: () => ({
      register_user_url: null,
      submit_kyc_url: null,
      set_specimen_url: null,
      register_keyla_url: null,
      templates: {
        register_user_payload: '{}',
        kyc_payload: '{}',
        specimen_payload: '{}',
      },
    }),
  },
});

const isEditMode = computed(() => props.mode === 'edit');
const page = usePage();
const onboardingFlash = computed(() => page.props.flash?.peruri_onboarding ?? null);

const form = useForm({
  user_id: props.record.user_id ? String(props.record.user_id) : '',
  role: props.record.role ?? 'reviewer',
  name: props.record.name ?? '',
  email: props.record.email ?? '',
  phone_number: props.record.phone_number ?? '',
  position_title: props.record.position_title ?? '',
  title_suffix: props.record.title_suffix ?? '',
  certification_number: props.record.certification_number ?? '',
  is_active: props.record.is_active ?? true,
  _method: isEditMode.value ? 'put' : 'post',
});

const submit = () => {
  form.post(props.submitUrl, { preserveScroll: true });
};

const refreshReadiness = () => {
  if (!props.refreshReadinessUrl) return;

  router.post(props.refreshReadinessUrl, {}, { preserveScroll: true });
};

const registerUserForm = useForm({
  payload_json: props.peruriActions?.templates?.register_user_payload ?? '{}',
});

const kycForm = useForm({
  payload_json: props.peruriActions?.templates?.kyc_payload ?? '{}',
  kyc_video: null,
});

const specimenForm = useForm({
  payload_json: props.peruriActions?.templates?.specimen_payload ?? '{}',
  signature_image: null,
});

const registerPeruriUser = () => {
  if (!props.peruriActions?.register_user_url) return;

  registerUserForm.post(props.peruriActions.register_user_url, { preserveScroll: true });
};

const submitKyc = () => {
  if (!props.peruriActions?.submit_kyc_url) return;

  kycForm.post(props.peruriActions.submit_kyc_url, {
    preserveScroll: true,
    forceFormData: true,
  });
};

const submitSpecimen = () => {
  if (!props.peruriActions?.set_specimen_url) return;

  specimenForm.post(props.peruriActions.set_specimen_url, {
    preserveScroll: true,
    forceFormData: true,
  });
};

const registerKeyla = () => {
  if (!props.peruriActions?.register_keyla_url) return;

  router.post(props.peruriActions.register_keyla_url, {}, { preserveScroll: true });
};

const peruriStatusLabel = (value, emptyLabel = "Belum dicek") => {
  switch (value) {
    case "ready":
      return "Siap";
    case "expired":
      return "Expired";
    case "inactive":
      return "Belum Aktif";
    case "missing_email":
      return "Email Belum Ada";
    case "error":
      return "Gagal Diperiksa";
    case "unknown":
      return "Belum Diketahui";
    default:
      return emptyLabel;
  }
};

const peruriStatusClass = (value) => {
  switch (value) {
    case "ready":
      return "border-emerald-200 bg-emerald-50 text-emerald-800";
    case "expired":
    case "error":
      return "border-rose-200 bg-rose-50 text-rose-800";
    case "inactive":
    case "missing_email":
    case "unknown":
      return "border-amber-200 bg-amber-50 text-amber-800";
    default:
      return "border-slate-200 bg-slate-50 text-slate-700";
  }
};
</script>

<template>
  <Head :title="isEditMode ? 'Admin - Edit Penandatangan Report' : 'Admin - Tambah Penandatangan Report'" />

  <AdminLayout :title="isEditMode ? 'Edit Penandatangan Report' : 'Tambah Penandatangan Report'">
    <div class="mx-auto max-w-4xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
            {{ isEditMode ? 'Edit Penandatangan Report' : 'Tambah Penandatangan Report' }}
          </h1>
          <p class="mt-2 text-sm text-slate-600">
            Master profil ini dipakai admin saat menentukan reviewer dan penilai publik untuk report DigiPro by KJPP HJAR.
          </p>
        </div>
        <Button variant="outline" as-child><Link :href="indexUrl">Kembali ke daftar</Link></Button>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Profil Penandatangan</CardTitle>
            <CardDescription>Minimal isi nama, peran, dan nomor sertifikasi/izin yang akan tampil di report.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2 md:col-span-2">
              <Label for="signer_user">Akun Internal (untuk portal signing)</Label>
              <Select v-model="form.user_id">
                <SelectTrigger id="signer_user"><SelectValue placeholder="Pilih user (opsional)" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="">(Kosongkan)</SelectItem>
                  <SelectItem v-for="option in userOptions" :key="option.value" :value="String(option.value)">
                    {{ option.label }} <span v-if="option.description" class="text-slate-400">- {{ option.description }}</span>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.user_id" class="text-xs text-rose-600">{{ form.errors.user_id }}</p>
              <p class="text-xs text-slate-500">
                Untuk penandatangan kontrak Peruri, user ini akan digunakan untuk otorisasi saat melakukan signing di portal internal.
              </p>
            </div>

            <div class="space-y-2">
              <Label for="signer_role">Peran</Label>
              <Select v-model="form.role">
                <SelectTrigger id="signer_role"><SelectValue placeholder="Pilih peran" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.role" class="text-xs text-rose-600">{{ form.errors.role }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_name">Nama</Label>
              <Input id="signer_name" v-model="form.name" />
              <p v-if="form.errors.name" class="text-xs text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_email">Email Peruri</Label>
              <Input id="signer_email" v-model="form.email" placeholder="email@domain.com" />
              <p v-if="form.errors.email" class="text-xs text-rose-600">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_phone">No. HP (Peruri)</Label>
              <Input id="signer_phone" v-model="form.phone_number" placeholder="08xxxxxxxxxx" />
              <p v-if="form.errors.phone_number" class="text-xs text-rose-600">{{ form.errors.phone_number }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_position">Jabatan / Peran Tampil</Label>
              <Input id="signer_position" v-model="form.position_title" placeholder="Contoh: Reviewer Bersertifikasi" />
              <p v-if="form.errors.position_title" class="text-xs text-rose-600">{{ form.errors.position_title }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_suffix">Gelar / Suffix</Label>
              <Input id="signer_suffix" v-model="form.title_suffix" placeholder="Contoh: MAPPI (Cert)" />
              <p v-if="form.errors.title_suffix" class="text-xs text-rose-600">{{ form.errors.title_suffix }}</p>
            </div>

            <div class="space-y-2">
              <Label for="signer_certification_number">Nomor Sertifikasi / Izin</Label>
              <Input id="signer_certification_number" v-model="form.certification_number" placeholder="Contoh: MAPPI-12345 / P-6789" />
              <p v-if="form.errors.certification_number" class="text-xs text-rose-600">{{ form.errors.certification_number }}</p>
            </div>

            <div class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700">
              <Checkbox :model-value="form.is_active" @update:model-value="form.is_active = Boolean($event)" />
              <span>Profil aktif dan bisa dipilih pada report</span>
            </div>
          </CardContent>
        </Card>

        <Card v-if="isEditMode">
          <CardHeader>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <CardTitle>Kesiapan Peruri</CardTitle>
                <CardDescription>
                  Status ini di-update oleh flow signing dan dipakai sebagai indikator kesiapan sertifikat serta KEYLA signer internal.
                </CardDescription>
              </div>
              <Button v-if="refreshReadinessUrl" type="button" variant="outline" :disabled="form.processing" @click="refreshReadiness">
                Refresh Readiness
              </Button>
            </div>
          </CardHeader>
          <CardContent class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border p-4">
              <div class="text-xs font-semibold uppercase tracking-widest text-slate-500">Sertifikat</div>
              <div class="mt-3">
                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium" :class="peruriStatusClass(record.peruri_certificate_status)">
                  {{ peruriStatusLabel(record.peruri_certificate_status) }}
                </span>
              </div>
            </div>
            <div class="rounded-xl border p-4">
              <div class="text-xs font-semibold uppercase tracking-widest text-slate-500">KEYLA</div>
              <div class="mt-3">
                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium" :class="peruriStatusClass(record.peruri_keyla_status)">
                  {{ peruriStatusLabel(record.peruri_keyla_status) }}
                </span>
              </div>
            </div>
            <div class="rounded-xl border p-4 md:col-span-2">
              <div class="text-xs font-semibold uppercase tracking-widest text-slate-500">Pemeriksaan Terakhir</div>
              <p class="mt-3 text-sm text-slate-700">{{ record.peruri_last_checked_at || '-' }}</p>
            </div>
          </CardContent>
        </Card>

        <Card v-if="isEditMode">
          <CardHeader>
            <CardTitle>Onboarding Peruri / KEYLA</CardTitle>
            <CardDescription>
              Area ini dipakai admin untuk menjalankan lifecycle signer internal: registrasi user, kirim E-KYC, set specimen, lalu registrasi KEYLA.
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <Alert v-if="onboardingFlash?.action === 'register_keyla' && onboardingFlash?.qr_image" class="border-sky-200 bg-sky-50 text-sky-950">
              <AlertTitle>QR KEYLA tersedia</AlertTitle>
              <AlertDescription>
                Scan QR ini di aplikasi KEYLA milik signer untuk menyelesaikan aktivasi.
              </AlertDescription>
              <div class="mt-4 flex justify-center rounded-2xl border border-sky-200 bg-white p-4">
                <img :src="onboardingFlash.qr_image" alt="QR KEYLA signer" class="h-56 w-56 object-contain" />
              </div>
            </Alert>

            <div class="grid gap-6 xl:grid-cols-2">
              <div class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <h3 class="text-base font-semibold text-slate-950">1. Registrasi User PDS</h3>
                    <p class="mt-1 text-sm text-slate-600">
                      Payload default diambil dari profil signer. Tambahkan field lain sesuai kontrak request PDS di env Anda.
                    </p>
                  </div>
                  <Button type="button" variant="outline" :disabled="registerUserForm.processing" @click="registerPeruriUser">
                    Register User
                  </Button>
                </div>
                <div class="mt-4 space-y-2">
                  <Label for="peruri_register_payload">Payload JSON</Label>
                  <Textarea id="peruri_register_payload" v-model="registerUserForm.payload_json" rows="10" class="font-mono text-xs" />
                  <p v-if="registerUserForm.errors.payload_json" class="text-xs text-rose-600">{{ registerUserForm.errors.payload_json }}</p>
                </div>
              </div>

              <div class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <h3 class="text-base font-semibold text-slate-950">2. Submit Video E-KYC</h3>
                    <p class="mt-1 text-sm text-slate-600">
                      Upload video E-KYC signer. File akan dikirim ke PDS dalam bentuk base64 oleh adapter backend.
                    </p>
                  </div>
                  <Button type="button" variant="outline" :disabled="kycForm.processing" @click="submitKyc">
                    Kirim KYC
                  </Button>
                </div>
                <div class="mt-4 space-y-4">
                  <div class="space-y-2">
                    <Label for="peruri_kyc_video">Video KYC</Label>
                    <Input id="peruri_kyc_video" type="file" accept="video/*" @input="kycForm.kyc_video = $event.target.files?.[0] ?? null" />
                    <p v-if="kycForm.errors.kyc_video" class="text-xs text-rose-600">{{ kycForm.errors.kyc_video }}</p>
                  </div>
                  <div class="space-y-2">
                    <Label for="peruri_kyc_payload">Payload JSON Tambahan</Label>
                    <Textarea id="peruri_kyc_payload" v-model="kycForm.payload_json" rows="6" class="font-mono text-xs" />
                    <p v-if="kycForm.errors.payload_json" class="text-xs text-rose-600">{{ kycForm.errors.payload_json }}</p>
                  </div>
                </div>
              </div>

              <div class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <h3 class="text-base font-semibold text-slate-950">3. Set Specimen Tanda Tangan</h3>
                    <p class="mt-1 text-sm text-slate-600">
                      Upload gambar tanda tangan signer. Backend akan meng-encode file ke base64 saat request ke PDS.
                    </p>
                  </div>
                  <Button type="button" variant="outline" :disabled="specimenForm.processing" @click="submitSpecimen">
                    Set Specimen
                  </Button>
                </div>
                <div class="mt-4 space-y-4">
                  <div class="space-y-2">
                    <Label for="peruri_specimen_image">Gambar Tanda Tangan</Label>
                    <Input id="peruri_specimen_image" type="file" accept="image/png,image/jpeg" @input="specimenForm.signature_image = $event.target.files?.[0] ?? null" />
                    <p v-if="specimenForm.errors.signature_image" class="text-xs text-rose-600">{{ specimenForm.errors.signature_image }}</p>
                  </div>
                  <div class="space-y-2">
                    <Label for="peruri_specimen_payload">Payload JSON Tambahan</Label>
                    <Textarea id="peruri_specimen_payload" v-model="specimenForm.payload_json" rows="6" class="font-mono text-xs" />
                    <p v-if="specimenForm.errors.payload_json" class="text-xs text-rose-600">{{ specimenForm.errors.payload_json }}</p>
                  </div>
                </div>
              </div>

              <div class="rounded-2xl border p-4">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <h3 class="text-base font-semibold text-slate-950">4. Registrasi KEYLA</h3>
                    <p class="mt-1 text-sm text-slate-600">
                      Setelah akun signer terdaftar, generate QR KEYLA agar signer bisa menghubungkan aplikasi mobile-nya.
                    </p>
                  </div>
                  <Button type="button" variant="outline" @click="registerKeyla">
                    Generate QR KEYLA
                  </Button>
                </div>
                <div class="mt-4 rounded-xl border bg-slate-50 p-4 text-sm text-slate-600">
                  Jalankan `Refresh Readiness` setelah registrasi atau aktivasi selesai untuk memperbarui status certificate dan KEYLA di profil signer.
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child><Link :href="indexUrl">Batal</Link></Button>
          <Button type="submit" :disabled="form.processing">
            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Profil' }}
          </Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
