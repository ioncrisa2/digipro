<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2 } from "lucide-vue-next";

defineProps({
  twoFactorState: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits([
  "open-password-dialog",
  "enable-two-factor",
  "disable-two-factor",
  "confirm-two-factor",
  "toggle-recovery-codes",
  "regenerate-recovery-codes",
]);
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-xl border p-4 space-y-3">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm font-semibold text-slate-900">Ubah Password</div>
          <p class="text-xs text-slate-500">Perbarui password Anda untuk menjaga keamanan akun.</p>
        </div>
        <Button variant="secondary" size="sm" @click="emit('open-password-dialog')">
          Ubah
        </Button>
      </div>
    </div>

    <div class="rounded-xl border p-4 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="text-sm font-semibold text-slate-900">Two-Factor Authentication</div>
          <p class="text-xs text-slate-500">Gunakan aplikasi seperti Google Authenticator atau Authy.</p>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="text-[11px] px-2 py-1 rounded-full"
            :class="
              twoFactorState.enabled && twoFactorState.confirmed
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            {{ twoFactorState.enabled && twoFactorState.confirmed ? "Aktif" : "Belum Aktif" }}
          </span>
          <Button
            v-if="!twoFactorState.enabled"
            variant="secondary"
            size="sm"
            :disabled="twoFactorState.loading"
            @click="emit('enable-two-factor')"
          >
            <Loader2 v-if="twoFactorState.loading" class="mr-2 h-4 w-4 animate-spin" />
            Aktifkan
          </Button>
          <Button
            v-else
            variant="outline"
            size="sm"
            :disabled="twoFactorState.loading"
            @click="emit('disable-two-factor')"
          >
            Nonaktifkan
          </Button>
        </div>
      </div>

      <div v-if="twoFactorState.enabled" class="space-y-4">
        <div v-if="!twoFactorState.confirmed" class="rounded-lg border p-4 bg-slate-50">
          <div class="text-xs text-slate-500 mb-3">
            Scan QR code, lalu masukkan kode 6 digit untuk konfirmasi.
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-md border bg-white p-3 flex items-center justify-center min-h-40">
              <div v-if="twoFactorState.qrSvg" v-html="twoFactorState.qrSvg"></div>
              <div v-else class="text-xs text-slate-500">Memuat QR...</div>
            </div>
            <div class="space-y-3">
              <div>
                <div class="text-xs text-slate-500">Kode Manual</div>
                <div class="font-mono text-sm text-slate-900 break-all">
                  {{ twoFactorState.secretKey || "-" }}
                </div>
              </div>
              <div class="space-y-2">
                <Label for="two_factor_code">Kode 6 Digit</Label>
                <Input
                  id="two_factor_code"
                  v-model="twoFactorState.code"
                  inputmode="numeric"
                  maxlength="6"
                  placeholder="123456"
                />
              </div>
              <Button
                type="button"
                class="bg-slate-900 hover:bg-slate-800"
                :disabled="twoFactorState.loading || !twoFactorState.code"
                @click="emit('confirm-two-factor')"
              >
                <Loader2 v-if="twoFactorState.loading" class="mr-2 h-4 w-4 animate-spin" />
                Konfirmasi
              </Button>
            </div>
          </div>
        </div>

        <div v-else class="rounded-lg border p-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="text-sm font-semibold text-slate-900">Recovery Codes</div>
              <p class="text-xs text-slate-500">Simpan kode ini di tempat aman.</p>
            </div>
            <div class="flex items-center gap-2">
              <Button variant="outline" size="sm" @click="emit('toggle-recovery-codes')">
                {{ twoFactorState.showRecoveryCodes ? "Sembunyikan" : "Lihat" }}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                :disabled="twoFactorState.loading"
                @click="emit('regenerate-recovery-codes')"
              >
                Perbarui
              </Button>
            </div>
          </div>

          <div v-if="twoFactorState.showRecoveryCodes" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div
              v-for="code in twoFactorState.recoveryCodes"
              :key="code"
              class="rounded-md border bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700"
            >
              {{ code }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
