<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import DashboardLayout from "@/layouts/UserDashboardLayout.vue";
import { useAppraisalIndex } from "@/composables/useAppraisalIndex";
import { useAppraisalStatus } from "@/composables/useAppraisalStatus";
import AppraisalHeader from "@/components/appraisal/AppraisalHeader.vue";
import AppraisalStatsGrid from "@/components/appraisal/AppraisalStatsGrid.vue";
import AppraisalFilters from "@/components/appraisal/AppraisalFilters.vue";
import AppraisalEmptyState from "@/components/appraisal/AppraisalEmptyState.vue";
import AppraisalTable from "@/components/appraisal/AppraisalTable.vue";
import AppraisalCardsMobile from "@/components/appraisal/AppraisalCardsMobile.vue";

const props = defineProps({
  appraisals: { type: Object, required: true },
  statsCards: {
    type: Array,
    default: () => [],
  },
  filters: { type: Object, default: () => ({ q: "", status: "all" }) },
});

const { searchQuery, statusFilter, rows, links, goTo, resetFilters, hasActiveFilters } =
  useAppraisalIndex(props);

// Use status helper composable
const { getStatusConfig, statusFilterOptions } = useAppraisalStatus();

const goToCreate = () => router.get("/buat-permohonan");

const viewDetail = (id) => {
  router.get(`/permohonan-penilaian/${id}`);
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
};

const formatDateRelative = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  const now = new Date();
  const diffTime = Math.abs(now - date);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays === 0) return "Hari ini";
  if (diffDays === 1) return "Kemarin";
  if (diffDays < 7) return `${diffDays} hari lalu`;
  if (diffDays < 30) return `${Math.floor(diffDays / 7)} minggu lalu`;
  return formatDate(dateString);
};

// data table / cards
const filteredAppraisals = computed(() => rows.value);

// pagination convenience
const prevLink = computed(
  () => links.value.find((l) => l.label?.toLowerCase().includes("previous")) ?? null
);
const nextLink = computed(
  () => links.value.find((l) => l.label?.toLowerCase().includes("next")) ?? null
);

// Stats cards configuration
const statsCards = computed(() => {
  props.statsCards?.length ? props.statsCards : []
});
</script>

<template>
  <DashboardLayout>
    <template #title>Daftar Permohonan Penilaian</template>

    <div class="space-y-6">
      <AppraisalHeader :on-create="goToCreate" />

      <AppraisalStatsGrid :stats-cards="statsCards" />

      <AppraisalFilters
        v-model:searchQuery="searchQuery"
        v-model:statusFilter="statusFilter"
        :status-filter-options="statusFilterOptions"
        :has-active-filters="hasActiveFilters"
        :reset-filters="resetFilters"
        :get-status-config="getStatusConfig"
      />

      <AppraisalEmptyState
        v-if="filteredAppraisals.length === 0"
        :has-active-filters="hasActiveFilters"
        :on-reset-filters="resetFilters"
        :on-create="goToCreate"
      />

      <AppraisalTable
        v-if="filteredAppraisals.length > 0"
        :items="filteredAppraisals"
        :get-status-config="getStatusConfig"
        :format-date="formatDate"
        :format-date-relative="formatDateRelative"
        :prev-link="prevLink"
        :next-link="nextLink"
        :on-view-detail="viewDetail"
        :on-page="goTo"
      />

      <AppraisalCardsMobile
        v-if="filteredAppraisals.length > 0"
        :items="filteredAppraisals"
        :get-status-config="getStatusConfig"
        :format-date="formatDate"
        :format-date-relative="formatDateRelative"
        :prev-link="prevLink"
        :next-link="nextLink"
        :on-view-detail="viewDetail"
        :on-page="goTo"
      />
    </div>
  </DashboardLayout>
</template>
