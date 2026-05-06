<script setup>
defineProps({
  name: {
    type: String,
    default: "",
  },
});
</script>

<template>
  <section class="space-y-2">
    <p class="text-xs font-semibold uppercase text-slate-500">
      Ringkasan Hari Ini
    </p>
    <div class="space-y-1">
      <h1 class="text-balance text-3xl font-semibold text-slate-950">
        Dashboard {{ name || "Pengguna" }}
      </h1>
      <p class="max-w-3xl text-pretty text-sm leading-6 text-slate-600">
        Pantau permohonan yang sedang berjalan, lihat tindakan yang menunggu respon Anda, dan buka daftar request tanpa berpindah-pindah halaman.
      </p>
    </div>
  </section>
</template>
