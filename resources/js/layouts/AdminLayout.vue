<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
  BookMarked,
  BookOpen,
  BookText,
  Building2,
  ClipboardList,
  ClipboardCheck,
  CreditCard,
  Factory,
  House,
  FolderTree,
  FileText,
  Layers3,
  Map,
  MapPinned,
  Mail,
  MessageSquareQuote,
  LayoutDashboard,
  LockKeyhole,
  CircleHelp,
  CircleSlash2,
  Ruler,
  Scale,
  ScrollText,
  ShieldCheck,
  Sparkles,
  Tags,
  Users,
} from 'lucide-vue-next';
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
    default: 'Admin',
  },
});

const page = usePage();
const { notify } = useNotification();
const user = computed(() => page.props.auth?.user ?? {});
const flash = computed(() => page.props.flash ?? {});
const defaultAvatarUrl = '/images/avatar-default.svg';
const avatarUrl = computed(() => user.value?.avatar_url || defaultAvatarUrl);
const shownFlashFingerprint = ref('');
const openGroups = ref({});
const profileHref = computed(() => {
  try {
    return isReviewerContext.value ? route('reviewer.profile.edit') : route('profile.edit');
  } catch (_error) {
    return isReviewerContext.value ? '/reviewer/profile' : '/profile';
  }
});

const isReviewerContext = computed(() => currentPath.value.startsWith('/reviewer'));

const iconMap = {
  BookMarked,
  BookOpen,
  BookText,
  Building2,
  ClipboardCheck,
  ClipboardList,
  CreditCard,
  Factory,
  House,
  FolderTree,
  FileText,
  Layers3,
  LayoutDashboard,
  LockKeyhole,
  Mail,
  Map,
  MapPinned,
  MessageSquareQuote,
  CircleHelp,
  CircleSlash2,
  Ruler,
  Scale,
  ScrollText,
  ShieldCheck,
  Sparkles,
  Tags,
  Users,
};

const mapNavItems = (items = []) => items.map((item) => ({
  ...item,
  icon: iconMap[item.icon] || ClipboardList,
  subItems: item.subItems?.length ? mapNavItems(item.subItems) : undefined,
}));

const navItems = computed(() => mapNavItems(
  isReviewerContext.value
    ? (page.props.navigation?.reviewer_nav || [])
    : (page.props.navigation?.admin_nav || []),
));

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
  syncOpenGroups();

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

    return route().current(item.routeName, item.routeParams ?? {});
  } catch (_error) {
    if (item.exactPaths?.includes(currentPath.value)) {
      return true;
    }

    if (item.pathPrefixes?.length) {
      return matchesPathPrefix(currentPath.value, item.pathPrefixes);
    }

    return false;
  }
};

const syncOpenGroups = () => {
  openGroups.value = navItems.value.reduce((carry, item) => {
    if (item.subItems?.length) {
      carry[item.key] = isActive(item);
    }

    return carry;
  }, {});
};

const isProfileActive = computed(() => {
  try {
    return isReviewerContext.value ? route().current('reviewer.profile.*') : route().current('profile.*');
  } catch (_error) {
    return matchesPathPrefix(currentPath.value, isReviewerContext.value ? ['/reviewer/profile'] : ['/profile']);
  }
});

const portalLabel = computed(() => (isReviewerContext.value ? 'Reviewer Workspace' : 'Admin Control'));

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
    const entries = [
      ['success', flashValue?.success],
      ['error', flashValue?.error],
      ['info', flashValue?.status],
    ].filter(([, message]) => typeof message === 'string' && message.trim() !== '');

    if (entries.length === 0) {
      shownFlashFingerprint.value = '';
      return;
    }

    const fingerprint = JSON.stringify(entries);
    if (shownFlashFingerprint.value === fingerprint) {
      return;
    }

    shownFlashFingerprint.value = fingerprint;
    entries.forEach(([type, message]) => notify(type, message));
  },
  { immediate: true, deep: true },
);

watch(
  () => page.url,
  () => {
    syncOpenGroups();
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
      :open-groups="openGroups"
      :is-profile-active="isProfileActive"
      :user="user"
      :close-sidebar="closeSidebar"
      :profile-href="profileHref"
      :portal-label="portalLabel"
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
        :profile-href="profileHref"
        :portal-label="portalLabel"
      >
        <template #title>
          <span>{{ props.title }}</span>
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
