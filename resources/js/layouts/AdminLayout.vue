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
  Ruler,
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
    return route('profile.edit');
  } catch (_error) {
    return '/profile';
  }
});

const navItems = [
  {
    key: 'admin.dashboard',
    label: 'Dashboard',
    icon: LayoutDashboard,
    routeName: 'admin.dashboard',
    activePatterns: ['admin.dashboard'],
    exactPaths: ['/admin'],
  },
  {
    key: 'admin.requests',
    label: 'Permohonan',
    icon: ClipboardList,
    routeName: 'admin.appraisal-requests.index',
    activePatterns: ['admin.appraisal-requests.*'],
    pathPrefixes: ['/admin/permohonan-penilaian'],
  },
  {
    key: 'admin.finance',
    label: 'Keuangan',
    icon: CreditCard,
    routeName: 'admin.finance.payments.index',
    activePatterns: ['admin.finance.*'],
    pathPrefixes: ['/admin/keuangan'],
  },
  {
    key: 'admin.master-data',
    label: 'Master Data',
    icon: MapPinned,
    routeName: 'admin.master-data.provinces.index',
    activePatterns: ['admin.master-data.*'],
    pathPrefixes: ['/admin/master-data'],
    subItems: [
      {
        key: 'admin.master-data.users',
        label: 'User Terdaftar',
        icon: Users,
        routeName: 'admin.master-data.users.index',
        activePatterns: ['admin.master-data.users.*'],
        pathPrefixes: ['/admin/master-data/users'],
      },
      {
        key: 'admin.master-data.provinces',
        label: 'Provinsi',
        icon: Map,
        routeName: 'admin.master-data.provinces.index',
        activePatterns: ['admin.master-data.provinces.*'],
        pathPrefixes: ['/admin/master-data/provinsi'],
      },
      {
        key: 'admin.master-data.regencies',
        label: 'Kabupaten/Kota',
        icon: Building2,
        routeName: 'admin.master-data.regencies.index',
        activePatterns: ['admin.master-data.regencies.*'],
        pathPrefixes: ['/admin/master-data/kabupaten-kota'],
      },
      {
        key: 'admin.master-data.districts',
        label: 'Kecamatan',
        icon: MapPinned,
        routeName: 'admin.master-data.districts.index',
        activePatterns: ['admin.master-data.districts.*'],
        pathPrefixes: ['/admin/master-data/kecamatan'],
      },
      {
        key: 'admin.master-data.villages',
        label: 'Kelurahan/Desa',
        icon: House,
        routeName: 'admin.master-data.villages.index',
        activePatterns: ['admin.master-data.villages.*'],
        pathPrefixes: ['/admin/master-data/kelurahan-desa'],
      },
    ],
  },
  {
    key: 'admin.ref-guidelines',
    label: 'Pedoman Referensi',
    icon: BookOpen,
    routeName: 'admin.ref-guidelines.guideline-sets.index',
    activePatterns: ['admin.ref-guidelines.*'],
    pathPrefixes: ['/admin/ref-guidelines'],
    subItems: [
      {
        key: 'admin.ref-guidelines.guideline-sets',
        label: 'Set Pedoman',
        icon: BookText,
        routeName: 'admin.ref-guidelines.guideline-sets.index',
        activePatterns: ['admin.ref-guidelines.guideline-sets.*'],
        pathPrefixes: ['/admin/ref-guidelines/guideline-sets'],
      },
      {
        key: 'admin.ref-guidelines.construction-cost-indices',
        label: 'Indeks Kemahalan Konstruksi',
        icon: Factory,
        routeName: 'admin.ref-guidelines.construction-cost-indices.index',
        activePatterns: ['admin.ref-guidelines.construction-cost-indices.*'],
        pathPrefixes: ['/admin/ref-guidelines/ikk'],
      },
      {
        key: 'admin.ref-guidelines.cost-elements',
        label: 'Biaya Unit Terpasang',
        icon: Layers3,
        routeName: 'admin.ref-guidelines.cost-elements.index',
        activePatterns: ['admin.ref-guidelines.cost-elements.*'],
        pathPrefixes: ['/admin/ref-guidelines/cost-elements'],
      },
      {
        key: 'admin.ref-guidelines.floor-indices',
        label: 'Indeks Lantai',
        icon: Building2,
        routeName: 'admin.ref-guidelines.floor-indices.index',
        activePatterns: ['admin.ref-guidelines.floor-indices.*'],
        pathPrefixes: ['/admin/ref-guidelines/floor-indices'],
      },
      {
        key: 'admin.ref-guidelines.mappi-rcn-standards',
        label: 'MAPPI RCN',
        icon: Ruler,
        routeName: 'admin.ref-guidelines.mappi-rcn-standards.index',
        activePatterns: ['admin.ref-guidelines.mappi-rcn-standards.*'],
        pathPrefixes: ['/admin/ref-guidelines/mappi-rcn-standards'],
      },
      {
        key: 'admin.ref-guidelines.building-economic-lives',
        label: 'BEL',
        icon: BookMarked,
        href: '/legacy-admin/ref-guidelines/building-economic-lives',
      },
      {
        key: 'admin.ref-guidelines.ikk-by-province',
        label: 'IKK by Province',
        icon: MapPinned,
        href: '/legacy-admin/ref-guidelines/ikk-by-province',
      },
      {
        key: 'admin.ref-guidelines.valuation-settings',
        label: 'Pengaturan Valuasi',
        icon: ClipboardList,
        routeName: 'admin.ref-guidelines.valuation-settings.index',
        activePatterns: ['admin.ref-guidelines.valuation-settings.*'],
        pathPrefixes: ['/admin/ref-guidelines/valuation-settings'],
      },
    ],
  },
  {
    key: 'admin.access-control',
    label: 'Hak Akses',
    icon: LockKeyhole,
    routeName: 'admin.access-control.roles.index',
    activePatterns: ['admin.access-control.*'],
    pathPrefixes: ['/admin/hak-akses'],
    subItems: [
      {
        key: 'admin.access-control.roles',
        label: 'Roles',
        icon: ShieldCheck,
        routeName: 'admin.access-control.roles.index',
        activePatterns: ['admin.access-control.roles.*'],
        pathPrefixes: ['/admin/hak-akses/roles'],
      },
    ],
  },
  {
    key: 'admin.content',
    label: 'Konten',
    icon: BookOpen,
    routeName: 'admin.content.articles.index',
    activePatterns: ['admin.content.articles.*', 'admin.content.categories.*', 'admin.content.tags.*'],
    pathPrefixes: ['/admin/konten/artikel', '/admin/konten/kategori-artikel', '/admin/konten/tag'],
    subItems: [
      {
        key: 'admin.content.articles',
        label: 'Artikel',
        icon: BookText,
        routeName: 'admin.content.articles.index',
        activePatterns: ['admin.content.articles.*'],
        pathPrefixes: ['/admin/konten/artikel'],
      },
      {
        key: 'admin.content.categories',
        label: 'Kategori Artikel',
        icon: FolderTree,
        routeName: 'admin.content.categories.index',
        activePatterns: ['admin.content.categories.*'],
        pathPrefixes: ['/admin/konten/kategori-artikel'],
      },
      {
        key: 'admin.content.tags',
        label: 'Tag',
        icon: Tags,
        routeName: 'admin.content.tags.index',
        activePatterns: ['admin.content.tags.*'],
        pathPrefixes: ['/admin/konten/tag'],
      },
    ],
  },
  {
    key: 'admin.legal',
    label: 'Konten & Legal',
    icon: FileText,
    routeName: 'admin.content.legal.faqs.index',
    activePatterns: ['admin.content.legal.*'],
    pathPrefixes: ['/admin/konten/legal'],
    subItems: [
      {
        key: 'admin.legal.faqs',
        label: 'FAQ',
        icon: CircleHelp,
        routeName: 'admin.content.legal.faqs.index',
        activePatterns: ['admin.content.legal.faqs.*'],
        pathPrefixes: ['/admin/konten/legal/faq'],
      },
      {
        key: 'admin.legal.features',
        label: 'Fitur',
        icon: Sparkles,
        routeName: 'admin.content.legal.features.index',
        activePatterns: ['admin.content.legal.features.*'],
        pathPrefixes: ['/admin/konten/legal/fitur'],
      },
      {
        key: 'admin.legal.testimonials',
        label: 'Testimoni',
        icon: MessageSquareQuote,
        routeName: 'admin.content.legal.testimonials.index',
        activePatterns: ['admin.content.legal.testimonials.*'],
        pathPrefixes: ['/admin/konten/legal/testimoni'],
      },
      {
        key: 'admin.legal.terms',
        label: 'Terms',
        icon: ScrollText,
        routeName: 'admin.content.legal.terms.index',
        activePatterns: ['admin.content.legal.terms.*'],
        pathPrefixes: ['/admin/konten/legal/terms'],
      },
      {
        key: 'admin.legal.privacy',
        label: 'Privacy',
        icon: ShieldCheck,
        routeName: 'admin.content.legal.privacy.index',
        activePatterns: ['admin.content.legal.privacy.*'],
        pathPrefixes: ['/admin/konten/legal/privacy'],
      },
      {
        key: 'admin.legal.consent',
        label: 'Consent',
        icon: FileText,
        routeName: 'admin.content.legal.consent.index',
        activePatterns: ['admin.content.legal.consent.*'],
        pathPrefixes: ['/admin/konten/legal/consent'],
      },
      {
        key: 'admin.legal.user-consents',
        label: 'Audit Consent',
        icon: ClipboardCheck,
        routeName: 'admin.content.legal.user-consents.index',
        activePatterns: ['admin.content.legal.user-consents.*'],
        pathPrefixes: ['/admin/konten/legal/persetujuan-pengguna'],
      },
    ],
  },
  {
    key: 'admin.communications',
    label: 'Komunikasi',
    icon: Mail,
    routeName: 'admin.communications.contact-messages.index',
    activePatterns: ['admin.communications.*'],
    pathPrefixes: ['/admin/komunikasi'],
    subItems: [
      {
        key: 'admin.communications.contact-messages',
        label: 'Contact Message',
        icon: Mail,
        routeName: 'admin.communications.contact-messages.index',
        activePatterns: ['admin.communications.contact-messages.*'],
        pathPrefixes: ['/admin/komunikasi/contact-messages'],
      },
    ],
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
  openGroups.value = navItems.reduce((carry, item) => {
    if (item.subItems?.length) {
      carry[item.key] = isActive(item);
    }

    return carry;
  }, {});
};

const isProfileActive = computed(() => {
  try {
    return route().current('profile.*');
  } catch (_error) {
    return matchesPathPrefix(currentPath.value, ['/profile']);
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
      portal-label="Admin Control"
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
        portal-label="Admin Control"
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
