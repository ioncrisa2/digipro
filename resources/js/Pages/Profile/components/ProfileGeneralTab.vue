<script setup>
import { computed } from "vue";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Loader2, Pencil, Trash2, Upload } from "lucide-vue-next";

const props = defineProps({
  user: { type: Object, required: true },
  isCustomer: { type: Boolean, default: false },
  supportContact: { type: Object, default: null },
  avatarUrl: { type: String, required: true },
  avatarInputRef: { type: [Object, Function], default: null },
  profileEditing: { type: Boolean, default: false },
  profileForm: { type: Object, required: true },
  selectedBillingLabels: { type: Object, required: true },
  billingDeliveryReady: { type: Boolean, default: false },
  provinceOptions: { type: Array, default: () => [] },
  regencyOptions: { type: Array, default: () => [] },
  districtOptions: { type: Array, default: () => [] },
  villageOptions: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "edit-profile",
  "open-avatar-picker",
  "avatar-selected",
  "remove-avatar",
  "submit-profile",
  "cancel-edit",
  "billing-province-change",
  "billing-regency-change",
  "billing-district-change",
]);

const userInitial = computed(() => (props.user.name || "U").slice(0, 1).toUpperCase());
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-4">
        <Avatar class="h-16 w-16 border border-slate-200">
          <AvatarImage :src="avatarUrl" />
          <AvatarFallback class="text-sm font-semibold text-slate-700">
            {{ userInitial }}
          </AvatarFallback>
        </Avatar>
        <div class="space-y-1">
          <div class="text-lg font-semibold text-slate-900">{{ user.name || "-" }}</div>
          <div class="text-sm text-slate-500">{{ user.email || "-" }}</div>
          <div
            v-if="isCustomer && !user.phone_number"
            class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800"
          >
            Nomor telepon belum diatur. Mohon lengkapi profil.
          </div>
        </div>
      </div>

      <Button variant="outline" size="sm" class="gap-2" @click="emit('edit-profile')">
        <Pencil class="h-4 w-4" />
        Edit Profil
      </Button>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <input
        :ref="avatarInputRef"
        type="file"
        accept="image/*"
        class="hidden"
        @change="emit('avatar-selected', $event)"
      />
      <Button type="button" variant="secondary" size="sm" class="gap-2" @click="emit('open-avatar-picker')">
        <Upload class="h-4 w-4" />
        Upload Foto
      </Button>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        class="gap-2 text-slate-600"
        :disabled="!user.avatar_url"
        @click="emit('remove-avatar')"
      >
        <Trash2 class="h-4 w-4" />
        Reset ke Default
      </Button>
      <span class="text-xs text-slate-500">JPG/PNG, maks 2 MB</span>
    </div>

    <Separator />

    <form @submit.prevent="emit('submit-profile')" class="space-y-6">
      <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-600">
        Kolom bertanda <span class="font-semibold text-red-500">*</span> wajib diisi.
        Label <span class="font-semibold text-slate-500">Opsional</span> hanya pelengkap bila tersedia.
      </div>

      <section class="space-y-4">
        <div>
          <h3 class="text-sm font-semibold text-slate-900">Akun Dasar</h3>
          <p class="text-xs text-slate-500">Informasi identitas utama yang tampil di akun Anda.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label for="name">Nama Lengkap <span class="text-red-500">*</span></Label>
            <div
              v-if="!profileEditing"
              class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
            >
              {{ user.name || "-" }}
            </div>
            <div v-else>
              <Input id="name" v-model="profileForm.name" autocomplete="name" />
              <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-500">
                {{ profileForm.errors.name }}
              </p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="email">Email <span class="text-red-500">*</span></Label>
            <div
              v-if="!profileEditing"
              class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
            >
              {{ user.email || "-" }}
            </div>
            <div v-else>
              <Input id="email" v-model="profileForm.email" type="email" autocomplete="email" />
              <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-500">
                {{ profileForm.errors.email }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="space-y-4">
        <div>
          <h3 class="text-sm font-semibold text-slate-900">Kontak Utama</h3>
          <p class="text-xs text-slate-500">
            Nomor telepon dipakai untuk follow-up operasional seperti review pembatalan dan klarifikasi admin.
          </p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label for="phone_number">Nomor Telepon <span class="text-red-500">*</span></Label>
            <div
              v-if="!profileEditing"
              class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
            >
              {{ user.phone_number || "-" }}
            </div>
            <div v-else>
              <Input id="phone_number" v-model="profileForm.phone_number" autocomplete="tel" />
              <p v-if="profileForm.errors.phone_number" class="mt-1 text-xs text-red-500">
                {{ profileForm.errors.phone_number }}
              </p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="whatsapp_number">Nomor WhatsApp <span class="text-slate-400">(Opsional)</span></Label>
            <div
              v-if="!profileEditing"
              class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
            >
              {{ user.whatsapp_number || "-" }}
            </div>
            <div v-else>
              <Input id="whatsapp_number" v-model="profileForm.whatsapp_number" autocomplete="tel" />
              <p v-if="profileForm.errors.whatsapp_number" class="mt-1 text-xs text-red-500">
                {{ profileForm.errors.whatsapp_number }}
              </p>
            </div>
          </div>
        </div>

        <div class="space-y-2">
          <Label for="address">Alamat <span class="text-slate-400">(Opsional)</span></Label>
          <div
            v-if="!profileEditing"
            class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
          >
            {{ user.address || "-" }}
          </div>
          <div v-else>
            <Textarea id="address" v-model="profileForm.address" rows="4" />
            <p v-if="profileForm.errors.address" class="mt-1 text-xs text-red-500">
              {{ profileForm.errors.address }}
            </p>
          </div>
        </div>
      </section>

      <section v-if="isCustomer" class="space-y-4">
        <div>
          <h3 class="text-sm font-semibold text-slate-900">Billing & Pengiriman Laporan</h3>
          <p class="text-xs text-slate-500">
            Data ini dipakai untuk pengiriman 1 hard copy laporan dan dokumen billing setiap kali Anda membuat
            permohonan baru.
          </p>
        </div>

        <div
          :class="[
            'rounded-2xl border px-4 py-4',
            billingDeliveryReady ? 'border-emerald-200 bg-emerald-50/80' : 'border-amber-200 bg-amber-50/80',
          ]"
        >
          <p :class="['text-sm font-medium', billingDeliveryReady ? 'text-emerald-900' : 'text-amber-900']">
            {{
              billingDeliveryReady
                ? 'Profil billing sudah siap untuk pengiriman laporan.'
                : 'Lengkapi nama penerima, nomor telepon, dan alamat detail billing agar permohonan bisa dikirim.'
            }}
          </p>
          <p :class="['mt-1 text-sm', billingDeliveryReady ? 'text-emerald-800' : 'text-amber-800']">
            Sistem akan otomatis mengirim 1 hard copy ke alamat billing yang tersimpan pada profil ini.
          </p>
        </div>

        <div class="space-y-3">
          <div>
            <h4 class="text-sm font-semibold text-slate-900">Penerima & Kontak Pengiriman</h4>
            <p class="text-xs text-slate-500">Nomor telepon pengiriman mengikuti kontak utama pada profil Anda.</p>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="billing_recipient_name">Nama Penerima Laporan <span class="text-red-500">*</span></Label>
              <div
                v-if="!profileEditing"
                class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
              >
                {{ user.billing_recipient_name || "-" }}
              </div>
              <div v-else>
                <Input id="billing_recipient_name" v-model="profileForm.billing_recipient_name" />
                <p v-if="profileForm.errors.billing_recipient_name" class="mt-1 text-xs text-red-500">
                  {{ profileForm.errors.billing_recipient_name }}
                </p>
              </div>
            </div>

            <div class="space-y-2">
              <Label>Nomor Telepon Pengiriman</Label>
              <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                {{ user.phone_number || "-" }}
              </div>
              <p class="text-xs text-slate-500">Ubah nomor ini pada bagian Kontak Utama jika perlu diperbarui.</p>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <div>
            <h4 class="text-sm font-semibold text-slate-900">Alamat Billing</h4>
            <p class="text-xs text-slate-500">Alamat berikut dipakai sebagai tujuan kirim laporan cetak.</p>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="billing_province_id">Provinsi <span class="text-slate-400">(Opsional)</span></Label>
              <div
                v-if="!profileEditing"
                class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
              >
                {{ selectedBillingLabels.province }}
              </div>
              <div v-else>
                <Select
                  :model-value="profileForm.billing_province_id || undefined"
                  @update:model-value="emit('billing-province-change', $event)"
                >
                  <SelectTrigger id="billing_province_id" class="w-full">
                    <SelectValue placeholder="Pilih provinsi" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in provinceOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="profileForm.errors.billing_province_id" class="mt-1 text-xs text-red-500">
                  {{ profileForm.errors.billing_province_id }}
                </p>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="billing_regency_id">Kabupaten / Kota <span class="text-slate-400">(Opsional)</span></Label>
              <div
                v-if="!profileEditing"
                class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
              >
                {{ selectedBillingLabels.regency }}
              </div>
              <div v-else>
                <Select
                  :model-value="profileForm.billing_regency_id || undefined"
                  @update:model-value="emit('billing-regency-change', $event)"
                >
                  <SelectTrigger id="billing_regency_id" class="w-full">
                    <SelectValue placeholder="Pilih kabupaten / kota" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in regencyOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="profileForm.errors.billing_regency_id" class="mt-1 text-xs text-red-500">
                  {{ profileForm.errors.billing_regency_id }}
                </p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="billing_district_id">Kecamatan <span class="text-slate-400">(Opsional)</span></Label>
              <div
                v-if="!profileEditing"
                class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
              >
                {{ selectedBillingLabels.district }}
              </div>
              <div v-else>
                <Select
                  :model-value="profileForm.billing_district_id || undefined"
                  @update:model-value="emit('billing-district-change', $event)"
                >
                  <SelectTrigger id="billing_district_id" class="w-full">
                    <SelectValue placeholder="Pilih kecamatan" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in districtOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="profileForm.errors.billing_district_id" class="mt-1 text-xs text-red-500">
                  {{ profileForm.errors.billing_district_id }}
                </p>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="billing_village_id">Kelurahan / Desa <span class="text-slate-400">(Opsional)</span></Label>
              <div
                v-if="!profileEditing"
                class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
              >
                {{ selectedBillingLabels.village }}
              </div>
              <div v-else>
                <Select v-model="profileForm.billing_village_id">
                  <SelectTrigger id="billing_village_id" class="w-full">
                    <SelectValue placeholder="Pilih kelurahan / desa" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in villageOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="profileForm.errors.billing_village_id" class="mt-1 text-xs text-red-500">
                  {{ profileForm.errors.billing_village_id }}
                </p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="billing_postal_code">Kode Pos <span class="text-slate-400">(Opsional)</span></Label>
              <div
                v-if="!profileEditing"
                class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
              >
                {{ user.billing_postal_code || "-" }}
              </div>
              <div v-else>
                <Input id="billing_postal_code" v-model="profileForm.billing_postal_code" inputmode="numeric" />
                <p v-if="profileForm.errors.billing_postal_code" class="mt-1 text-xs text-red-500">
                  {{ profileForm.errors.billing_postal_code }}
                </p>
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="billing_address_detail">Alamat Detail Billing <span class="text-red-500">*</span></Label>
            <p class="text-xs text-slate-500">Tuliskan alamat lengkap agar pengiriman hard copy tidak tertunda.</p>
            <div
              v-if="!profileEditing"
              class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
            >
              {{ user.billing_address_detail || "-" }}
            </div>
            <div v-else>
              <Textarea id="billing_address_detail" v-model="profileForm.billing_address_detail" rows="4" />
              <p v-if="profileForm.errors.billing_address_detail" class="mt-1 text-xs text-red-500">
                {{ profileForm.errors.billing_address_detail }}
              </p>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <div>
            <h4 class="text-sm font-semibold text-slate-900">Dokumen Billing</h4>
            <p class="text-xs text-slate-500">
              Data ini dipakai untuk invoice, faktur pajak, dan dokumen tagihan bila diperlukan.
            </p>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="billing_npwp">NPWP Billing <span class="text-slate-400">(Opsional)</span></Label>
              <p class="text-xs text-slate-500">
                Gunakan NPWP lawan transaksi bila customer membutuhkan faktur pajak.
              </p>
              <div
                v-if="!profileEditing"
                class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
              >
                {{ user.billing_npwp || "-" }}
              </div>
              <div v-else>
                <Input id="billing_npwp" v-model="profileForm.billing_npwp" placeholder="00.000.000.0-000.000" />
                <p v-if="profileForm.errors.billing_npwp" class="mt-1 text-xs text-red-500">
                  {{ profileForm.errors.billing_npwp }}
                </p>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="billing_nik">NIK Billing <span class="text-slate-400">(Opsional)</span></Label>
              <p class="text-xs text-slate-500">
                Isi NIK bila billing tidak memakai NPWP sebagai identitas pajak.
              </p>
              <div
                v-if="!profileEditing"
                class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
              >
                {{ user.billing_nik || "-" }}
              </div>
              <div v-else>
                <Input
                  id="billing_nik"
                  v-model="profileForm.billing_nik"
                  inputmode="numeric"
                  placeholder="3201xxxxxxxxxxxx"
                />
                <p v-if="profileForm.errors.billing_nik" class="mt-1 text-xs text-red-500">
                  {{ profileForm.errors.billing_nik }}
                </p>
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="billing_email">Email Billing <span class="text-slate-400">(Opsional)</span></Label>
            <p class="text-xs text-slate-500">
              Email ini dipakai untuk pengiriman invoice, faktur pajak, atau dokumen tagihan bila diperlukan.
            </p>
            <div
              v-if="!profileEditing"
              class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900"
            >
              {{ user.billing_email || "-" }}
            </div>
            <div v-else>
              <Input id="billing_email" v-model="profileForm.billing_email" type="email" placeholder="billing@contoh.id" />
              <p v-if="profileForm.errors.billing_email" class="mt-1 text-xs text-red-500">
                {{ profileForm.errors.billing_email }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section v-if="isCustomer && supportContact" class="space-y-4">
        <div>
          <h3 class="text-sm font-semibold text-slate-900">Butuh Bantuan</h3>
          <p class="text-xs text-slate-500">
            Gunakan kontak resmi ini bila Anda membutuhkan klarifikasi langsung dari tim admin.
          </p>
        </div>

        <div class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 md:grid-cols-2">
          <div class="space-y-1">
            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Contact Person</div>
            <div class="text-sm font-medium text-slate-950">{{ supportContact.name || "-" }}</div>
          </div>
          <div class="space-y-1">
            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Telepon</div>
            <div class="text-sm font-medium text-slate-950">{{ supportContact.phone || "-" }}</div>
          </div>
          <div class="space-y-1">
            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">WhatsApp</div>
            <div class="text-sm font-medium text-slate-950">{{ supportContact.whatsapp || "-" }}</div>
          </div>
          <div class="space-y-1">
            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Email</div>
            <div class="text-sm font-medium text-slate-950">{{ supportContact.email || "-" }}</div>
          </div>
          <div class="space-y-1 md:col-span-2">
            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Jam Layanan</div>
            <div class="text-sm font-medium text-slate-950">{{ supportContact.availability_label || "-" }}</div>
          </div>
        </div>
      </section>

      <div v-if="profileEditing" class="flex justify-end gap-2">
        <Button type="button" variant="outline" @click="emit('cancel-edit')">
          Batal
        </Button>
        <Button type="submit" class="bg-slate-900 hover:bg-slate-800" :disabled="profileForm.processing">
          <Loader2 v-if="profileForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          Simpan Perubahan
        </Button>
      </div>
    </form>
  </div>
</template>
