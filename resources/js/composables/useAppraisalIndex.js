import { ref, computed, watch } from "vue";
import { router } from "@inertiajs/vue3";

export function useAppraisalIndex(props) {
  const searchQuery = ref(props.filters?.q ?? "");
  const statusFilter = ref(props.filters?.status ?? "all");

  const rows = computed(() => props.appraisals?.data ?? []);
  const links = computed(() => props.appraisals?.links ?? []);
  const meta = computed(() => props.appraisals?.meta ?? null);

  const hasActiveFilters = computed(() => {
    return Boolean(searchQuery.value) || statusFilter.value !== "all";
  });

  const resetFilters = () => {
    searchQuery.value = "";
    statusFilter.value = "all";
  };

  let t = null;
  watch([searchQuery, statusFilter], () => {
    clearTimeout(t);
    t = setTimeout(() => {
      router.get(
        route("appraisal.index"),
        {
          q: searchQuery.value || undefined,
          status: statusFilter.value !== "all" ? statusFilter.value : undefined,
        },
        { preserveState: true, replace: true }
      );
    }, 300);
  });

  const goTo = (url) => {
    if (!url) return;
    router.visit(url, { preserveState: true });
  };

  return {
    searchQuery,
    statusFilter,
    rows,
    links,
    meta,
    goTo,
    resetFilters,
    hasActiveFilters,
  };
}
