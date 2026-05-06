<script setup>
import { computed } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import DashboardWelcome from "@/components/dashboard/DashboardWelcome.vue";
import DashboardStatsGrid from "@/components/dashboard/DashboardStatsGrid.vue";
import RecentRequestsCard from "@/components/dashboard/RecentRequestsCard.vue";
import QuickActionsCard from "@/components/dashboard/QuickActionsCard.vue";
import DashboardInfoCard from "@/components/dashboard/DashboardInfoCard.vue";
import DashboardFeaturedRequest from "@/components/dashboard/DashboardFeaturedRequest.vue";
import DashboardActionCenter from "@/components/dashboard/DashboardActionCenter.vue";

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
const featuredRequest = computed(() => page.props.featuredRequest ?? null);
const actionCenter = computed(() => page.props.actionCenter ?? []);
const profileCompletionAlert = computed(() => page.props.profileCompletionAlert ?? null);
const userName = computed(() => page.props.auth?.user?.name ?? "Pengguna");

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

const featuredDetailHref = computed(() => {
  const request = featuredRequest.value;
  if (!request) return null;
  if (request.detail_url) return request.detail_url;
  if (request.id) return `/permohonan-penilaian/${request.id}`;
  return null;
});

const featuredTrackingHref = computed(() => {
  const request = featuredRequest.value;
  if (!request) return null;
  if (request.tracking_url) return request.tracking_url;
  if (request.id) return `/permohonan-penilaian/${request.id}/tracking`;
  return null;
});

const runPrimaryAction = (request) => {
  const action = request?.progress_summary?.primary_action;
  if (!action?.url) return;

  if (action.external) {
    window.open(action.url, "_blank", "noreferrer");
    return;
  }

  router.visit(action.url);
};

</script>

<template>
  <DashboardLayout>
    <template #title>Dashboard</template>

    <div class="mx-auto flex w-full max-w-[1500px] flex-col gap-6">
      <section class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(340px,0.95fr)]">
        <div class="space-y-6">
          <DashboardWelcome :name="userName" />

          <DashboardFeaturedRequest
            :featured-request="featuredRequest"
            :new-request-href="newRequestHref"
            :detail-href="featuredDetailHref"
            :tracking-href="featuredTrackingHref"
            :on-run-primary-action="runPrimaryAction"
          />
        </div>

        <div class="space-y-6">
          <section
            v-if="profileCompletionAlert"
            class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-5 py-4 text-amber-950"
          >
            <div class="space-y-3">
              <div class="space-y-1">
                <p class="text-xs font-semibold uppercase text-amber-700">Alert Sistem</p>
                <p class="text-sm font-medium text-pretty">{{ profileCompletionAlert.message }}</p>
              </div>
              <Link
                :href="profileHref"
                class="inline-flex min-h-11 items-center justify-center rounded-full bg-white px-4 py-2 text-sm font-medium text-amber-900 transition-colors hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-900/20 focus-visible:ring-offset-2 focus-visible:ring-offset-amber-50"
              >
                {{ profileCompletionAlert.action_label }}
              </Link>
            </div>
          </section>

          <DashboardStatsGrid :stats="stats" compact />

          <DashboardActionCenter
            :action-center="actionCenter"
          />

          <QuickActionsCard
            :new-request-href="newRequestHref"
            :request-list-href="requestListHref"
          />

          <DashboardInfoCard />
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(340px,0.8fr)]">
        <div class="space-y-6">
          <RecentRequestsCard
            :recent-requests="recentRequests"
            :request-list-href="requestListHref"
            :new-request-href="newRequestHref"
          />
        </div>

        <div class="space-y-6">
          <section class="rounded-[2rem] border border-slate-200 bg-white p-6">
            <div class="space-y-2">
              <p class="text-xs font-semibold uppercase text-slate-500">Ringkasan Operasional</p>
              <h2 class="text-lg font-semibold text-slate-950">Arah tindakan Anda hari ini</h2>
              <p class="text-pretty text-sm leading-6 text-slate-600">
                Fokus utama tetap ada di permohonan prioritas, sementara area ini merangkum volume kerja, permintaan aktif, dan langkah yang paling cepat untuk dilanjutkan.
              </p>
            </div>
            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <dt class="text-[11px] font-semibold uppercase text-slate-500">Permohonan aktif</dt>
                <dd class="mt-2 text-3xl font-semibold tabular-nums text-slate-950">{{ stats.total_requests ?? 0 }}</dd>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <dt class="text-[11px] font-semibold uppercase text-slate-500">Sedang proses</dt>
                <dd class="mt-2 text-3xl font-semibold tabular-nums text-slate-950">{{ stats.in_progress ?? 0 }}</dd>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <dt class="text-[11px] font-semibold uppercase text-slate-500">Butuh respon</dt>
                <dd class="mt-2 text-3xl font-semibold tabular-nums text-slate-950">{{ stats.need_revision ?? 0 }}</dd>
              </div>
            </dl>
          </section>
        </div>
      </section>
    </div>
  </DashboardLayout>
</template>
