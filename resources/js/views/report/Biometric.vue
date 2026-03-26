<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/common/Modal.vue'
import { useUserStore } from '@/store/UserStore'
import { useAppSettingStore } from '@/store/AppSettingStore'
import { storeToRefs } from 'pinia'
import PrintableAttendance from '@/views/user/components/PrintableAttendance.vue'

const userStore = useUserStore()
const appSettingStore = useAppSettingStore()
const { officeShifts, departments, colleges } = storeToRefs(userStore)
const { companySchoolName } = storeToRefs(appSettingStore)

const now = new Date()
const filters = ref({
  year: now.getFullYear(),
  month: now.getMonth() + 1,
  office_shift_id: '',
  department_id: '',
  college_id: '',
})

const reportUsers = ref([])
const loading = ref(false)
const copiesPerUser = ref(1)
const printableRefs = ref([])
const selectedUserIds = ref([])
const biometricModalOpen = ref(false)
const biometricModalLoading = ref(false)
const biometricLogRows = ref([])
const biometricLogOverrides = ref([])
const biometricLogUser = ref(null)

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 1800,
  timerProgressBar: true,
})

const monthOptions = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

const yearOptions = computed(() => {
  const current = now.getFullYear()
  const years = []
  for (let y = current + 1; y >= current - 10; y -= 1) {
    years.push(y)
  }
  return years
})

const monthYearLabel = computed(() => {
  const date = new Date(Number(filters.value.year), Number(filters.value.month) - 1)
  return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const selectedCount = computed(() => selectedUserIds.value.length)
const allSelected = computed(() => {
  return reportUsers.value.length > 0 && selectedUserIds.value.length === reportUsers.value.length
})
const unselectedCount = computed(() => {
  return Math.max(reportUsers.value.length - selectedUserIds.value.length, 0)
})

const toastResult = (message, icon = 'success') => {
  Toast.fire({ icon, title: message })
}

const loadOptions = async () => {
  await appSettingStore.loadSettings()
  await userStore.loadUsers()
}

const generateReport = async () => {
  loading.value = true

  try {
    const payload = {
      year: Number(filters.value.year),
      month: Number(filters.value.month),
      office_shift_id: filters.value.office_shift_id === '' ? null : Number(filters.value.office_shift_id),
      department_id: filters.value.department_id === '' ? null : Number(filters.value.department_id),
      college_id: filters.value.college_id === '' ? null : Number(filters.value.college_id),
    }

    const resp = await axios.post('/api/report/biometric', payload)
    reportUsers.value = resp?.data?.report_users || []
    selectedUserIds.value = reportUsers.value.map((user) => user.id)

    if (!reportUsers.value.length) {
      toastResult('No users found for selected filters', 'info')
    }
        await fetchOverridesForUsers(reportUsers.value)
  } catch (error) {
    reportUsers.value = []
    toastResult(error?.response?.data?.message || 'Unable to generate report', 'error')
  } finally {
    loading.value = false
  }
}
    const fetchOverridesForUsers = async (users) => {
      for (const user of users) {
        try {
          const resp = await axios.post('/api/user/checkinout', {
            user_id: user.id,
            year: Number(filters.value.year),
            month: Number(filters.value.month),
          })
          user._overrides = resp?.data?.overrides || []
        } catch (err) {
          user._overrides = []
        }
      }
    }

const printReport = async () => {
  if (!reportUsers.value.length) {
    toastResult('Generate report first', 'info')
    return
  }

  await nextTick()

  if (!selectedUserIds.value.length) {
    toastResult('Select at least one user to print', 'info')
    return
  }

  const printableComponents = (printableRefs.value || [])
  const firstPayload = printableComponents[0]?.getPrintPayload?.(1)
  const styles = firstPayload?.styles || ''

  const allCopies = []
  const copiesEach = Number(copiesPerUser.value || 1)

  reportUsers.value.forEach((user, index) => {
    if (!selectedUserIds.value.includes(user.id)) {
      return
    }

    const componentRef = printableComponents[index]
    const content = componentRef?.getPrintContent?.() || ''
    if (!content) {
      return
    }

    for (let i = 0; i < copiesEach; i += 1) {
      allCopies.push(`<div class="form-copy">${content}</div>`)
    }
  })

  if (!allCopies.length) {
    toastResult('Nothing to print', 'info')
    return
  }

  const perRow = 4
  let formsHtml = ''
  for (let i = 0; i < allCopies.length; i += perRow) {
    const rowInner = allCopies.slice(i, i + perRow).join('')
    formsHtml += `<div class="page-wrapper">${rowInner}</div>`
  }

  const win = window.open('', '_blank')
  win.document.write(`
    <!doctype html>
    <html>
      <head>
        <meta charset="utf-8" />
        <title>Biometric Report</title>
        <style>
          ${styles}
        </style>
      </head>
      <body>${formsHtml}</body>
    </html>
  `)
  win.document.close()
  win.focus()
  win.print()
  win.close()
}

onMounted(async () => {
  await loadOptions()
})

const toggleSelectAll = (event) => {
  if (event.target.checked) {
    selectedUserIds.value = reportUsers.value.map((user) => user.id)
    return
  }

  selectedUserIds.value = []
}

const formatLogDateTime = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'

  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

const normalizeCheckType = (value) => String(value || '').trim().toUpperCase()

const checkInCount = computed(() => mergedBiometricLogs.value.filter((log) => normalizeCheckType(log?.CHECKTYPE) === 'I').length)
const checkOutCount = computed(() => mergedBiometricLogs.value.filter((log) => normalizeCheckType(log?.CHECKTYPE) === 'O').length)

const formatCheckTypeLabel = (value) => {
  const normalized = normalizeCheckType(value)
  if (normalized === 'I') return 'Check In'
  if (normalized === 'O') return 'Check Out'
  return normalized || 'Unknown'
}

const checkTypeBadgeClass = (value) => {
  const normalized = normalizeCheckType(value)
  if (normalized === 'I') {
    return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300'
  }
  if (normalized === 'O') {
    return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-900/20 dark:text-sky-300'
  }
  return 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
}

const mergedBiometricLogs = computed(() => {
  const withActions = [...biometricLogRows.value]
  const overrideMap = new Map()
  biometricLogOverrides.value.forEach((override) => {
    const key = String(override.checkinout_id || '').trim()
    if (key) {
      overrideMap.set(key, override.action_type)
    }
  })
  return withActions.map((log) => ({
    ...log,
    _override_action: overrideMap.get(String(log.id || '').trim()) || null,
  }))
})

const openBiometricLogs = async (user) => {
  biometricModalOpen.value = true
  biometricModalLoading.value = true
  biometricLogUser.value = user
  biometricLogRows.value = []
  biometricLogOverrides.value = []

  try {
    const resp = await axios.post('/api/user/checkinout', {
      user_id: user.id,
      year: Number(filters.value.year),
      month: Number(filters.value.month),
    })

    biometricLogRows.value = resp?.data?.checkinouts || []
    biometricLogOverrides.value = resp?.data?.overrides || []
  } catch (error) {
    biometricLogRows.value = []
    biometricLogOverrides.value = []
    toastResult(error?.response?.data?.message || 'Unable to load biometric logs', 'error')
  } finally {
    biometricModalLoading.value = false
  }
}

const closeBiometricLogs = () => {
  biometricModalOpen.value = false
  biometricModalLoading.value = false
  biometricLogRows.value = []
  biometricLogOverrides.value = []
  biometricLogUser.value = null
}
</script>

<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] p-5 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
      <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">Attendance Reporting Deck</p>
          <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white lg:text-4xl">Biometric Bulk Report</h1>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200/90">
            Generate and print attendance forms for multiple users by month, office shift, department, and college.
          </p>
          <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex rounded-full bg-white/10 px-3 py-1 font-medium text-slate-100 ring-1 ring-inset ring-white/10">
              Period: {{ monthYearLabel }}
            </span>
            <span class="inline-flex rounded-full px-3 py-1 font-medium ring-1 ring-inset"
              :class="loading ? 'bg-amber-400/15 text-amber-100 ring-amber-300/30' : 'bg-emerald-400/15 text-emerald-100 ring-emerald-300/30'">
              {{ loading ? 'Generating Report...' : 'Ready to Generate' }}
            </span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:min-w-[460px]">
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Users</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ reportUsers.length }}</p>
            <p class="mt-1 text-xs text-slate-300">Matched records</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Selected</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ selectedCount }}</p>
            <p class="mt-1 text-xs text-slate-300">Ready to print</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Unselected</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ unselectedCount }}</p>
            <p class="mt-1 text-xs text-slate-300">Excluded from print</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Copies/User</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ copiesPerUser }}</p>
            <p class="mt-1 text-xs text-slate-300">Print multiplier</p>
          </div>
        </div>
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
      <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03] lg:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Report Filters</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set criteria to generate the attendance population.</p>
          </div>
          <Button @click="generateReport" size="sm" variant="primary" :className="'h-11 bg-sky-500 hover:bg-sky-600 text-white'">Generate</Button>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-5">
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Year</label>
            <select v-model.number="filters.year" class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
              <option v-for="year in yearOptions" :key="`year-${year}`" :value="year">{{ year }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Month</label>
            <select v-model.number="filters.month" class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
              <option v-for="month in monthOptions" :key="`month-${month.value}`" :value="month.value">{{ month.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Office Shift</label>
            <select v-model="filters.office_shift_id" class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
              <option value="">All</option>
              <option v-for="shift in officeShifts" :key="`report-shift-${shift.id}`" :value="String(shift.id)">{{ shift.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Department</label>
            <select v-model="filters.department_id" class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
              <option value="">All</option>
              <option v-for="department in departments" :key="`report-department-${department.id}`" :value="String(department.id)">{{ department.department_name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">College</label>
            <select v-model="filters.college_id" class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
              <option value="">All</option>
              <option v-for="college in colleges" :key="`report-college-${college.id}`" :value="String(college.id)">{{ college.college_long || college.college_short || `College #${college.id}` }}</option>
            </select>
          </div>
        </div>
      </div>

      <aside class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Print Options</h3>
        <div class="mt-3 space-y-3">
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Copies Per User</label>
            <select v-model.number="copiesPerUser" class="h-11 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
              <option v-for="n in 10" :key="`copies-${n}`" :value="n">{{ n }}</option>
            </select>
          </div>
          <button @click="printReport" type="button" class="inline-flex h-11 w-full items-center justify-center rounded-lg border border-sky-200 bg-sky-50 px-4 text-sm font-medium text-sky-700 transition hover:bg-sky-100 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/30">
            Print Selected
          </button>
          <p class="text-xs text-slate-500 dark:text-slate-400">Only selected users will be included in printing.</p>
        </div>
      </aside>
    </section>

    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
        <div class="text-sm text-slate-600 dark:text-slate-300">
          <span class="font-semibold text-slate-900 dark:text-white">{{ loading ? 'Generating...' : reportUsers.length }}</span> user(s) matched for {{ monthYearLabel }}
          <span v-if="!loading" class="ml-2">({{ selectedCount }} selected)</span>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="bg-slate-50 dark:bg-slate-900/60">
            <tr>
              <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                <input
                  type="checkbox"
                  :checked="allSelected"
                  @change="toggleSelectAll"
                  class="h-4 w-4"
                />
              </th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Office Shift</th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Department</th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">College</th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Biometrics</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr v-for="user in reportUsers" :key="`report-user-${user.id}`" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <td class="px-4 py-2 text-center text-sm">
                <input
                  v-model="selectedUserIds"
                  type="checkbox"
                  :value="user.id"
                  class="h-4 w-4"
                />
              </td>
              <td class="px-4 py-2 text-sm font-medium text-slate-800 dark:text-slate-100">{{ user.name }}</td>
              <td class="px-4 py-2 text-sm text-slate-700 dark:text-slate-200">{{ user.office_shift?.name || '-' }}</td>
              <td class="px-4 py-2 text-sm text-slate-700 dark:text-slate-200">{{ user.department || '-' }}</td>
              <td class="px-4 py-2 text-sm text-slate-700 dark:text-slate-200">{{ user.college || '-' }}</td>
              <td class="px-4 py-2 text-sm">
                <button
                  @click="openBiometricLogs(user)"
                  type="button"
                  class="rounded-md border border-sky-200 px-2.5 py-1 text-xs font-medium text-sky-700 transition hover:bg-sky-50 dark:border-sky-800/60 dark:text-sky-300 dark:hover:bg-sky-900/20"
                >
                  View All Logs
                </button>
              </td>
            </tr>
            <tr v-if="!reportUsers.length && !loading">
              <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">No report data yet. Apply filters and click Generate.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Modal v-if="biometricModalOpen" @close="closeBiometricLogs">
      <template #body>
        <div class="relative m-2 w-full max-w-5xl max-h-[92vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-800 dark:bg-slate-950 lg:p-6">
          <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_45%,_#0f766e_100%)] p-5 text-white shadow-sm dark:border-slate-800">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
              <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">Attendance Audit</p>
                <h4 class="mt-3 text-2xl font-semibold tracking-tight text-white">Raw Biometric Logs</h4>
                <p class="mt-2 text-sm text-slate-200/90">
                  {{ biometricLogUser?.name || '-' }} - {{ monthYearLabel }}
                </p>
                <p class="mt-2 text-xs text-slate-300/90">All entries are shown as-is, including duplicate IN/OUT punches.</p>
              </div>

              <div class="grid grid-cols-3 gap-3 sm:min-w-[340px]">
                <div class="rounded-2xl border border-white/10 bg-white/10 p-3 backdrop-blur-sm">
                  <p class="text-[10px] uppercase tracking-[0.24em] text-slate-300">Total</p>
                  <p class="mt-1 text-2xl font-semibold text-white">{{ mergedBiometricLogs.length }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 p-3 backdrop-blur-sm">
                  <p class="text-[10px] uppercase tracking-[0.24em] text-slate-300">Check In</p>
                  <p class="mt-1 text-2xl font-semibold text-white">{{ mergedBiometricLogs.filter(log => normalizeCheckType(log?.CHECKTYPE) === 'I').length }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 p-3 backdrop-blur-sm">
                  <p class="text-[10px] uppercase tracking-[0.24em] text-slate-300">Check Out</p>
                  <p class="mt-1 text-2xl font-semibold text-white">{{ mergedBiometricLogs.filter(log => normalizeCheckType(log?.CHECKTYPE) === 'O').length }}</p>
                </div>
              </div>
            </div>
          </section>

          <div class="mt-4 flex items-center justify-between gap-2">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Chronological Event Stream</p>
            <button
              @click="closeBiometricLogs"
              type="button"
              class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
            >
              Close
            </button>
          </div>

          <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
            <div class="max-h-[60vh] overflow-auto">
              <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-900/70">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date/Time</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr v-if="biometricModalLoading">
                    <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading biometric logs...</td>
                  </tr>
                  <tr v-else-if="!mergedBiometricLogs.length">
                    <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No biometric logs for selected user and period.</td>
                  </tr>
                  <tr v-else v-for="(log, index) in mergedBiometricLogs" :key="`log-${index}-${log.CHECKTIME}`" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                    <td class="px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-200">{{ index + 1 }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                      <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.2em]" :class="checkTypeBadgeClass(log.CHECKTYPE)">
                          {{ formatCheckTypeLabel(log.CHECKTYPE) }}
                        </span>
                        <span v-if="log._override_action" class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                          {{ log._override_action === 'add' ? 'added' : log._override_action }}
                        </span>
                      </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ formatLogDateTime(log.CHECKTIME) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>
    </Modal>

    <div class="hidden">
      <PrintableAttendance
        v-for="user in reportUsers"
        :key="`report-printable-${user.id}`"
        ref="printableRefs"
        :user="user"
        :selected-year="filters.year"
        :selected-month="filters.month"
        :attendance-records="user.attendance_records || []"
        :company-name="companySchoolName"
        :show-controls="false"
        :overrides="user._overrides || []"
      />
    </div>
  </div>
</template>
