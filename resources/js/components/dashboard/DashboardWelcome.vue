<script setup>
defineProps({
  name: {
    type: String,
    default: "",
  },
});
</script>

<template>
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">
      Selamat Datang, {{ name || "Pengguna" }}
    </h1>
    <p class="text-slate-600 mt-1">
      Berikut ringkasan permohonan penilaian properti Anda
    </p>
  </div>
</template>
