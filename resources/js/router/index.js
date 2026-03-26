// #region Imports
import { createWebHistory, createRouter } from 'vue-router'
import { useAuthStore } from '@/store/AuthStore'
import { useLicenseStore } from '@/store/LicenseStore'
import { getActivePinia } from "pinia"
//#endregion

const AdminLayout = () => import('@/layouts/admin-account/AdminLayout.vue');
const PublicLayout = () => import('@/layouts/public-account/FullScreenLayout.vue')
const PublicScreenLayout = () => import('@/layouts/public-account/PublicScreenLayout.vue')
const routes = [
  {
    path: '/license',
    name: 'License',
    component: () => import('../views/license/Activate.vue'),
    meta: { title: 'Activate License', skipLicenseCheck: true, middleware: 'guest' },
  },
  {
    path: "/",
    component: () => import('@/layouts/public-account/FullScreenLayout.vue'),
    meta: {
      middleware: "guest",
      auth: {
        roles: null,
        forbiddenRedirect: "/Page403",
      },
    },
    children: [
      {
        name: "Home",
        path: "",
        component: () => import('../views/Home.vue'),
        meta: { title: `Home` },
      },
      {
        name: "Signin",
        path: "signin",
        component: () => import('../views/auth/Signin.vue'),
        meta: { title: `Sign In` }
      }
    ]
  },
  {
    path: "/main/",
    component: AdminLayout,
    meta: {
      middleware: "auth",
      auth: {
        redirect: {
          name: "Signin",
        },
        forbiddenRedirect: "/Page403",
      },
    },
    children: [
      {
        name: "Dashboard",
        path: "dashboard",
        component: () => import('../views/dashboard/Index.vue'),
        meta: { title: `Dashboard`, roles: [1, 0] }
      },

      {
        name: "User",
        path: "users",
        component: () => import('../views/user/Index.vue'),
        meta: { title: `Users`, roles: [1] }
      },
      {
        name: "OfficeShift",
        path: "office-shifts",
        component: () => import('../views/office-shift/Index.vue'),
        meta: { title: `Office Shift`, roles: [1] }
      },
      {
        name: "Department",
        path: "departments",
        component: () => import('../views/department/Index.vue'),
        meta: { title: `Departments`, roles: [1] }
      },
      {
        name: "College",
        path: "colleges",
        component: () => import('../views/college/Index.vue'),
        meta: { title: `Colleges`, roles: [1] }
      },
      {
        name: "Machine",
        path: "machines",
        component: () => import('../views/machine/Index.vue'),
        meta: { title: `Biometric Machines`, roles: [1] }
      },
      {
        name: "BiometricReport",
        path: "reports/biometric",
        component: () => import('../views/report/Biometric.vue'),
        meta: { title: `Biometric Report`, roles: [1] }
      },
      {
        name: "AppSettings",
        path: "settings",
        component: () => import('../views/settings/Index.vue'),
        meta: { title: `Settings`, roles: [1] }
      },
      {
        name: "UserView",
        path: "users/:id",
        component: () => import('../views/user/View.vue'),
        meta: { title: `User Details`, roles: [1, 0] }
      },
      {
        name: "UserProfile",
        path: "user/profile",
        component: () => import('../views/user/Profile.vue'),
        meta: { title: 'User Profile', roles: [1, 0] }
      },
    ]
  },


]

const router = createRouter({
  history: createWebHistory(),
  routes, // short for `routes: routes`
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  const licenseStore = useLicenseStore()
  const currentRole = Number(authStore.user?.role ?? -1)
  const currentUserId = Number(authStore.user?.id ?? 0)

  document.title = to.meta.title || 'Biometric System'

  // ── License gate — always re-fetch so server-side changes (deactivation) are picked up ──
  if (!to.meta.skipLicenseCheck) {
    if (!licenseStore.license_loaded) {
      await licenseStore.loadStatus()
    }

    if (licenseStore.isExpired) {
      next({ name: 'License' })
      return
    }
  }
  // ────────────────────────────────────────────────────────────────────────────────────────

  if (to.meta.middleware === 'guest') {
    next()
    return
  }

  if (!authStore.authenticated) {
    next({ name: 'Signin' })
    return
  }

  const allowedRoles = to.meta.roles
  if (!allowedRoles || allowedRoles.includes(currentRole)) {
    if (currentRole === 0 && to.name === 'UserView') {
      const targetId = Number(to.params?.id ?? 0)
      if (targetId !== currentUserId) {
        next({ name: 'UserView', params: { id: currentUserId } })
        return
      }
    }

    next()
    return
  }

  if (currentRole === 0) {
    next({ name: 'Dashboard' })
    return
  }

  next({ name: 'Dashboard' })
})

export default router