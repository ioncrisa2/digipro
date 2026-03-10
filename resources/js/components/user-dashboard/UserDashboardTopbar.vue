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
  <header class="h-16 bg-white border-b border-slate-200 flex items-center px-4 lg:px-6 gap-3">
    <Button variant="ghost" size="icon" class="lg:hidden" @click="toggleSidebar">
      <Menu class="h-5 w-5" />
    </Button>

    <div class="flex-1 flex items-center gap-2 font-semibold text-slate-900 text-base">
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
          class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2 py-1 hover:bg-slate-50 transition-colors"
        >
          <Avatar class="h-8 w-8">
            <AvatarImage :src="avatarUrl" />
            <AvatarFallback class="bg-slate-900 text-white text-xs">
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
