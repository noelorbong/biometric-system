<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import VueApexCharts from 'vue3-apexcharts'
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
const chartTextColor = '#475569'
const chartGridColor = '#E2E8F0'

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

const attendanceChartSeries = computed(() => [{
  name: 'Attendance Logs',
  data: [
    stats.value.attendance_today || 0,
    stats.value.attendance_this_month || 0,
    stats.value.attendance_this_year || 0,
  ],
}])

const attendanceChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'Outfit, sans-serif',
  },
  colors: ['#0891B2'],
  plotOptions: {
    bar: {
      borderRadius: 0,
      columnWidth: '42%',
    },
  },
  dataLabels: { enabled: false },
  grid: {
    borderColor: chartGridColor,
    strokeDashArray: 4,
  },
  xaxis: {
    categories: ['Today', 'Month', 'Year'],
    labels: { style: { colors: chartTextColor, fontSize: '11px' } },
    axisBorder: { color: chartGridColor },
    axisTicks: { color: chartGridColor },
  },
  yaxis: {
    labels: {
      style: { colors: chartTextColor, fontSize: '11px' },
      formatter: value => formatNumber(value),
    },
  },
  tooltip: {
    y: { formatter: value => `${formatNumber(value)} logs` },
  },
}))

const distributionSeries = computed(() => {
  if (isSuperAdmin.value) {
    const activeUsers = Number(stats.value.active_users || 0)
    const totalUsers = Number(stats.value.total_users || 0)
    const inactiveUsers = Math.max(totalUsers - activeUsers, 0)
    const autoMachines = Number(stats.value.auto_download_machines || 0)
    const totalMachines = Number(stats.value.total_machines || 0)
    const manualMachines = Math.max(totalMachines - autoMachines, 0)

    return [activeUsers, inactiveUsers, autoMachines, manualMachines]
  }

  const checkInCount = recentAttendance.value.filter(record => String(record.checktype || '').toUpperCase() === 'I').length
  const checkOutCount = recentAttendance.value.filter(record => String(record.checktype || '').toUpperCase() === 'O').length
  const otherCount = Math.max(recentAttendance.value.length - checkInCount - checkOutCount, 0)

  return [checkInCount, checkOutCount, otherCount]
})

const hasDistributionData = computed(() => distributionSeries.value.some(value => Number(value) > 0))

const distributionOptions = computed(() => ({
  chart: {
    type: 'donut',
    toolbar: { show: false },
    fontFamily: 'Outfit, sans-serif',
  },
  labels: isSuperAdmin.value ? ['Active Users', 'Inactive Users', 'Auto Machines', 'Manual Machines'] : ['Check In', 'Check Out', 'Other Logs'],
  colors: ['#0891B2', '#64748B', '#10B981', '#F59E0B'],
  dataLabels: {
    enabled: true,
    style: { fontSize: '11px', fontWeight: 600 },
    dropShadow: { enabled: false },
  },
  legend: {
    position: 'bottom',
    fontSize: '11px',
    labels: { colors: chartTextColor },
    markers: { radius: 0 },
  },
  stroke: { colors: ['#FFFFFF'], width: 2 },
  plotOptions: {
    pie: {
      donut: {
        size: '62%',
        labels: {
          show: true,
          total: {
            show: true,
            label: isSuperAdmin.value ? 'Total' : 'Recent',
            formatter: value => formatNumber(value.globals.seriesTotals.reduce((sum, item) => sum + item, 0)),
          },
        },
      },
    },
  },
  tooltip: {
    y: { formatter: value => formatNumber(value) },
  },
  noData: {
    text: 'No chart data',
    align: 'center',
    verticalAlign: 'middle',
    style: {
      color: chartTextColor,
      fontSize: '12px',
    },
  },
}))

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
  <div class="dashboard-page space-y-4 text-slate-800 dark:text-slate-200">
    <section class="-ml-4 -mt-4 -mr-4 border-b border-slate-900/20 bg-[linear-gradient(135deg,_#0f172a_0%,_#164e63_52%,_#0f766e_100%)] px-4 py-4 text-white shadow-sm md:-ml-6 md:-mr-6 md:-mt-6 md:px-6 dark:border-slate-800">
      <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold text-white">{{ isSuperAdmin ? 'Super Admin Dashboard' : 'User Dashboard' }}</h1>
            <span class="border border-white/20 bg-white/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-cyan-50">
              {{ dashboard?.role_label || roleMap[Number(user?.role)] || 'User' }}
            </span>
          </div>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-100/90">{{ headline }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 xl:justify-end">
          <div class="inline-flex min-h-9 items-center gap-2 border border-white/20 bg-white/10 px-3 text-xs text-white">
            <span class="h-2 w-2 bg-emerald-300"></span>
            <span class="font-medium">{{ dashboard?.user?.name || user?.name || 'Account' }}</span>
          </div>
          <button type="button" class="inline-flex h-9 items-center justify-center border border-white/30 bg-white px-3 text-xs font-semibold text-slate-900 transition hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading" @click="loadDashboard">
            {{ loading ? 'Refreshing...' : 'Refresh' }}
          </button>
        </div>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <article v-for="metric in heroMetrics" :key="metric.label" class="border border-white/15 bg-white/10 p-3 backdrop-blur">
          <div class="flex items-start justify-between gap-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-cyan-50/80">{{ metric.label }}</p>
            <span class="h-2 w-2 bg-cyan-200"></span>
          </div>
          <p class="mt-2 text-2xl font-semibold tracking-tight text-white">{{ formatNumber(metric.value) }}</p>
          <p class="mt-1 text-xs text-slate-100/80">{{ metric.note }}</p>
        </article>
      </div>
    </section>

    <section v-if="error" class="border border-rose-300 bg-rose-50 p-3 text-rose-700 shadow-sm dark:border-rose-900/60 dark:bg-rose-950/20 dark:text-rose-200">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-sm font-semibold">Unable to load dashboard data</p>
          <p class="mt-1 text-sm text-rose-600/90 dark:text-rose-200/80">{{ error }}</p>
        </div>
        <button type="button" class="inline-flex h-9 items-center justify-center border border-rose-700 bg-rose-600 px-3 text-xs font-semibold text-white transition hover:bg-rose-700" @click="loadDashboard">
          Retry
        </button>
      </div>
    </section>
<!-- 
    <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      <article v-for="card in overviewCards" :key="card.label" class="border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ card.label }}</p>
        <p class="mt-2 truncate text-lg font-semibold tracking-tight text-slate-950 dark:text-white">{{ typeof card.value === 'number' ? formatNumber(card.value) : card.value }}</p>
        <p class="mt-1 min-h-8 text-xs leading-4 text-slate-500 dark:text-slate-400">{{ card.note }}</p>
      </article>
    </section> -->

    <section class="grid gap-4 lg:grid-cols-2">
      <div class="border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Attendance Trend</p>
          <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Log volume overview</h2>
        </div>
        <div class="min-h-[260px] px-2 py-3">
          <VueApexCharts type="bar" height="235" :options="attendanceChartOptions" :series="attendanceChartSeries" />
        </div>
      </div>

      <div class="border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ isSuperAdmin ? 'Operations Mix' : 'Recent Log Mix' }}</p>
          <h2 class="text-sm font-semibold text-slate-950 dark:text-white">{{ isSuperAdmin ? 'Users and machines' : 'Check in / out balance' }}</h2>
        </div>
        <div class="min-h-[260px] px-2 py-3">
          <VueApexCharts v-if="hasDistributionData" type="donut" height="235" :options="distributionOptions" :series="distributionSeries" />
          <div v-else class="flex h-[235px] items-center justify-center text-sm text-slate-500 dark:text-slate-400">
            No chart data
          </div>
        </div>
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
      <div class="border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Attendance Feed</p>
            <h2 class="text-sm font-semibold text-slate-950 dark:text-white">{{ isSuperAdmin ? 'Latest attendance activity' : 'My recent attendance' }}</h2>
          </div>
          <div v-if="loading" class="text-xs text-slate-500 dark:text-slate-400">Refreshing...</div>
        </div>

        <div v-if="recentAttendance.length" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-100 text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th class="px-3 py-2 text-left font-semibold">{{ isSuperAdmin ? 'Employee' : 'Type' }}</th>
                <th class="px-3 py-2 text-left font-semibold">{{ isSuperAdmin ? 'Type' : 'Machine' }}</th>
                <th class="px-3 py-2 text-left font-semibold">Date / Time</th>
                <th class="px-3 py-2 text-left font-semibold">Serial</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="(record, index) in recentAttendance" :key="`${record.userid || user?.id}-${record.checktime || index}`" class="hover:bg-slate-50 dark:hover:bg-slate-900/60">
                <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-950 dark:text-white">{{ isSuperAdmin ? (record.name || `User #${record.userid}`) : formatCheckType(record.checktype) }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-slate-600 dark:text-slate-300">{{ isSuperAdmin ? formatCheckType(record.checktype) : `Machine ${record.machine_sn || 'N/A'}` }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-slate-600 dark:text-slate-300">{{ formatDateTime(record.checktime) }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ record.machine_sn || 'No serial' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="m-3 border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
          No attendance records to show yet.
        </div>
      </div>

      <div class="space-y-4">
        <div class="border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
          <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Quick Actions</p>
          </div>
          <div class="divide-y divide-slate-100 dark:divide-slate-800">
            <router-link v-for="item in quickLinks" :key="item.label" :to="item.path" class="block px-3 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-900/60">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ item.label }}</p>
                <span class="text-slate-400">&rsaquo;</span>
              </div>
              <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ item.note }}</p>
            </router-link>
          </div>
        </div>

        <div v-if="isSuperAdmin" class="border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
          <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Machine Sync</p>
          </div>
          <div v-if="machineOverview.length" class="divide-y divide-slate-100 dark:divide-slate-800">
            <article v-for="machine in machineOverview" :key="machine.id" class="px-3 py-3">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ machine.name }}</p>
                  <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ machine.ip || 'No IP configured' }}</p>
                </div>
                <span :class="machine.auto_download ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300' : 'border-slate-300 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'" class="border px-2 py-1 text-[11px] font-semibold uppercase tracking-wide">
                  {{ machine.auto_download ? 'Auto' : 'Manual' }}
                </span>
              </div>
              <div class="mt-3 flex items-center justify-between text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <span>{{ machine.enabled ? 'Enabled' : 'Disabled' }}</span>
                <span>{{ formatDateTime(machine.last_synced_at, true) }}</span>
              </div>
            </article>
          </div>
          <div v-else class="m-3 border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
            No machine records available.
          </div>
        </div>

        <div v-else class="border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
          <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Assigned Schedule</p>
          </div>
          <div class="border-b border-slate-100 px-3 py-3 dark:border-slate-800">
            <p class="text-base font-semibold text-slate-950 dark:text-white">{{ dashboard?.schedule?.name || 'No office shift assigned' }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ dashboard?.schedule?.schedule_label || 'No schedule pattern available yet.' }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <span class="border border-cyan-300 bg-cyan-50 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-cyan-700 dark:border-cyan-800 dark:bg-cyan-950/30 dark:text-cyan-300">
                {{ dashboard?.schedule?.is_flexible ? 'Flexible Shift' : 'Fixed Shift' }}
              </span>
            </div>
          </div>

          <div v-if="scheduleEntries.length" class="divide-y divide-slate-100 dark:divide-slate-800">
            <article v-for="entry in scheduleEntries" :key="entry.sequence" class="flex items-center justify-between px-3 py-3">
              <div>
                <p class="text-sm font-semibold text-slate-950 dark:text-white">Segment {{ entry.sequence }}</p>
                <p class="mt-1 text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ entry.is_next_day ? 'Next day exit' : 'Same day exit' }}</p>
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
