<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { ShieldCheck } from 'lucide-vue-next'

const props = defineProps({
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['update:open', 'agree'])

const setOpen = (v) => emit('update:open', v)

const onAgree = () => {
  emit('agree')
  setOpen(false)
}

const page = usePage()
const termsDocument = computed(() => page.props.termsDocument || null)

const formatMonthYear = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(date)
}

const meta = computed(() => {
  const doc = termsDocument.value
  return {
    title: doc?.title || 'Ketentuan Layanan Aplikasi Permohonan Penilaian',
    company: doc?.company || 'DigiPro by KJPP HJAR',
    version: doc?.version || '-',
    effectiveSince: formatMonthYear(doc?.effective_since),
  }
})

const contentHtml = computed(() => {
  return termsDocument.value?.content_html
    || '<p>Ketentuan layanan sedang diperbarui. Silakan hubungi admin untuk informasi terbaru.</p>'
})
</script>

<template>
  <Dialog :open="open" @update:open="setOpen">
    <DialogContent class="sm:max-w-3xl max-h-[90vh] overflow-hidden flex flex-col p-0 gap-0">
      <DialogHeader class="p-6 border-b bg-slate-50/50">
        <div class="flex items-center gap-2 text-blue-600 mb-1">
          <div class="p-1.5 bg-blue-100 rounded-md">
            <ShieldCheck class="w-4 h-4" />
          </div>
          <span class="text-xs font-bold uppercase tracking-wider">Legal Document</span>
        </div>
        <DialogTitle class="text-xl font-bold">{{ meta.title }}</DialogTitle>
      </DialogHeader>

      <div class="flex-1 overflow-y-auto p-6 space-y-8 scrollbar-thin">
        <div class="grid grid-cols-2 gap-4 p-4 bg-slate-50 rounded-lg border border-slate-100">
          <div>
            <p class="text-[10px] uppercase text-slate-400 font-bold">Penyedia Layanan</p>
            <p class="text-xs font-medium text-slate-700">{{ meta.company }}</p>
          </div>
          <div class="text-right">
            <p class="text-[10px] uppercase text-slate-400 font-bold">Versi dan Tanggal</p>
            <p class="text-xs font-medium text-slate-700">{{ meta.version }} - {{ meta.effectiveSince }}</p>
          </div>
        </div>

        <div class="terms-content space-y-6 text-sm text-slate-600" v-html="contentHtml"></div>
      </div>

      <DialogFooter class="p-4 border-t bg-slate-50 flex sm:justify-between items-center">
        <p class="hidden sm:block text-[11px] text-slate-400 italic">
          Harap baca seluruh dokumen sebelum menyetujui.
        </p>
        <div class="flex gap-3 w-full sm:w-auto">
          <Button type="button" variant="ghost" @click="setOpen(false)" class="flex-1 sm:flex-none">Batal</Button>
          <Button type="button" @click="onAgree" class="flex-1 sm:flex-none px-8 bg-blue-600 hover:bg-blue-700">
            Saya Setuju dan Lanjutkan
          </Button>
        </div>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
.terms-content :deep(h3) {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
  margin-top: 18px;
}

.terms-content :deep(p) {
  line-height: 1.7;
  margin-top: 8px;
}

.terms-content :deep(ul) {
  margin-top: 8px;
  padding-left: 18px;
  display: grid;
  gap: 6px;
}

.terms-content :deep(li) {
  list-style: disc;
}
</style>
