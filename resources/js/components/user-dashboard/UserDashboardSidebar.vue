<script setup>
import { Link } from "@inertiajs/vue3";
import { User, X } from "lucide-vue-next";

defineProps({
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
});

const year = new Date().getFullYear();
</script>

<template>
  <aside
    class="bg-slate-900 text-slate-100 shrink-0 flex flex-col transition-all duration-200 fixed inset-y-0 left-0 z-40 lg:static"
    :class="[
      sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      sidebarCollapsed ? 'lg:w-20' : 'lg:w-64',
      sidebarCollapsed ? 'w-20' : 'w-64',
    ]"
  >
    <div class="h-16 flex items-center px-4 border-b border-slate-800">
      <div
        class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-800 border border-slate-700"
        :class="sidebarCollapsed ? 'mx-auto' : 'mr-3'"
      >
        <span class="text-sm font-semibold">DG</span>
      </div>
      <div v-if="!sidebarCollapsed" class="flex flex-col leading-tight">
        <span class="font-semibold text-sm tracking-tight">DIGIPRO</span>
        <span class="text-[11px] text-slate-400">User Portal</span>
      </div>
      <button
        type="button"
        class="ml-auto inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-300 hover:bg-slate-800 hover:text-white lg:hidden"
        aria-label="Tutup menu"
        @click="closeSidebar"
      >
        <X class="h-5 w-5" />
      </button>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 space-y-1">
      <Link
        v-for="item in navItems"
        :key="item.routeName"
        :href="route(item.routeName)"
        :title="sidebarCollapsed ? item.label : ''"
        class="flex items-center text-sm rounded-md mx-2 transition-colors"
        :class="[
          sidebarCollapsed ? 'justify-center px-2 py-2' : 'px-4 py-2',
          isActive(item)
            ? 'bg-slate-800 text-white'
            : 'text-slate-300 hover:bg-slate-800/60 hover:text-white',
        ]"
      >
        <component
          :is="item.icon"
          :class="[
            'h-5 w-5',
            sidebarCollapsed ? '' : 'mr-3',
            isActive(item) ? 'text-white' : 'text-slate-400',
          ]"
        />
        <span v-if="!sidebarCollapsed">{{ item.label }}</span>
      </Link>
    </nav>

    <div class="px-3 py-3 border-t border-slate-800">
      <Link
        :href="route('profile.edit')"
        :title="sidebarCollapsed ? 'Profil Saya' : ''"
        class="flex items-center rounded-md text-sm transition-colors"
        :class="[
          sidebarCollapsed ? 'justify-center px-2 py-2' : 'gap-3 px-3 py-2',
          isProfileActive
            ? 'bg-slate-800 text-white'
            : 'text-slate-300 hover:bg-slate-800/60 hover:text-white',
        ]"
      >
        <div class="h-8 w-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center">
          <User class="h-4 w-4" :class="isProfileActive ? 'text-white' : 'text-slate-300'" />
        </div>
        <div v-if="!sidebarCollapsed" class="min-w-0">
          <div class="truncate font-medium">{{ user.name || "Profil Saya" }}</div>
          <div class="text-[11px] text-slate-400 truncate">Profil Saya</div>
        </div>
      </Link>
    </div>

    <div v-if="!sidebarCollapsed" class="px-4 py-3 border-t border-slate-800 text-[11px] text-slate-500">
      DIGIPRO (c) {{ year }}
    </div>
  </aside>
</template>
