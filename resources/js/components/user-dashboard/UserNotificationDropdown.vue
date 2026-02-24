<script setup>
import { Bell } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
} from "@/components/ui/dropdown-menu";

defineProps({
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
});
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="icon" class="relative mr-1">
        <Bell class="h-5 w-5 text-slate-500" />
        <span
          v-if="unreadCount > 0"
          class="absolute -top-1 -right-1 h-4 min-w-4 x-1 rounded-full bg-red-600 text-white text-[10px] flex items-center justify-center"
        >
          {{ unreadCount }}
        </span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-80 p-0">
      <div class="flex items-center justify-between px-4 py-2 border-b">
        <span class="text-sm font-semibold">Notifikasi</span>
        <button v-if="unreadCount > 0" @click="markAllAsRead" class="text-xs text-blue-600 hover:underline">
          Tandai semua dibaca
        </button>
      </div>

      <div v-if="notifications.length" class="max-h-80 overflow-y-auto">
        <div
          v-for="notif in notifications"
          :key="notif.id"
          @click="markAsRead(notif)"
          class="px-4 py-3 text-sm cursor-pointer border-b last:border-b-0 hover:bg-slate-50"
          :class="!notif.read ? 'bg-slate-50' : ''"
        >
          <div class="flex justify-between gap-2">
            <span class="font-medium text-slate-900">
              {{ notif.title }}
            </span>
            <span class="text-[11px] text-slate-400">
              {{ notif.time }}
            </span>
          </div>

          <p class="text-xs text-slate-600 mt-1 line-clamp-2">
            {{ notif.message }}
          </p>
        </div>
      </div>

      <div v-else class="px-4 py-6 text-center text-xs text-slate-500">
        Tidak ada notifikasi
      </div>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
