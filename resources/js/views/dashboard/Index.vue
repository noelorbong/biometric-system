<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/store/AuthStore'
import { useDashboardStore } from '@/store/DashboardStore'

const authStore = useAuthStore()
const dashboardStore = useDashboardStore()

const { user } = storeToRefs(authStore)
const { dashboard, loading, error } = storeToRefs(dashboardStore)

const roleMap = {
  0: 'User',
  1: 'Super Admin',
  2: 'Region Admin',
  3: 'SUC Admin',
  4: 'Campus Admin',
  5: 'College Admin',
  6: 'Employee',
}

const isSuperAdmin = computed(() => Number(user.value?.role) === 1)
const stats = computed(() => dashboard.value?.stats || {})
const recentAttendance = computed(() => dashboard.value?.recent_attendance || [])
const machineOverview = computed(() => dashboard.value?.machine_overview || [])
const scheduleEntries = computed(() => dashboard.value?.schedule?.entries || [])

const headline = computed(() => {
  if (isSuperAdmin.value) {
    return 'Monitor attendance flow, machine health, and workforce coverage from one control point.'
  }

  return 'Track your recent attendance, review your shift, and jump straight to your profile and biometric records.'
})

const heroMetrics = computed(() => {
  if (isSuperAdmin.value) {
    return [
      { label: 'Users', value: stats.value.total_users || 0, note: `${stats.value.active_users || 0} active accounts` },
      { label: 'Machines', value: stats.value.total_machines || 0, note: `${stats.value.auto_download_machines || 0} auto-sync enabled` },
      { label: 'Attendance Today', value: stats.value.attendance_today || 0, note: `${stats.value.attendance_this_month || 0} logs this month` },
    ]
  }

  return [
    { label: 'Today', value: stats.value.attendance_today || 0, note: 'Attendance logs today' },
    { label: 'This Month', value: stats.value.attendance_this_month || 0, note: 'Attendance logs this month' },
    { label: 'This Year', value: stats.value.attendance_this_year || 0, note: `Last punch ${formatDateTime(stats.value.last_punch_at, true)}` },
  ]
})

const overviewCards = computed(() => {
  if (isSuperAdmin.value) {
    return [
      { label: 'Active Users', value: stats.value.active_users || 0, note: 'Accounts ready for attendance operations' },
      { label: 'Departments', value: stats.value.total_departments || 0, note: 'Organizational groups configured' },
      { label: 'Colleges', value: stats.value.total_colleges || 0, note: 'Academic units connected to users' },
      { label: 'Office Shifts', value: stats.value.total_office_shifts || 0, note: 'Shift templates available for assignment' },
    ]
  }

  return [
    { label: 'Department', value: dashboard.value?.user?.department || '-', note: 'Your assigned department' },
    { label: 'College', value: dashboard.value?.user?.college || '-', note: 'Current college affiliation' },
    { label: 'Office Shift', value: dashboard.value?.user?.office_shift || '-', note: 'Shift used for biometric review' },
    { label: 'Role', value: dashboard.value?.role_label || roleMap[Number(user.value?.role)] || '-', note: 'Access level for this account' },
  ]
})

const quickLinks = computed(() => {
  if (isSuperAdmin.value) {
    return [
      { label: 'Manage Users', path: '/main/users', note: 'Review accounts, affiliations, and biometric status' },
      { label: 'Open Machines', path: '/main/machines', note: 'Check machine sync health and latest downloads' },
      { label: 'View Reports', path: '/main/reports/biometric', note: 'Generate attendance reports for teams' },
      { label: 'System Settings', path: '/main/settings', note: 'Adjust timer behavior and company settings' },
    ]
  }

  return [
    { label: 'My Profile', path: '/main/user/profile', note: 'Update your account and personal details' },
    { label: 'My Biometric', path: `/main/users/${Number(user.value?.id || 0)}`, note: 'Review and print your attendance logs' },
    { label: 'My Shift', path: '/main/user/profile', note: 'Check the shift assigned to your account' },
    { label: 'Need Admin Help', path: '/main/user/profile', note: 'Use your profile as the main self-service page' },
  ]
})

const loadDashboard = async () => {
  await dashboardStore.loadDashboards()
}

const formatNumber = (value) => new Intl.NumberFormat().format(Number(value || 0))

const formatDateTime = (value, compact = false) => {
  if (!value) {
    return compact ? 'not available' : 'No attendance yet'
  }

  return new Intl.DateTimeFormat('en-US', {
    month: compact ? 'short' : 'long',
    day: 'numeric',
    year: compact ? undefined : 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(value))
}

const formatCheckType = (value) => {
  if (!value) return 'Attendance Log'
  if (String(value).toUpperCase() === 'I') return 'Check In'
  if (String(value).toUpperCase() === 'O') return 'Check Out'
  return value
}

onMounted(async () => {
  await loadDashboard()
})
</script>

<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] p-5 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
      <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">{{ isSuperAdmin ? 'Operations Overview' : 'My Workspace' }}</p>
          <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white lg:text-4xl">{{ isSuperAdmin ? 'Super Admin Dashboard' : 'User Dashboard' }}</h1>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200/90">
            {{ headline }}
          </p>
          <div class="mt-5 inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/10 px-4 py-2 text-sm text-slate-100 backdrop-blur-sm">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
            <span>{{ dashboard?.user?.name || user?.name || 'Account' }}</span>
            <span class="text-slate-300">{{ dashboard?.role_label || roleMap[Number(user?.role)] || 'User' }}</span>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-3 xl:min-w-[560px]">
          <div v-for="metric in heroMetrics" :key="metric.label" class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">{{ metric.label }}</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ formatNumber(metric.value) }}</p>
            <p class="mt-1 text-xs text-slate-300">{{ metric.note }}</p>
          </div>
        </div>
      </div>
    </section>

    <section v-if="error" class="rounded-[24px] border border-rose-200 bg-rose-50 p-4 text-rose-700 shadow-sm dark:border-rose-900/60 dark:bg-rose-950/20 dark:text-rose-200">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-sm font-semibold">Unable to load dashboard data</p>
          <p class="mt-1 text-sm text-rose-600/90 dark:text-rose-200/80">{{ error }}</p>
        </div>
        <button type="button" class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-700" @click="loadDashboard">
          Retry
        </button>
      </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article v-for="card in overviewCards" :key="card.label" class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ card.label }}</p>
        <p class="mt-4 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ typeof card.value === 'number' ? formatNumber(card.value) : card.value }}</p>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ card.note }}</p>
      </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_360px]">
      <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Attendance Feed</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">{{ isSuperAdmin ? 'Latest attendance activity' : 'My recent attendance' }}</h2>
          </div>
          <div v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Refreshing...</div>
        </div>

        <div v-if="recentAttendance.length" class="mt-5 space-y-3">
          <article v-for="(record, index) in recentAttendance" :key="`${record.userid || user?.id}-${record.checktime || index}`" class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/50 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ isSuperAdmin ? (record.name || `User #${record.userid}`) : formatCheckType(record.checktype) }}</p>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ isSuperAdmin ? formatCheckType(record.checktype) : `Machine ${record.machine_sn || 'N/A'}` }}
              </p>
            </div>
            <div class="text-left sm:text-right">
              <p class="text-sm font-medium text-slate-900 dark:text-white">{{ formatDateTime(record.checktime) }}</p>
              <p class="mt-1 text-xs uppercase tracking-[0.24em] text-slate-400">{{ record.machine_sn || 'No machine serial' }}</p>
            </div>
          </article>
        </div>
        <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
          No attendance records to show yet.
        </div>
      </div>

      <div class="space-y-4">
        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Quick Actions</p>
          <div class="mt-4 space-y-3">
            <router-link v-for="item in quickLinks" :key="item.label" :to="item.path" class="block rounded-2xl border border-slate-200 bg-slate-50/80 p-4 transition hover:border-sky-300 hover:bg-sky-50 dark:border-slate-800 dark:bg-slate-900/50 dark:hover:border-sky-800 dark:hover:bg-sky-950/20">
              <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ item.label }}</p>
              <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ item.note }}</p>
            </router-link>
          </div>
        </div>

        <div v-if="isSuperAdmin" class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Machine Sync</p>
          <div v-if="machineOverview.length" class="mt-4 space-y-3">
            <article v-for="machine in machineOverview" :key="machine.id" class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/50">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ machine.name }}</p>
                  <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ machine.ip || 'No IP configured' }}</p>
                </div>
                <span :class="machine.auto_download ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300'" class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]">
                  {{ machine.auto_download ? 'Auto' : 'Manual' }}
                </span>
              </div>
              <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400">
                <span>{{ machine.enabled ? 'Enabled' : 'Disabled' }}</span>
                <span>{{ formatDateTime(machine.last_synced_at, true) }}</span>
              </div>
            </article>
          </div>
          <div v-else class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
            No machine records available.
          </div>
        </div>

        <div v-else class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Assigned Schedule</p>
          <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/50">
            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ dashboard?.schedule?.name || 'No office shift assigned' }}</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ dashboard?.schedule?.schedule_label || 'No schedule pattern available yet.' }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
              <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
                {{ dashboard?.schedule?.is_flexible ? 'Flexible Shift' : 'Fixed Shift' }}
              </span>
            </div>
          </div>

          <div v-if="scheduleEntries.length" class="mt-4 space-y-3">
            <article v-for="entry in scheduleEntries" :key="entry.sequence" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/50">
              <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Segment {{ entry.sequence }}</p>
                <p class="mt-1 text-xs uppercase tracking-[0.2em] text-slate-400">{{ entry.is_next_day ? 'Next day exit' : 'Same day exit' }}</p>
              </div>
              <div class="text-right text-sm font-medium text-slate-700 dark:text-slate-200">
                <p>{{ entry.time_in || '--:--' }}</p>
                <p class="mt-1">{{ entry.time_out || '--:--' }}</p>
              </div>
            </article>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
