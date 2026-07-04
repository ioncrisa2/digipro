<script setup>
import { ref, watch } from "vue";
import { Link } from "@inertiajs/vue3";
import { ChevronDown, ChevronRight, User, X } from "lucide-vue-next";

const props = defineProps({
  navItems: {
    type: Array,
    required: true,
  },
  sidebarOpen: {
    type: Boolean,
    required: true,
  },
  sidebarCollapsed: {
    type: Boolean,
    required: true,
  },
  isActive: {
    type: Function,
    required: true,
  },
  openGroups: {
    type: Object,
    default: () => ({}),
  },
  isProfileActive: {
    type: Boolean,
    required: true,
  },
  user: {
    type: Object,
    required: true,
  },
  closeSidebar: {
    type: Function,
    required: true,
  },
  profileHref: {
    type: String,
    default: "/profile",
  },
  portalLabel: {
    type: String,
    default: "User Portal",
  },
});

const year = new Date().getFullYear();
const localOpenGroups = ref({ ...(props.openGroups ?? {}) });

const resolveHref = (item) => {
  if (item.href) return item.href;
  return route(item.routeName, item.routeParams ?? {});
};

const toggleGroup = (key) => {
  localOpenGroups.value = {
    ...localOpenGroups.value,
    [key]: !localOpenGroups.value[key],
  };
};

watch(
  () => props.openGroups,
  (value) => {
    localOpenGroups.value = { ...(value ?? {}) };
  },
  { deep: true },
);
</script>

<template>
  <aside
    class="fixed inset-y-0 left-0 z-40 flex shrink-0 flex-col bg-[var(--customer-nav,#0f172a)] text-white transition-[transform] duration-200 motion-reduce:transition-none lg:static"
    :class="[
      sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      sidebarCollapsed ? 'lg:w-20' : 'lg:w-64',
      sidebarCollapsed ? 'w-20' : 'w-64',
    ]"
  >
    <div class="flex h-16 items-center border-b border-white/10 px-4">
      <div
        class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/15 bg-[var(--customer-brand,#1e293b)]"
        :class="sidebarCollapsed ? 'mx-auto' : 'mr-3'"
      >
        <span class="text-sm font-semibold">DG</span>
      </div>
      <div v-if="!sidebarCollapsed" class="flex flex-col leading-tight">
        <span class="font-semibold text-sm">DIGIPRO BY KJPP HJAR</span>
        <span class="text-[11px] text-[var(--customer-nav-muted,#94a3b8)]">{{ portalLabel }}</span>
      </div>
      <button
        type="button"
        class="ml-auto inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-300 hover:bg-slate-800 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/20 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 lg:hidden"
        aria-label="Tutup menu"
        @click="closeSidebar"
      >
        <X class="h-5 w-5" />
      </button>
    </div>

    <nav class="flex-1 overflow-y-auto overscroll-contain py-4 space-y-1">
      <div v-for="item in navItems" :key="item.key ?? item.routeName" class="mx-2">
        <Link
          v-if="!item.subItems?.length || sidebarCollapsed"
          :href="resolveHref(item)"
          :title="sidebarCollapsed ? item.label : ''"
          class="flex items-center text-sm rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/20 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
          :class="[
            sidebarCollapsed ? 'justify-center px-2 py-2' : 'px-4 py-2',
            isActive(item)
              ? 'bg-[var(--customer-brand,#1e293b)] text-white shadow-sm'
              : 'text-[#d8e2e4] hover:bg-white/10 hover:text-white',
          ]"
        >
          <component
            :is="item.icon"
            :class="[
              'h-5 w-5',
              sidebarCollapsed ? '' : 'mr-3',
              isActive(item) ? 'text-white' : 'text-[var(--customer-nav-muted,#94a3b8)]',
            ]"
          />
          <span v-if="!sidebarCollapsed">{{ item.label }}</span>
        </Link>

        <div v-else class="space-y-1">
          <button
            type="button"
            class="flex items-center rounded-md px-4 py-2 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/20 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
            :class="[
              isActive(item)
                ? 'bg-slate-800 text-white'
                : 'text-slate-300 hover:bg-slate-800/60 hover:text-white',
            ]"
            @click="toggleGroup(item.key)"
          >
            <component
              :is="item.icon"
              :class="[
                'mr-3 h-5 w-5',
                isActive(item) ? 'text-white' : 'text-slate-400',
              ]"
            />
            <span class="flex-1">{{ item.label }}</span>
            <component
              :is="localOpenGroups[item.key] ? ChevronDown : ChevronRight"
              class="h-4 w-4"
              :class="isActive(item) ? 'text-white' : 'text-slate-400'"
            />
          </button>

          <div v-if="localOpenGroups[item.key]" class="space-y-1 pl-4">
            <Link
              v-for="subItem in item.subItems"
              :key="subItem.key ?? subItem.routeName"
              :href="resolveHref(subItem)"
              class="flex items-center rounded-md px-4 py-2 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/20 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
              :class="[
                isActive(subItem)
                  ? 'bg-slate-800 text-white'
                  : 'text-slate-400 hover:bg-slate-800/60 hover:text-white',
              ]"
            >
              <component
                v-if="subItem.icon"
                :is="subItem.icon"
                class="mr-3 h-4 w-4"
                :class="isActive(subItem) ? 'text-white' : 'text-slate-500'"
              />
              <span>{{ subItem.label }}</span>
            </Link>
          </div>
        </div>
      </div>
    </nav>

    <div class="border-t border-white/10 px-3 py-3">
      <Link
        :href="profileHref"
        :title="sidebarCollapsed ? 'Profil Saya' : ''"
        class="flex items-center rounded-md text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/20 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
        :class="[
          sidebarCollapsed ? 'justify-center px-2 py-2' : 'gap-3 px-3 py-2',
          isProfileActive
            ? 'bg-[var(--customer-brand,#1e293b)] text-white'
            : 'text-[#d8e2e4] hover:bg-white/10 hover:text-white',
        ]"
      >
        <div class="flex h-8 w-8 items-center justify-center rounded-full border border-white/15 bg-white/10">
          <User class="h-4 w-4" :class="isProfileActive ? 'text-white' : 'text-slate-300'" />
        </div>
        <div v-if="!sidebarCollapsed" class="min-w-0">
          <div class="truncate font-medium">{{ user.name || "Profil Saya" }}</div>
          <div class="truncate text-[11px] text-[var(--customer-nav-muted,#94a3b8)]">Profil Saya</div>
        </div>
      </Link>
    </div>

    <div v-if="!sidebarCollapsed" class="border-t border-white/10 px-4 py-3 text-[11px] text-[var(--customer-nav-muted,#64748b)]">
      DIGIPRO BY KJPP HJAR (c) {{ year }}
    </div>
  </aside>
</template>
