<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAppraisalRequestStore } from '@/stores/appraisalRequestStore'
import BaseFileUpload from '@/components/BaseFileUpload.vue'

const store = useAppraisalRequestStore()

const open = computed(() => store.wizard.open)
const mode = computed(() => store.wizard.mode)
const editingId = computed(() => store.wizard.editingId)

const step = ref(1)

const emptyDraft = () => ({
  client_id: null,
  nama: '',
  jenis: 'tanah',
  luas_tanah: '',
  alamat_lengkap: '',
  documents: { certificate: null, pbb: null, imb: null },
  photos: { akses_jalan: [], depan: [], dalam: [] },
})

const draft = reactive(emptyDraft())

watch(open, (v) => {
  if (!v) return
  step.value = 1

  const src = mode.value === 'edit' ? store.getAssetById(editingId.value) : null
  const base = src ? structuredClone(src) : emptyDraft()

  Object.keys(draft).forEach(k => delete draft[k])
  Object.assign(draft, base)
})

const hasBuilding = computed(() => draft.jenis === 'tanah_bangunan')

function next() {
  step.value = Math.min(step.value + 1, 5)
}
function back() {
  step.value = Math.max(step.value - 1, 1)
}
function close() {
  store.closeWizard()
}
function save() {
  store.saveAsset(structuredClone(draft))
}
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-3xl overflow-hidden">
      <div class="p-4 border-b flex justify-between">
        <strong>{{ mode === 'edit' ? 'Edit Aset' : 'Tambah Aset' }}</strong>
        <button @click="close">Tutup</button>
      </div>

      <div class="px-4 py-2 border-b text-sm">Step {{ step }} / 5</div>

      <div class="p-4 space-y-4 max-h-[70vh] overflow-y-auto">
        <div v-if="step === 1" class="space-y-3">
          <input v-model="draft.nama" placeholder="Nama Aset" class="w-full border rounded px-3 py-2" />
          <select v-model="draft.jenis" class="w-full border rounded px-3 py-2">
            <option value="tanah">Tanah</option>
            <option value="tanah_bangunan">Tanah + Bangunan</option>
          </select>
          <input v-model="draft.luas_tanah" type="number" placeholder="Luas Tanah" class="w-full border rounded px-3 py-2" />
        </div>

        <div v-if="step === 3" class="space-y-3">
          <BaseFileUpload v-model="draft.documents.certificate" label="Sertifikat" />
          <BaseFileUpload v-model="draft.documents.pbb" label="PBB" />
          <BaseFileUpload v-if="hasBuilding" v-model="draft.documents.imb" label="IMB" />
        </div>

        <div v-if="step === 4" class="space-y-3">
          <BaseFileUpload v-model="draft.photos.akses_jalan" multiple label="Akses Jalan" />
          <BaseFileUpload v-model="draft.photos.depan" multiple label="Depan" />
          <BaseFileUpload v-model="draft.photos.dalam" multiple label="Dalam" />
        </div>

        <div v-if="step === 5">
          <pre class="text-xs bg-gray-50 p-3 rounded">{{ draft }}</pre>
        </div>
      </div>

      <div class="p-4 border-t flex justify-between">
        <button @click="step === 1 ? close() : back()">Kembali</button>
        <button v-if="step < 5" class="bg-black text-white px-4 py-2 rounded" @click="next">Lanjut</button>
        <button v-else class="bg-black text-white px-4 py-2 rounded" @click="save">Simpan Aset</button>
      </div>
    </div>
  </div>
</template>
