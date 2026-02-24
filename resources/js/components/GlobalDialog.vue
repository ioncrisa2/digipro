<!-- components/GlobalDialog.vue -->
<script setup>
import { useDialogStore } from '@/stores/dialogStore';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Loader2 } from 'lucide-vue-next';

const dialog = useDialogStore();
</script>

<template>
  <AlertDialog :open="dialog.isOpen">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ dialog.title }}</AlertDialogTitle>
        <AlertDialogDescription class="text-left">
          {{ dialog.description }}
        </AlertDialogDescription>
      </AlertDialogHeader>

      <AlertDialogFooter>
        <AlertDialogCancel
          v-if="dialog.showCancel"
          @click="dialog.handleCancel"
          :disabled="dialog.isProcessing"
        >
          {{ dialog.cancelText }}
        </AlertDialogCancel>

        <AlertDialogAction
          @click="dialog.handleConfirm"
          :class="dialog.confirmButtonClass"
          :disabled="dialog.isProcessing"
        >
          <Loader2
            v-if="dialog.isProcessing"
            class="w-4 h-4 mr-2 animate-spin"
          />
          {{ dialog.confirmText }}
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
