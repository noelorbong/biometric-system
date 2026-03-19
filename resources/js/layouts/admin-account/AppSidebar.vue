<template>
  <aside
    :class="[
      'fixed mt-16 flex flex-col lg:mt-0 top-0 px-4 left-0 bg-[linear-gradient(180deg,_#f8fafc_0%,_#eef2ff_100%)] dark:bg-[linear-gradient(180deg,_#0f172a_0%,_#111827_100%)] dark:border-slate-800 text-slate-900 h-screen transition-all duration-300 ease-in-out z-20 border-r border-slate-200',
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
    <div class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
      <nav class="mb-6">
        <div class="flex flex-col gap-4">
          <div v-for="(menuGroup, groupIndex) in menuGroups" :key="groupIndex">
            <h2
              :class="[
                'mb-3 text-[11px] font-semibold uppercase flex tracking-[0.2em] leading-[20px] text-slate-400 dark:text-slate-500',
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
                      'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-800/40': isSubmenuOpen(groupIndex, index),
                      'text-slate-700 hover:bg-slate-100 ring-transparent dark:text-slate-300 dark:hover:bg-slate-800/50': !isSubmenuOpen(groupIndex, index),
                    },
                    !isExpanded && !isHovered
                      ? 'lg:justify-center'
                      : 'lg:justify-start',
                  ]"
                >
                  <span
                    :class="[
                      isSubmenuOpen(groupIndex, index)
                        ? 'text-sky-600 dark:text-sky-300'
                        : 'text-slate-500 group-hover:text-slate-700 dark:text-slate-400 dark:group-hover:text-slate-300',
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
                        'rotate-180 text-sky-500 dark:text-sky-300': isSubmenuOpen(
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
                      'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-800/40': isActive(item.path),
                      'text-slate-700 hover:bg-slate-100 ring-transparent dark:text-slate-300 dark:hover:bg-slate-800/50': !isActive(item.path),
                    },
                  ]"
                >
                  <span
                    :class="[
                      isActive(item.path)
                        ? 'text-sky-600 dark:text-sky-300'
                        : 'text-slate-500 group-hover:text-slate-700 dark:text-slate-400 dark:group-hover:text-slate-300',
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
                              'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300': isActive(
                                subItem.path
                              ),
                              'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800/50': !isActive(
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
  PlugInIcon,
  TaskIcon,
  SettingsIcon,
  UserCircleIcon,
  UserGroupIcon,
  BuildingIcon,
  WorkIcon,
} from "@/icons";
import { useSidebar } from "@/composables/useSidebar";

const route = useRoute();

const { isExpanded, isMobileOpen, isHovered, openSubmenu } = useSidebar();

const menuAdminGroups = [
  {
    title: "Overview",
    items: [
      { icon: LayoutDashboardIcon, name: "Dashboard", path: "/main/dashboard" },
    ],
  },
  {
    title: "Attendance Ops",
    items: [
      { icon: PlugInIcon, name: "Biometric Machines", path: "/main/machines" },
      { icon: TaskIcon, name: "Biometric Report", path: "/main/reports/biometric" },
    ],
  },
  {
    title: "Workforce Setup",
    items: [
      { icon: UserGroupIcon, name: "Users", path: "/main/users" },
      { icon: WorkIcon, name: "Office Shift", path: "/main/office-shifts" },
      { icon: BuildingIcon, name: "Departments", path: "/main/departments" },
      { icon: BuildingIcon, name: "Colleges", path: "/main/colleges" },
    ],
  },
  {
    title: "Account & System",
    items: [
      { icon: UserCircleIcon, name: "Profile", path: "/main/user/profile" },
      { icon: SettingsIcon, name: "Settings", path: "/main/settings" },
    ],
  },
];

const menuUserGroups = computed(() => [
  {
    title: "My Workspace",
    items: [
      { icon: LayoutDashboardIcon, name: "Dashboard", path: "/main/dashboard" },
      { icon: UserCircleIcon, name: "Profile", path: "/main/user/profile" },
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

const toggleSubmenu = (groupIndex, itemIndex) => {
  const key = `${groupIndex}-${itemIndex}`
  openSubmenu.value = openSubmenu.value === key ? null : key
}


const isAnySubmenuRouteActive = computed(() => {
  return menuGroups.value.some((group) =>
    group.items.some(
      (item) =>
        item.subItems && item.subItems.some((subItem) => isActive(subItem.path))
    )
  );
});

const isSubmenuOpen = (groupIndex, itemIndex) => {
  const key = `${groupIndex}-${itemIndex}`;
  return (
    openSubmenu.value === key ||
    (isAnySubmenuRouteActive.value &&
      menuGroups.value[groupIndex].items[itemIndex].subItems?.some((subItem) =>
        isActive(subItem.path)
      ))
  )
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
