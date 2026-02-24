<script setup>
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

defineProps({
  open: {
    type: Boolean,
    required: true,
  },
});

const emit = defineEmits(["update:open", "confirm"]);
</script>

<template>
  <AlertDialog :open="open" @update:open="emit('update:open', $event)">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Konfirmasi Keluar</AlertDialogTitle>
        <AlertDialogDescription>
          Apakah Anda yakin ingin keluar dari akun Anda? Anda harus login kembali untuk mengakses aplikasi.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Batal</AlertDialogCancel>
        <AlertDialogAction @click="emit('confirm')" class="bg-red-600 hover:bg-red-700">
          Ya, Keluar
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
