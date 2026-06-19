<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import {
  AlertTriangle,
  ArrowRight,
  CheckCircle2,
  Clock3,
  CreditCard,
  FileCheck2,
  FileText,
  FileWarning,
  HandCoins,
  ListChecks,
  Plus,
  UserRound,
} from "lucide-vue-next";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";

const page = usePage();

const stats = computed(
  () =>
    page.props.stats ?? {
      total_requests: 0,
      in_progress: 0,
      completed: 0,
      need_revision: 0,
    }
);

const recentRequests = computed(() => page.props.recentRequests ?? []);
const actionCenter = computed(() => page.props.actionCenter ?? []);
const profileCompletionAlert = computed(() => page.props.profileCompletionAlert ?? null);
const userName = computed(() => page.props.auth?.user?.name ?? "Pengguna");
const firstName = computed(() => String(userName.value).trim().split(" ")[0] || "Pengguna");

const profileHref = computed(() => {
  if (profileCompletionAlert.value?.action_url) return profileCompletionAlert.value.action_url;

  try {
    return route("profile.edit");
  } catch (_) {
    return "/profile";
  }
});

const newRequestHref = computed(() => {
  if (profileCompletionAlert.value?.action_url) return profileCompletionAlert.value.action_url;

  try {
    return route("appraisal.create");
  } catch (_) {
    return "/buat-permohonan";
  }
});

const requestListHref = computed(() => {
  try {
    return route("appraisal.list");
  } catch (_) {
    return "/permohonan-penilaian";
  }
});

const statCards = computed(() => [
  {
    label: "Total Permohonan",
    value: stats.value.total_requests ?? 0,
    note: "Semua pengajuan",
    icon: FileText,
    class: "text-slate-700",
  },
  {
    label: "Dalam Proses",
    value: stats.value.in_progress ?? 0,
    note: "Sedang ditangani",
    icon: Clock3,
    class: "text-amber-700",
  },
  {
    label: "Selesai",
    value: stats.value.completed ?? 0,
    note: "Laporan final",
    icon: CheckCircle2,
    class: "text-emerald-700",
  },
  {
    label: "Perlu Revisi",
    value: stats.value.need_revision ?? 0,
    note: "Butuh perbaikan",
    icon: FileWarning,
    class: "text-rose-700",
  },
]);

const actionableItems = computed(() => (actionCenter.value || []).filter((item) => Number(item.count || 0) > 0));

const iconForAction = (key) => {
  switch (key) {
    case "need_revision":
      return FileWarning;
    case "offer_sent":
      return HandCoins;
    case "waiting_signature":
      return FileCheck2;
    case "contract_signed":
      return CreditCard;
    default:
      return Clock3;
  }
};

const actionToneClass = (tone) => {
  switch (tone) {
    case "danger":
      return "border-rose-200 bg-rose-50 text-rose-900";
    case "warning":
      return "border-amber-200 bg-amber-50 text-amber-950";
    case "info":
      return "border-sky-200 bg-sky-50 text-sky-950";
    default:
      return "border-slate-200 bg-slate-50 text-slate-900";
  }
};

const statusClass = (color) => {
  switch (color) {
    case "success":
      return "border-emerald-200 bg-emerald-50 text-emerald-700";
    case "warning":
      return "border-amber-200 bg-amber-50 text-amber-700";
    case "info":
      return "border-sky-200 bg-sky-50 text-sky-700";
    case "danger":
      return "border-rose-200 bg-rose-50 text-rose-700";
    default:
      return "border-slate-200 bg-slate-50 text-slate-600";
  }
};
</script>

<template>
  <DashboardLayout>
    <template #title>Dashboard</template>

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 px-0 lg:gap-6">
      <header class="flex flex-col gap-4 rounded-[18px] border border-slate-200 bg-white px-5 py-5 shadow-xs sm:flex-row sm:items-center sm:justify-between lg:px-6">
        <div class="min-w-0">
          <p class="text-sm font-medium text-slate-500">Halo, {{ firstName }}</p>
          <h1 class="mt-1 text-3xl font-semibold text-balance text-slate-950">Dashboard</h1>
          <p class="mt-1 text-sm text-pretty text-slate-600">
            Pantau permohonan penilaian, dokumen, dan tindakan lanjutan.
          </p>
        </div>

        <Link
          :href="newRequestHref"
          class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/20 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
        >
          <Plus class="size-4" />
          Ajukan Permohonan
        </Link>
      </header>

      <section
        v-if="profileCompletionAlert"
        class="flex flex-col gap-3 rounded-[14px] border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950 shadow-xs sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex min-w-0 items-start gap-3">
          <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-700" />
          <p class="text-sm font-medium text-pretty">
            Profil billing belum lengkap. Lengkapi data billing sebelum mengajukan permohonan baru.
          </p>
        </div>
        <Link
          :href="profileHref"
          class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-900 transition-colors hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-900/20 focus-visible:ring-offset-2 focus-visible:ring-offset-amber-50"
        >
          Lengkapi Profil
        </Link>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="card in statCards"
          :key="card.label"
          class="min-h-[96px] rounded-[16px] border border-slate-200 bg-white px-4 py-4 shadow-xs"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-xs font-semibold text-slate-500">{{ card.label }}</p>
              <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-950">{{ card.value }}</p>
            </div>
            <component :is="card.icon" class="size-4 shrink-0" :class="card.class" />
          </div>
          <p class="mt-2 text-xs text-slate-500">{{ card.note }}</p>
        </article>
      </section>

      <section class="grid gap-5 xl:grid-cols-12">
        <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs xl:col-span-8">
          <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
            <div>
              <h2 class="text-lg font-semibold text-slate-950">Permohonan Terbaru</h2>
              <p class="mt-1 text-sm text-slate-500">Daftar ringkas permohonan penilaian terakhir.</p>
            </div>
            <Link
              :href="requestListHref"
              class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
            >
              Lihat Semua
              <ArrowRight class="size-4" />
            </Link>
          </div>

          <div v-if="recentRequests.length === 0" class="flex max-h-[300px] min-h-[240px] items-center justify-center px-5 py-8">
            <div class="max-w-md text-center">
              <div class="mx-auto flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50">
                <FileText class="size-5 text-slate-500" />
              </div>
              <h3 class="mt-4 text-base font-semibold text-slate-950">Belum ada permohonan</h3>
              <p class="mt-2 text-sm leading-6 text-pretty text-slate-500">
                Permohonan aktif, dokumen, jadwal survey, dan status laporan akan muncul di sini.
              </p>
              <Link
                :href="newRequestHref"
                class="mt-4 inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/20 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
              >
                <Plus class="size-4" />
                Ajukan Permohonan Pertama
              </Link>
            </div>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
              <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                <tr>
                  <th scope="col" class="px-5 py-3 lg:px-6">Nomor</th>
                  <th scope="col" class="px-5 py-3">Objek</th>
                  <th scope="col" class="px-5 py-3">Status</th>
                  <th scope="col" class="px-5 py-3">Tanggal</th>
                  <th scope="col" class="px-5 py-3 text-right lg:px-6">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr
                  v-for="request in recentRequests"
                  :key="request.id"
                  class="transition-colors hover:bg-slate-50"
                >
                  <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-semibold text-slate-900 lg:px-6">
                    {{ request.code }}
                  </td>
                  <td class="min-w-[260px] px-5 py-4">
                    <p class="line-clamp-1 font-medium text-slate-900">{{ request.property }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ request.asset_count }} aset</p>
                  </td>
                  <td class="whitespace-nowrap px-5 py-4">
                    <span
                      class="inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium"
                      :class="statusClass(request.status_color)"
                    >
                      {{ request.status }}
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                    {{ request.created_at }}
                  </td>
                  <td class="whitespace-nowrap px-5 py-4 text-right lg:px-6">
                    <Link
                      :href="request.detail_url || `/permohonan-penilaian/${request.id}`"
                      class="inline-flex min-h-9 items-center justify-center rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                    >
                      Detail
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <aside class="space-y-5 xl:col-span-4">
          <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-lg font-semibold text-slate-950">Tindakan Menunggu</h2>
              <p class="mt-1 text-sm text-slate-500">Item yang membutuhkan respons Anda.</p>
            </div>

            <div class="p-5">
              <p v-if="!actionableItems.length" class="text-sm leading-6 text-slate-500">
                Tidak ada tindakan yang menunggu saat ini.
              </p>

              <div v-else class="space-y-3">
                <Link
                  v-for="item in actionableItems"
                  :key="item.key"
                  :href="item.url || requestListHref"
                  class="group block rounded-xl border px-4 py-3 transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950/15 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                  :class="actionToneClass(item.tone)"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                      <component :is="iconForAction(item.key)" class="mt-0.5 size-4 shrink-0" />
                      <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-950">{{ item.label }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ item.count }} permohonan</p>
                      </div>
                    </div>
                    <ArrowRight class="size-4 shrink-0 text-slate-500 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none motion-reduce:transform-none" />
                  </div>
                </Link>
              </div>
            </div>
          </section>

          <section class="rounded-[18px] border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-5 py-4">
              <h2 class="text-lg font-semibold text-slate-950">Akses Cepat</h2>
            </div>
            <nav class="divide-y divide-slate-200" aria-label="Akses cepat dashboard">
              <Link
                :href="newRequestHref"
                class="flex min-h-12 items-center justify-between gap-3 px-5 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-950/15"
              >
                <span class="inline-flex items-center gap-2"><Plus class="size-4 text-slate-500" />Ajukan Permohonan</span>
                <ArrowRight class="size-4 text-slate-400" />
              </Link>
              <Link
                :href="requestListHref"
                class="flex min-h-12 items-center justify-between gap-3 px-5 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-950/15"
              >
                <span class="inline-flex items-center gap-2"><ListChecks class="size-4 text-slate-500" />Lihat Semua Permohonan</span>
                <ArrowRight class="size-4 text-slate-400" />
              </Link>
              <Link
                :href="profileHref"
                class="flex min-h-12 items-center justify-between gap-3 px-5 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-950/15"
              >
                <span class="inline-flex items-center gap-2"><UserRound class="size-4 text-slate-500" />Profil Saya</span>
                <ArrowRight class="size-4 text-slate-400" />
              </Link>
            </nav>
          </section>
        </aside>
      </section>
    </div>
  </DashboardLayout>
</template>
