<script setup>
import { computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import DashboardWelcome from "@/components/dashboard/DashboardWelcome.vue";
import DashboardStatsGrid from "@/components/dashboard/DashboardStatsGrid.vue";
import RecentRequestsCard from "@/components/dashboard/RecentRequestsCard.vue";
import QuickActionsCard from "@/components/dashboard/QuickActionsCard.vue";
import ProgressOverviewCard from "@/components/dashboard/ProgressOverviewCard.vue";
import DashboardInfoCard from "@/components/dashboard/DashboardInfoCard.vue";

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
const userName = computed(() => page.props.auth?.user?.name ?? "Pengguna");

const newRequest = () => {
  router.visit("/permohonan-penilaian");
};

const requestList = () => {
  router.visit("/permohonan-penilaian");
};

const viewDetail = (id) => {
  router.visit(`/permohonan-penilaian/${id}`);
};
</script>

<template>
  <DashboardLayout>
    <template #title>Dashboard</template>

    <DashboardWelcome :name="userName" />
    <DashboardStatsGrid :stats="stats" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <RecentRequestsCard
        :recent-requests="recentRequests"
        :on-request-list="requestList"
        :on-new-request="newRequest"
        :on-view-detail="viewDetail"
      />

      <div class="space-y-6">
        <QuickActionsCard
          :on-new-request="newRequest"
          :on-request-list="requestList"
        />
        <ProgressOverviewCard v-if="stats.total_requests > 0" :stats="stats" />
        <DashboardInfoCard />
      </div>
    </div>
  </DashboardLayout>
</template>
