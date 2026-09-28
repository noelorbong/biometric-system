<template>
  <aside
    :class="[
      'admin-sidebar fixed flex flex-col top-0 px-4 left-0 text-slate-200 h-screen transition-all duration-300 ease-in-out z-40 border-r',
      {
        'lg:w-[290px]': isExpanded || isMobileOpen || isHovered,
        'lg:w-[90px]': !isExpanded && !isHovered,
        'translate-x-0 w-[290px]': isMobileOpen,
        '-translate-x-full': !isMobileOpen,
        'lg:translate-x-0': true,
      },
    ]"
    @mouseenter="!isExpanded && (isHovered = true)"
    @mouseleave="isHovered = false"
  >
    <button
      type="button"
      class="admin-drawer-toggle absolute top-1/2 -right-8 flex items-center justify-center w-8 h-14 -translate-y-1/2 text-white rounded-r-xl shadow-lg shadow-slate-900/25 transition-[filter] duration-200 hover:brightness-125 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 dark:shadow-black/30 dark:focus:ring-offset-slate-900"
      :title="isExpanded || isMobileOpen ? 'Collapse sidebar' : 'Expand sidebar'"
      :aria-label="isExpanded || isMobileOpen ? 'Collapse sidebar' : 'Expand sidebar'"
      @click="handleToggle"
    >
      <span class="absolute left-1.5 h-6 border-l border-dotted border-white/60" aria-hidden="true"></span>
      <svg
        class="w-4 h-4 ml-0.5 transition-transform duration-300"
        :class="{ 'rotate-180': !isExpanded && !isMobileOpen }"
        viewBox="0 0 20 20"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="M12.5 4.16667L6.66667 10L12.5 15.8333"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </button>
    <div
      :class="[
        'py-6 flex',
        !isExpanded && !isHovered ? 'lg:justify-center' : 'justify-start',
      ]"
    >
    <router-link to="/">
        <img
          v-if="isExpanded || isHovered || isMobileOpen"
          class="dark:hidden "
          :src="'/images/logo/banner_white_mode.png'"
          alt="Logo"
          width="250"
          height="40"
        />
        <img
          v-if="isExpanded || isHovered || isMobileOpen"
          class="hidden dark:block"
          :src="'/images/logo/banner_white_mode.png'"
          alt="Logo"
          width="250"
          height="40"
        />
        <img
          v-else
          :src="'/images/logo/logo.png'"
          alt="Logo"
          width="32"
          height="32"
        />
      </router-link>
    </div>
    <div class="flex flex-col flex-1 overflow-y-auto duration-300 ease-linear no-scrollbar">
      <nav class="mb-6">
        <div class="flex flex-col gap-4">
          <div v-for="(menuGroup, groupIndex) in menuGroups" :key="groupIndex">
            <h2
              :class="[
                'mb-3 text-[11px] font-semibold uppercase flex tracking-[0.2em] leading-[20px] text-slate-300',
                !isExpanded && !isHovered
                  ? 'lg:justify-center'
                  : 'justify-start',
              ]"
            >
              <template v-if="isExpanded || isHovered || isMobileOpen">
                {{ menuGroup.title }}
              </template>
              <HorizontalDots v-else />
            </h2>
            <ul class="flex flex-col gap-4">
              <li v-for="(item, index) in menuGroup.items" :key="item.name">
                <button
                  v-if="item.subItems"
                  @click="toggleSubmenu(groupIndex, index)"
                  :class="[
                    'menu-item group w-full ring-1 transition-all',
                    {
                      'bg-sky-400/15 text-sky-200 ring-sky-300/30': isSubmenuOpen(groupIndex, index),
                      'text-slate-200 hover:bg-white/10 hover:text-white ring-transparent': !isSubmenuOpen(groupIndex, index),
                    },
                    !isExpanded && !isHovered
                      ? 'lg:justify-center'
                      : 'lg:justify-start',
                  ]"
                >
                  <span
                    :class="[
                      isSubmenuOpen(groupIndex, index)
                        ? 'text-sky-200'
                        : item.iconClass || 'text-slate-300 group-hover:text-white',
                    ]"
                  >
                    <component :is="item.icon" />
                  </span>
                  <span
                    v-if="isExpanded || isHovered || isMobileOpen"
                    class="menu-item-text"
                    >{{ item.name }}</span
                  >
                  <ChevronDownIcon
                    v-if="isExpanded || isHovered || isMobileOpen"
                    :class="[
                      'ml-auto w-5 h-5 transition-transform duration-200',
                      {
                        'rotate-180 text-sky-200': isSubmenuOpen(
                          groupIndex,
                          index
                        ),
                      },
                    ]"
                  />
                </button>
                <router-link
                  v-else-if="item.path"
                  :to="item.path"
                  :class="[
                    'menu-item group ring-1 transition-all',
                    {
                      'bg-sky-400/15 text-sky-200 ring-sky-300/30': isActive(item.path),
                      'text-slate-200 hover:bg-white/10 hover:text-white ring-transparent': !isActive(item.path),
                    },
                  ]"
                >
                  <span
                    :class="[
                      isActive(item.path)
                        ? 'text-sky-200'
                        : 'text-slate-300 group-hover:text-white',
                    ]"
                  >
                    <component :is="item.icon" />
                  </span>
                  <span
                    v-if="isExpanded || isHovered || isMobileOpen"
                    class="menu-item-text"
                    >{{ item.name }}</span
                  >
                </router-link>
                <transition
                  @enter="startTransition"
                  @after-enter="endTransition"
                  @before-leave="startTransition"
                  @after-leave="endTransition"
                >
                  <div
                    v-show="
                      isSubmenuOpen(groupIndex, index) &&
                      (isExpanded || isHovered || isMobileOpen)
                    "
                  >
                    <ul class="mt-2 space-y-1 ml-9">
                      <li v-for="subItem in item.subItems" :key="subItem.name">
                        <router-link
                          :to="subItem.path"
                          :class="[
                            'menu-dropdown-item',
                            {
                              'bg-sky-400/15 text-sky-200': isActive(
                                subItem.path
                              ),
                              'text-slate-200 hover:bg-white/10 hover:text-white': !isActive(
                                subItem.path
                              ),
                            },
                          ]"
                        >
                          {{ subItem.name }}
                          <span class="flex items-center gap-1 ml-auto">
                            <span
                              v-if="subItem.new"
                              :class="[
                                'menu-dropdown-badge',
                                {
                                  'menu-dropdown-badge-active': isActive(
                                    subItem.path
                                  ),
                                  'menu-dropdown-badge-inactive': !isActive(
                                    subItem.path
                                  ),
                                },
                              ]"
                            >
                              new
                            </span>
                            <span
                              v-if="subItem.pro"
                              :class="[
                                'menu-dropdown-badge',
                                {
                                  'menu-dropdown-badge-active': isActive(
                                    subItem.path
                                  ),
                                  'menu-dropdown-badge-inactive': !isActive(
                                    subItem.path
                                  ),
                                },
                              ]"
                            >
                              pro
                            </span>
                          </span>
                        </router-link>
                      </li>
                    </ul>
                  </div>
                </transition>
              </li>
            </ul>
          </div>
        </div>
      </nav>
    </div>
    <div class="flex items-center gap-2 py-4 border-t border-white/15">
      <ThemeToggler />
      <button
        type="button"
        class="menu-item group flex-1 text-slate-200 hover:bg-white/10 hover:text-white ring-1 ring-transparent"
        :class="!isExpanded && !isHovered ? 'lg:justify-center' : 'lg:justify-start'"
        title="Sign out"
        @click="signOut"
      >
        <LogoutIcon class="text-slate-300 group-hover:text-white" />
        <span v-if="isExpanded || isHovered || isMobileOpen" class="menu-item-text">Sign out</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/store/AuthStore'
const authStore = useAuthStore();
const { user } = storeToRefs(authStore)

import {
  ChevronDownIcon,
  HorizontalDots,
  LayoutDashboardIcon,
  DashboardIcon,
  PlugInIcon,
  TaskIcon,
  TableIcon,
  SettingsIcon,
  UserCircleIcon,
  UserGroupIcon,
  BuildingIcon,
  WorkIcon,
  LogoutIcon,
  BookSheIfcon,
  ClockIcon,
  HierarchicalIcon,
  FingerprintIcon,
  EmployeesIcon,
  GearIcon,
  UserIcon,
} from "@/icons";
import { useSidebar } from "@/composables/useSidebar";
import ThemeToggler from '@/components/common/ThemeToggler.vue'

const route = useRoute();

const { isExpanded, isMobileOpen, isHovered, openSubmenu, toggleSidebar, toggleMobileSidebar } = useSidebar();

const handleToggle = () => {
  if (window.innerWidth >= 991) {
    toggleSidebar()
  } else {
    toggleMobileSidebar()
  }
}

const menuAdminGroups = [
  {
    title: "Overview",
    items: [
      { icon: DashboardIcon, name: "Dashboard", path: "/main/dashboard" },
    ],
  },
  {
    title: "Attendance Ops",
    items: [
      { icon: FingerprintIcon, name: "Biometric Machines", path: "/main/machines" },
      {
        icon: BookSheIfcon,
        name: "Reports",
        subItems: [
          { name: "Biometric Report", path: "/main/reports/biometric" },
          { name: "Daily Attendance Monitoring", path: "/main/reports/daily-attendance" },
          { name: "Monthly Tardiness Report", path: "/main/reports/monthly-attendance" },
          { name: "Biometric Logs", path: "/main/biometric/logs" },
        ],
      },
    ],
  },
  {
    title: "Workforce Setup",
    items: [
      { icon: EmployeesIcon, name: "Users", path: "/main/users" },
      {
        icon: ClockIcon,
        name: "Attendance Settings",
        subItems: [
          { name: "Office Shift", path: "/main/office-shifts" },
          { name: "Weekly Schedule Exceptions", path: "/main/weekly-schedules" },
          { name: "Holidays", path: "/main/holidays" },
        ],
      },
      {
        icon: HierarchicalIcon,
        name: "Organizational Settings",
        subItems: [
          { name: "Departments", path: "/main/departments" },
          { name: "Colleges", path: "/main/colleges" },
        ],
      },
    ],
  },
  {
    title: "Account & System",
    items: [
      { icon: UserIcon, name: "Profile", path: "/main/user/profile" },
      { icon: GearIcon, name: "Settings", path: "/main/settings" },
    ],
  },
];

const menuUserGroups = computed(() => [
  {
    title: "My Workspace",
    items: [
      { icon: DashboardIcon, name: "Dashboard", path: "/main/dashboard" },
      { icon: UserIcon, name: "Profile", path: "/main/user/profile" },
      { icon: TaskIcon, name: "My Biometric", path: `/main/users/${Number(user.value?.id || 0)}` },
    ],
  },
])





const menuGroups = computed(() => {
  if (user.value?.role === 1) return menuAdminGroups
  if (Number(user.value?.role) === 0) return menuUserGroups.value
  return []
})

const isActive = (path) => route.path === path

const signOut = async () => {
  await authStore.logout()
}

const toggleSubmenu = (groupIndex, itemIndex) => {
  const key = `${groupIndex}-${itemIndex}`
  openSubmenu.value = openSubmenu.value === key ? null : key
}

const isSubmenuOpen = (groupIndex, itemIndex) => {
  return openSubmenu.value === `${groupIndex}-${itemIndex}`
}

const startTransition = (el) => {
  el.style.height = "auto";
  const height = el.scrollHeight;
  el.style.height = "0px";
  el.offsetHeight; // force reflow
  el.style.height = height + "px";
}

const endTransition = (el) => {
  el.style.height = "";
}
</script>
