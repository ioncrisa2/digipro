<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, ClipboardList, Scale } from 'lucide-vue-next';
import { useNotification } from '@/composables/useNotification';
import GlobalDialog from '@/components/GlobalDialog.vue';
import NotificationCenter from '@/components/ui/notification/NotificationCenter.vue';
import UserDashboardSidebar from '@/components/user-dashboard/UserDashboardSidebar.vue';
import UserDashboardTopbar from '@/components/user-dashboard/UserDashboardTopbar.vue';
import UserDashboardFooter from '@/components/user-dashboard/UserDashboardFooter.vue';
import LogoutConfirmDialog from '@/components/user-dashboard/LogoutConfirmDialog.vue';

const props = defineProps({
  title: {
    type: String,
    default: 'Reviewer',
  },
});

const page = usePage();
const { notify } = useNotification();
const user = computed(() => page.props.auth?.user ?? {});
const defaultAvatarUrl = '/images/avatar-default.svg';
const avatarUrl = computed(() => user.value?.avatar_url || defaultAvatarUrl);
const flash = computed(() => page.props.flash ?? {});
const shownFlashFingerprint = ref('');
const reviewerProfileHref = computed(() => {
  try {
    return route('reviewer.profile.edit');
  } catch (_error) {
    return '/reviewer/profile';
  }
});

const navItems = [
  {
    label: 'Dashboard',
    icon: LayoutDashboard,
    routeName: 'reviewer.dashboard',
    activePatterns: ['reviewer.dashboard'],
    pathPrefixes: ['/reviewer'],
  },
  {
    label: 'Review',
    icon: ClipboardList,
    routeName: 'reviewer.reviews.index',
    activePatterns: ['reviewer.reviews.*', 'reviewer.assets.*'],
    pathPrefixes: ['/reviewer/reviews', '/reviewer/assets'],
  },
  {
    label: 'Comparables',
    icon: Scale,
    routeName: 'reviewer.comparables.index',
    activePatterns: ['reviewer.comparables.*'],
    pathPrefixes: ['/reviewer/comparables'],
  },
];

const sidebarOpen = ref(true);
const sidebarCollapsed = ref(false);
const showLogoutDialog = ref(false);
const notifRefreshing = ref(false);
let notifTimer = null;

const handleResize = () => {
  sidebarOpen.value = window.innerWidth >= 1024;
};

onMounted(() => {
  handleResize();
  window.addEventListener('resize', handleResize);

  refreshNotifications();
  notifTimer = setInterval(refreshNotifications, 15000);
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize);
  if (notifTimer) clearInterval(notifTimer);
});

const currentPath = computed(() => {
  const raw = page.url || window.location.pathname || '';
  return raw.split('?')[0];
});

const matchesPathPrefix = (path, prefixes = []) => {
  return prefixes.some((prefix) => path === prefix || path.startsWith(prefix + '/'));
};

const isActive = (item) => {
  try {
    if (item.activePatterns?.length) {
      return item.activePatterns.some((pattern) => route().current(pattern));
    }
    return route().current(item.routeName);
  } catch (_error) {
    if (item.pathPrefixes?.length) {
      return matchesPathPrefix(currentPath.value, item.pathPrefixes);
    }
    return false;
  }
};

const isProfileActive = computed(() => {
  try {
    return route().current('reviewer.profile.*');
  } catch (_error) {
    return matchesPathPrefix(currentPath.value, ['/reviewer/profile']);
  }
});

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value;
};

const closeSidebar = () => {
  if (window.innerWidth < 1024) {
    sidebarOpen.value = false;
  }
};

const toggleCollapse = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value;
};

const userInitials = computed(() => {
  if (!user.value?.name) return 'U';
  return user.value.name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .toUpperCase();
});

const notifications = computed(() => page.props.notifications ?? []);
const unreadCount = computed(() => page.props.unreadCount ?? 0);

const refreshNotifications = () => {
  if (notifRefreshing.value) return;

  notifRefreshing.value = true;
  router.reload({
    only: ['notifications', 'unreadCount'],
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      notifRefreshing.value = false;
    },
  });
};

const markAsRead = (notif) => {
  router.post(
    route('notifications.read', notif.id),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        refreshNotifications();

        if (notif.url) {
          router.visit(notif.url, {
            preserveScroll: true,
            preserveState: true,
          });
        }
      },
    },
  );
};

const markAllAsRead = () => {
  router.post(route('notifications.readAll'), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: refreshNotifications,
  });
};

const handleLogoutClick = () => {
  showLogoutDialog.value = true;
};

const confirmLogout = () => {
  router.post('/logout');
  showLogoutDialog.value = false;
};

watch(
  flash,
  (flashValue) => {
    const notifications = [
      ['success', flashValue?.success],
      ['error', flashValue?.error],
      ['info', flashValue?.status],
    ].filter(([, message]) => typeof message === 'string' && message.trim() !== '');

    if (notifications.length === 0) {
      shownFlashFingerprint.value = '';
      return;
    }

    const fingerprint = JSON.stringify(notifications);
    if (shownFlashFingerprint.value === fingerprint) {
      return;
    }

    shownFlashFingerprint.value = fingerprint;
    notifications.forEach(([type, message]) => notify(type, message));
  },
  { immediate: true, deep: true },
);

watch(
  () => page.url,
  () => {
    closeSidebar();
  },
);
</script>

<template>
  <div class="min-h-screen bg-slate-100 flex">
    <button
      v-if="sidebarOpen"
      class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden"
      aria-label="Tutup menu"
      @click="closeSidebar"
    />

    <UserDashboardSidebar
      :nav-items="navItems"
      :sidebar-open="sidebarOpen"
      :sidebar-collapsed="sidebarCollapsed"
      :is-active="isActive"
      :is-profile-active="isProfileActive"
      :user="user"
      :close-sidebar="closeSidebar"
      :profile-href="reviewerProfileHref"
      portal-label="Reviewer Workspace"
    />

    <div class="flex-1 flex flex-col lg:ml-0">
      <UserDashboardTopbar
        :sidebar-collapsed="sidebarCollapsed"
        :toggle-sidebar="toggleSidebar"
        :toggle-collapse="toggleCollapse"
        :unread-count="unreadCount"
        :notifications="notifications"
        :mark-all-as-read="markAllAsRead"
        :mark-as-read="markAsRead"
        :user="user"
        :user-initials="userInitials"
        :avatar-url="avatarUrl"
        :on-logout="handleLogoutClick"
        :profile-href="reviewerProfileHref"
        portal-label="Reviewer Workspace"
      >
        <template #title>
          <span>{{ title }}</span>
        </template>
      </UserDashboardTopbar>

      <main class="flex-1 p-4 lg:p-6">
        <slot />
      </main>

      <UserDashboardFooter />
    </div>

    <LogoutConfirmDialog
      :open="showLogoutDialog"
      @update:open="showLogoutDialog = $event"
      @confirm="confirmLogout"
    />

    <GlobalDialog />
    <NotificationCenter />
  </div>
</template>
