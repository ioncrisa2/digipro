<script setup>
import { Link } from "@inertiajs/vue3";
import { Menu, ChevronDown } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuLabel,
} from "@/components/ui/dropdown-menu";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import UserNotificationDropdown from "@/components/user-dashboard/UserNotificationDropdown.vue";

defineProps({
  sidebarCollapsed: {
    type: Boolean,
    required: true,
  },
  toggleSidebar: {
    type: Function,
    required: true,
  },
  toggleCollapse: {
    type: Function,
    required: true,
  },
  unreadCount: {
    type: Number,
    default: 0,
  },
  notifications: {
    type: Array,
    default: () => [],
  },
  markAllAsRead: {
    type: Function,
    required: true,
  },
  markAsRead: {
    type: Function,
    required: true,
  },
  user: {
    type: Object,
    required: true,
  },
  userInitials: {
    type: String,
    required: true,
  },
  avatarUrl: {
    type: String,
    required: true,
  },
  onLogout: {
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
</script>

<template>
  <header class="flex h-16 items-center gap-3 border-b border-[var(--border,#e2e8f0)] bg-[var(--customer-surface,#ffffff)] px-4 lg:px-6">
    <Button variant="ghost" size="icon" class="lg:hidden" @click="toggleSidebar">
      <Menu class="h-5 w-5" />
    </Button>

    <div class="customer-display flex flex-1 items-center gap-2 text-base font-semibold text-[var(--customer-ink,#0f172a)]">
      <Button
        variant="ghost"
        size="icon"
        class="hidden lg:flex"
        @click="toggleCollapse"
        :title="sidebarCollapsed ? 'Perluas Sidebar' : 'Perkecil Sidebar'"
      >
        <Menu class="h-5 w-5" />
      </Button>
      <slot name="title">Dashboard</slot>
    </div>

    <UserNotificationDropdown
      :unread-count="unreadCount"
      :notifications="notifications"
      :mark-all-as-read="markAllAsRead"
      :mark-as-read="markAsRead"
    />

    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <button
          type="button"
          class="flex items-center gap-2 rounded-full border border-[var(--border,#e2e8f0)] bg-[var(--customer-surface,#ffffff)] px-2 py-1 transition-colors hover:bg-[var(--customer-surface-muted,#f8fafc)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/20 focus-visible:ring-offset-2"
        >
          <Avatar class="h-8 w-8">
            <AvatarImage :src="avatarUrl" />
            <AvatarFallback class="bg-[var(--customer-trust,#0f172a)] text-xs text-white">
              {{ userInitials }}
            </AvatarFallback>
          </Avatar>
          <div class="hidden sm:flex flex-col text-left">
            <span class="text-xs font-semibold text-slate-900 leading-tight">
              {{ user.name }}
            </span>
            <span class="text-[11px] text-slate-500 leading-tight">
              {{ portalLabel }}
            </span>
          </div>
          <ChevronDown class="h-4 w-4 text-slate-500 hidden sm:block" />
        </button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" class="w-56">
        <DropdownMenuLabel>
          <div class="flex flex-col">
            <span class="font-semibold text-sm">{{ user.name }}</span>
            <span class="text-xs text-slate-500 truncate">
              {{ user.email }}
            </span>
          </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />

        <DropdownMenuItem as-child>
          <Link :href="profileHref" class="w-full flex items-center justify-between">
            <span>Profil Saya</span>
          </Link>
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        <DropdownMenuItem class="text-red-600 cursor-pointer" @click="onLogout">
          Keluar
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </header>
</template>
