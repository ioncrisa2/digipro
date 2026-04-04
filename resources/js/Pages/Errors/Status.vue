<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";

const props = defineProps({
  status: {
    type: Number,
    required: true,
  },
});

const page = usePage();

const statuses = {
  403: {
    eyebrow: "Akses Ditahan",
    title: "Halaman ini tidak bisa Anda buka.",
    description:
      "Permintaan Anda diterima, tetapi sistem menahan akses karena izin yang dibutuhkan tidak tersedia.",
    tone: "amber",
  },
  404: {
    eyebrow: "Rute Tidak Ditemukan",
    title: "Halaman yang Anda cari tidak ada.",
    description:
      "Tautan bisa saja sudah berubah, sudah tidak aktif, atau alamat yang dibuka tidak lengkap.",
    tone: "sky",
  },
  419: {
    eyebrow: "Sesi Kedaluwarsa",
    title: "Sesi Anda sudah berakhir.",
    description:
      "Untuk keamanan, halaman ini perlu dimuat ulang atau Anda perlu mengulangi aksi terakhir dari awal.",
    tone: "emerald",
  },
  429: {
    eyebrow: "Terlalu Banyak Permintaan",
    title: "Sistem meminta Anda menunggu sebentar.",
    description:
      "Aktivitas yang sama dikirim terlalu sering dalam waktu singkat. Coba lagi setelah jeda singkat.",
    tone: "amber",
  },
  500: {
    eyebrow: "Gangguan Server",
    title: "Terjadi kendala di sisi aplikasi.",
    description:
      "Permintaan Anda tidak selesai diproses. Tim kami perlu memeriksa respons server ini.",
    tone: "rose",
  },
  503: {
    eyebrow: "Layanan Sementara Tidak Tersedia",
    title: "DigiPro sedang menyiapkan sistem.",
    description:
      "Layanan sedang dalam proses pemeliharaan atau penyesuaian sementara. Silakan coba lagi beberapa saat lagi.",
    tone: "stone",
  },
  505: {
    eyebrow: "Versi HTTP Tidak Didukung",
    title: "Permintaan ini memakai protokol yang tidak didukung.",
    description:
      "Browser atau jaringan mengirimkan versi permintaan yang tidak dapat diproses oleh aplikasi ini.",
    tone: "stone",
  },
};

const meta = computed(() => {
  return (
    statuses[props.status] ?? {
      eyebrow: "Terjadi Gangguan",
      title: "Halaman ini tidak bisa ditampilkan.",
      description:
        "Sistem mengembalikan respons yang belum bisa ditangani oleh antarmuka aplikasi.",
      tone: "stone",
    }
  );
});

const homeUrl = computed(() => {
  const user = page.props.auth?.user;

  if (!user) {
    return route("landing");
  }

  if (user.is_admin) {
    return route("admin.dashboard");
  }

  if (user.is_reviewer) {
    return route("reviewer.dashboard");
  }

  return route("dashboard");
});

const homeLabel = computed(() => {
  const user = page.props.auth?.user;

  if (!user) {
    return "Kembali ke Beranda";
  }

  if (user.is_admin) {
    return "Buka Dashboard Admin";
  }

  if (user.is_reviewer) {
    return "Buka Workspace Reviewer";
  }

  return "Buka Dashboard";
});

const accentClasses = computed(() => {
  return {
    emerald: "from-emerald-500/22 via-emerald-400/8 to-transparent text-emerald-700 ring-emerald-500/20",
    amber: "from-amber-500/22 via-amber-400/8 to-transparent text-amber-700 ring-amber-500/20",
    sky: "from-sky-500/22 via-sky-400/8 to-transparent text-sky-700 ring-sky-500/20",
    rose: "from-rose-500/22 via-rose-400/8 to-transparent text-rose-700 ring-rose-500/20",
    stone: "from-stone-500/18 via-stone-400/8 to-transparent text-stone-700 ring-stone-500/20",
  }[meta.value.tone];
});

const statusLabel = computed(() => String(props.status).padStart(3, "0"));

const goBack = () => {
  window.history.back();
};
</script>

<template>
  <Head :title="`${statusLabel} • DigiPro`" />

  <div
    class="relative min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.12),_transparent_30%),linear-gradient(180deg,#f7f6f2_0%,#efece5_100%)] text-slate-900"
  >
    <div class="absolute inset-0 bg-[linear-gradient(rgba(15,23,42,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(15,23,42,0.03)_1px,transparent_1px)] bg-[size:34px_34px]" />
    <div class="absolute -left-24 top-16 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl" />
    <div class="absolute -right-24 bottom-16 h-72 w-72 rounded-full bg-sky-500/10 blur-3xl" />

    <main class="relative mx-auto flex min-h-screen w-full max-w-7xl items-center px-6 py-10 sm:px-10 lg:px-16">
      <section class="grid w-full gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-end">
        <div class="space-y-8">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-3 rounded-full border border-white/70 bg-white/70 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-600 shadow-sm backdrop-blur">
              <span class="inline-block h-2 w-2 rounded-full bg-emerald-500" />
              DigiPro System Response
            </div>

            <div class="space-y-3">
              <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-500">
                {{ meta.eyebrow }}
              </p>
              <h1 class="max-w-3xl font-['Space_Grotesk'] text-4xl font-semibold leading-tight text-slate-950 sm:text-5xl lg:text-6xl">
                {{ meta.title }}
              </h1>
              <p class="max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                {{ meta.description }}
              </p>
            </div>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row">
            <Link
              :href="homeUrl"
              class="inline-flex items-center justify-center rounded-full bg-slate-950 px-6 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
            >
              {{ homeLabel }}
            </Link>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white/80 px-6 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-white"
              @click="goBack"
            >
              Kembali ke Halaman Sebelumnya
            </button>
          </div>
        </div>

        <div class="lg:justify-self-end">
          <div class="relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/75 p-6 shadow-[0_24px_80px_rgba(15,23,42,0.10)] backdrop-blur sm:p-8">
            <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-br" :class="accentClasses" />
            <div class="relative space-y-8">
              <div class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">
                  Response Code
                </p>
                <div class="font-['Space_Grotesk'] text-7xl font-semibold leading-none text-slate-950 sm:text-8xl">
                  {{ statusLabel }}
                </div>
              </div>

              <div class="grid gap-3 text-sm text-slate-600">
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.26em] text-slate-500">
                    Langkah Cepat
                  </p>
                  <p class="mt-2 leading-6">
                    Periksa URL, muat ulang sesi, atau kembali ke workspace utama untuk melanjutkan proses penilaian.
                  </p>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-white/80 p-4">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.26em] text-slate-500">
                    Catatan
                  </p>
                  <p class="mt-2 leading-6">
                    Jika kendala ini terus muncul, laporkan status code <span class="font-semibold text-slate-900">{{ statusLabel }}</span> ke tim internal DigiPro.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
