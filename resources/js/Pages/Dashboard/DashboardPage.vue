<script setup>
import { computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
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

const newRequest = () => {
  if (profileCompletionAlert.value?.action_url) {
    router.visit(profileCompletionAlert.value.action_url);
    return;
  }

  try {
    router.visit(route("appraisal.create"));
  } catch (_) {
    router.visit("/buat-permohonan");
  }
};

const requestList = () => {
  try {
    router.visit(route("appraisal.list"));
  } catch (_) {
    router.visit("/permohonan-penilaian");
  }
};

const viewDetail = (request) => {
  const url = typeof request === "object" ? request?.detail_url : null;
  const id = typeof request === "object" ? request?.id : request;

  if (url) {
    router.visit(url);
    return;
  }

  router.visit(`/permohonan-penilaian/${id}`);
};

const viewTracking = (request) => {
  if (request?.tracking_url) {
    router.visit(request.tracking_url);
    return;
  }

  if (request?.id) {
    router.visit(`/permohonan-penilaian/${request.id}/tracking`);
  }
};

const runPrimaryAction = (request) => {
  const action = request?.progress_summary?.primary_action;
  if (!action?.url) return;

  if (action.external) {
    window.open(action.url, "_blank", "noreferrer");
    return;
  }

  router.visit(action.url);
};

const openActionList = (item) => {
  if (item?.url) {
    router.visit(item.url);
  }
};

const goProfile = () => {
  if (profileCompletionAlert.value?.action_url) {
    router.visit(profileCompletionAlert.value.action_url);
    return;
  }

  try {
    router.visit(route("profile.edit"));
  } catch (_) {
    router.visit("/profile");
  }
};
</script>

<template>
  <DashboardLayout>
    <template #title>Dashboard</template>

    <div class="mx-auto flex w-full max-w-[1500px] flex-col gap-8">
      <DashboardWelcome :name="userName" />

      <section
        v-if="profileCompletionAlert"
        class="flex flex-col gap-3 rounded-[1.5rem] border border-amber-200 bg-amber-50 px-5 py-4 text-amber-950 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="space-y-1">
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-700">Alert Sistem</p>
          <p class="text-sm font-medium">{{ profileCompletionAlert.message }}</p>
        </div>
        <button
          class="inline-flex items-center justify-center rounded-full bg-white px-4 py-2 text-sm font-medium text-amber-900 transition-colors hover:bg-amber-100"
          @click="goProfile"
        >
          {{ profileCompletionAlert.action_label }}
        </button>
      </section>

      <div class="grid gap-8 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.85fr)]">
        <div class="space-y-8">
          <DashboardFeaturedRequest
            :featured-request="featuredRequest"
            :on-new-request="newRequest"
            :on-open-detail="viewDetail"
            :on-open-tracking="viewTracking"
            :on-run-primary-action="runPrimaryAction"
          />

          <DashboardStatsGrid :stats="stats" />

          <RecentRequestsCard
            :recent-requests="recentRequests"
            :on-request-list="requestList"
            :on-new-request="newRequest"
            :on-view-detail="viewDetail"
          />
        </div>

        <div class="space-y-8">
          <DashboardActionCenter
            :action-center="actionCenter"
            :on-open-action="openActionList"
          />
          <QuickActionsCard
            :on-new-request="newRequest"
            :on-request-list="requestList"
          />
          <DashboardInfoCard />
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
