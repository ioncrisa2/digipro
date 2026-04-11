<script setup>
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2 } from "lucide-vue-next";

defineProps({
  open: { type: Boolean, default: false },
  step: { type: Number, default: 1 },
  passwordForm: { type: Object, required: true },
});

const emit = defineEmits(["update:open", "close", "back", "verify", "submit"]);
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Ubah Password</DialogTitle>
        <DialogDescription>
          {{ step === 1 ? "Masukkan password lama terlebih dahulu." : "Buat password baru Anda." }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="step === 1" class="space-y-4">
        <div class="space-y-2">
          <Label for="current_password_modal">Password Lama</Label>
          <Input
            id="current_password_modal"
            v-model="passwordForm.current_password"
            type="password"
            autocomplete="current-password"
          />
          <p v-if="passwordForm.errors.current_password" class="text-xs text-red-500 mt-1">
            {{ passwordForm.errors.current_password }}
          </p>
        </div>
      </div>

      <div v-else class="space-y-4">
        <div class="grid grid-cols-1 gap-4">
          <div class="space-y-2">
            <Label for="password_modal">Password Baru</Label>
            <Input
              id="password_modal"
              v-model="passwordForm.password"
              type="password"
              autocomplete="new-password"
            />
            <p v-if="passwordForm.errors.password" class="text-xs text-red-500 mt-1">
              {{ passwordForm.errors.password }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="password_confirmation_modal">Konfirmasi Password Baru</Label>
            <Input
              id="password_confirmation_modal"
              v-model="passwordForm.password_confirmation"
              type="password"
              autocomplete="new-password"
            />
          </div>
        </div>
      </div>

      <DialogFooter class="gap-2 sm:justify-end">
        <Button variant="outline" type="button" @click="step === 1 ? emit('close') : emit('back')">
          {{ step === 1 ? "Batal" : "Kembali" }}
        </Button>
        <Button
          v-if="step === 1"
          type="button"
          class="bg-slate-900 hover:bg-slate-800"
          :disabled="!passwordForm.current_password || passwordForm.processing"
          @click="emit('verify')"
        >
          <Loader2 v-if="passwordForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          Lanjut
        </Button>
        <Button
          v-else
          type="button"
          class="bg-slate-900 hover:bg-slate-800"
          :disabled="passwordForm.processing"
          @click="emit('submit')"
        >
          <Loader2 v-if="passwordForm.processing" class="mr-2 h-4 w-4 animate-spin" />
          Simpan Password
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
