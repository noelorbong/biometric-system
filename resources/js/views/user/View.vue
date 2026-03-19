<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import ProfileCard from './components/ProfileCard.vue'
import PrintableAttendance from './components/PrintableAttendance.vue'
import Modal from '@/components/common/Modal.vue'
import { useUserStore } from '@/store/UserStore'
import { useAppSettingStore } from '@/store/AppSettingStore'
import { useAuthStore } from '@/store/AuthStore'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const appSettingStore = useAppSettingStore()
const authStore = useAuthStore()
const { users } = storeToRefs(userStore)
const { user: authUser } = storeToRefs(authStore)
const { companySchoolName } = storeToRefs(appSettingStore)

const activeTab = ref('userinfo')
const currentDate = new Date()
const selectedYear = ref(currentDate.getFullYear())
const selectedMonth = ref(currentDate.getMonth() + 1)
const checkinouts = ref([])
const checkinoutLoading = ref(false)
const rawLogModalOpen = ref(false)
const rawLogModalDate = ref('')
const rawLogRows = ref([])

const userId = computed(() => Number(route.params.id))

const selectedUser = computed(() => {
  const fromUsers = users.value.find((item) => item.id === userId.value)
  if (fromUsers) {
    return fromUsers
  }

  if (Number(authUser.value?.id || 0) === userId.value) {
    return authUser.value
  }

  return null
})

const biometricData = computed(() => {
  if (!selectedUser.value) {
    return null
  }

  return selectedUser.value.biometric_info || selectedUser.value.biometricInfo || null
})

const heroFullName = computed(() => {
  const u = selectedUser.value
  if (!u) return '-'
  const first = u.profile?.first_name || ''
  const middle = u.profile?.middle_name || ''
  const last = u.profile?.last_name || ''
  const ext = u.profile?.name_extension || ''
  return [first, middle, last, ext].filter(Boolean).join(' ').trim() || u.name || '-'
})

const heroRole = computed(() => {
  const map = { 0: 'User', 1: 'Super Admin', 2: 'Region Admin', 3: 'SUC Admin', 4: 'Campus Admin', 5: 'College Admin', 6: 'Employee' }
  return map[selectedUser.value?.role] || 'User'
})

const canGoBackToUsers = computed(() => Number(authUser.value?.role ?? -1) === 1)

const heroDepartment = computed(() => {
  const u = selectedUser.value
  return u?.department_ref?.department_name || u?.departmentRef?.department_name || u?.department || (u?.department_id ? `#${u.department_id}` : 'None')
})

const shiftSchedules = computed(() => {
  const officeShift = selectedUser.value?.office_shift || selectedUser.value?.officeShift
  return officeShift?.schedules || []
})

const scheduleSlots = computed(() => {
  const slots = [...shiftSchedules.value].sort((a, b) => (a.sequence || 0) - (b.sequence || 0))
  if (!slots.length) {
    return [{ sequence: 1, time_in: null, time_out: null, is_next_day: false }]
  }

  return slots
})

const hasOvernightShift = computed(() => {
  return shiftSchedules.value.some((row) => {
    if (row?.is_next_day) {
      return true
    }

    const timeIn = String(row?.time_in || '')
    const timeOut = String(row?.time_out || '')
    return timeIn && timeOut && timeOut < timeIn
  })
})

const overnightEndMinute = computed(() => {
  return shiftSchedules.value
    .filter((row) => Boolean(row?.is_next_day))
    .map((row) => {
      const [h, m] = String(row?.time_out || '00:00:00').split(':')
      return (Number(h) * 60) + Number(m)
    })
    .sort((a, b) => a - b)
    .at(-1) ?? 0
})

const resolveLogicalDateKey = (value) => {
  const dateTime = new Date(value)
  if (Number.isNaN(dateTime.getTime())) {
    return null
  }

  const logicalDate = new Date(dateTime)
  if (hasOvernightShift.value) {
    const minutes = (dateTime.getHours() * 60) + dateTime.getMinutes()
    if (minutes <= overnightEndMinute.value) {
      logicalDate.setDate(logicalDate.getDate() - 1)
    }
  }

  return `${logicalDate.getFullYear()}-${String(logicalDate.getMonth() + 1).padStart(2, '0')}-${String(logicalDate.getDate()).padStart(2, '0')}`
}

const rawLogsByDate = computed(() => {
  const grouped = new Map()
  const records = [...checkinouts.value].sort((a, b) => new Date(a.CHECKTIME) - new Date(b.CHECKTIME))

  records.forEach((record) => {
    const dateKey = resolveLogicalDateKey(record.CHECKTIME)
    if (!dateKey) {
      return
    }

    if (!grouped.has(dateKey)) {
      grouped.set(dateKey, [])
    }

    grouped.get(dateKey).push(record)
  })

  return grouped
})

const attendanceRows = computed(() => {
  const grouped = new Map()

  const records = [...checkinouts.value].sort((a, b) => new Date(a.CHECKTIME) - new Date(b.CHECKTIME))

  records.forEach((record) => {
    const dateKey = resolveLogicalDateKey(record.CHECKTIME)
    if (!dateKey) {
      return
    }
    if (!grouped.has(dateKey)) {
      grouped.set(dateKey, [])
    }
    grouped.get(dateKey).push(record)
  })

  const buildAttendanceRow = (date, recordsInDay = []) => {
      const sorted = recordsInDay
        .sort((a, b) => new Date(a.CHECKTIME) - new Date(b.CHECKTIME))
      const normalizedPunches = []

      sorted.forEach((item) => {
        const type = String(item.CHECKTYPE || '').toUpperCase()
        if (type !== 'I' && type !== 'O') {
          return
        }

        const lastPunch = normalizedPunches[normalizedPunches.length - 1]
        if (!lastPunch || lastPunch.type !== type) {
          normalizedPunches.push({ type, time: item.CHECKTIME })
          return
        }

        // Consecutive same-type punches are common in biometric logs.
        // Keep earliest IN and latest OUT within each consecutive group.
        if (type === 'O') {
          lastPunch.time = item.CHECKTIME
        }
      })

      const sessions = []
      let currentSession = null

      normalizedPunches.forEach((punch) => {
        if (punch.type === 'I') {
          if (!currentSession) {
            currentSession = { check_in: punch.time, check_out: null }
            return
          }

          if (currentSession.check_in && !currentSession.check_out) {
            sessions.push(currentSession)
            currentSession = { check_in: punch.time, check_out: null }
            return
          }

          if (!currentSession.check_in && currentSession.check_out) {
            sessions.push(currentSession)
            currentSession = { check_in: punch.time, check_out: null }
            return
          }

          currentSession = { check_in: punch.time, check_out: null }
          return
        }

        if (!currentSession) {
          currentSession = { check_in: null, check_out: punch.time }
          return
        }

        if (currentSession.check_in && !currentSession.check_out) {
          currentSession.check_out = punch.time
          sessions.push(currentSession)
          currentSession = null
          return
        }

        if (!currentSession.check_in && currentSession.check_out) {
          currentSession.check_out = punch.time
          return
        }

        sessions.push(currentSession)
        currentSession = { check_in: null, check_out: punch.time }
      })

      if (currentSession && (currentSession.check_in || currentSession.check_out)) {
        sessions.push(currentSession)
      }

      const slots = scheduleSlots.value.map(() => ({ check_in: null, check_out: null }))

      sessions.slice(0, slots.length).forEach((session, index) => {
        slots[index] = {
          check_in: session.check_in,
          check_out: session.check_out,
        }
      })

      return {
        date,
        slots,
      }
    }

  const totalDaysInMonth = new Date(selectedYear.value, selectedMonth.value, 0).getDate()
  const rows = []

  for (let day = 1; day <= totalDaysInMonth; day += 1) {
    const dateKey = `${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    rows.push(buildAttendanceRow(dateKey, grouped.get(dateKey) || []))
  }

  return rows.sort((a, b) => new Date(a.date) - new Date(b.date))
})

onMounted(async () => {
  await appSettingStore.loadSettings()

  if (!authUser.value?.id) {
    await authStore.loadUser()
  }

  if (Number(authUser.value?.role) === 1 && !users.value.length) {
    await userStore.loadUsers()
  }

  await loadCheckinouts()
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
  const startYear = currentDate.getFullYear() - 10
  const endYear = currentDate.getFullYear() + 1
  const values = []

  for (let year = endYear; year >= startYear; year -= 1) {
    values.push(year)
  }

  return values
})

const loadCheckinouts = async () => {
  if (!userId.value) {
    checkinouts.value = []
    return
  }

  checkinoutLoading.value = true
  try {
    const resp = await axios.post('/api/user/checkinout', {
      user_id: userId.value,
      year: selectedYear.value,
      month: selectedMonth.value,
    })

    checkinouts.value = resp?.data?.checkinouts || []
  } catch (error) {
    console.log(error)
    checkinouts.value = []
  } finally {
    checkinoutLoading.value = false
  }
}

watch([selectedYear, selectedMonth, userId], async () => {
  await loadCheckinouts()
})

const formatDateTime = (value) => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const formatDateOnly = (value) => {
  if (!value) {
    return '-'
  }

  if (/^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
    const [year, month, day] = String(value).split('-').map(Number)
    const date = new Date(year, month - 1, day)
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
    })
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  })
}

const formatTimeOnly = (value) => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

const formatLogDateTime = (value) => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

const openRawLogs = (date) => {
  rawLogModalDate.value = date
  rawLogRows.value = [...(rawLogsByDate.value.get(date) || [])]
  rawLogModalOpen.value = true
}

const closeRawLogs = () => {
  rawLogModalOpen.value = false
  rawLogModalDate.value = ''
  rawLogRows.value = []
}

const buildPrintableAttendanceRecords = () => {
  return attendanceRows.value.map((row) => {
    const [year, month, day] = String(row.date).split('-').map(Number)
    const dateObj = new Date(year, month - 1, day)

    const amIn = row.slots[0]?.check_in ? formatTimeOnly(row.slots[0].check_in) : ''
    const amOut = row.slots[0]?.check_out ? formatTimeOnly(row.slots[0].check_out) : ''
    const pmIn = row.slots[1]?.check_in ? formatTimeOnly(row.slots[1].check_in) : ''
    const pmOut = row.slots[1]?.check_out ? formatTimeOnly(row.slots[1].check_out) : ''

    return {
      date: row.date,
      dateDisplay: dateObj.toLocaleDateString('en-US', { month: 'short', day: '2-digit' }),
      am_in: amIn,
      am_out: amOut,
      pm_in: pmIn,
      pm_out: pmOut,
      undertimeHrs: '',
      undertimeMin: '',
    }
  })
}
</script>

<template>
  <div class="space-y-6">

    <!-- ── HERO ── -->
    <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] p-6 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
      <!-- decorative dots -->
      <div class="pointer-events-none absolute inset-0 opacity-10"
        style="background-image:radial-gradient(circle,white 1px,transparent 1px);background-size:28px 28px"></div>

      <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
        <!-- Avatar + Name -->
        <div class="flex items-center gap-4">
          <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-2xl border-2 border-white/30 shadow-lg">
            <img
              :src="selectedUser?.thumbnail || '/images/extras/add-image.png'"
              alt="user"
              class="h-full w-full object-cover"
            />
          </div>
          <div>
            <h1 class="text-2xl font-bold leading-tight text-white">{{ heroFullName }}</h1>
            <p class="mt-0.5 text-sm text-slate-200/90">{{ selectedUser?.email || 'No email' }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
              <span class="rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-medium text-white">{{ heroRole }}</span>
              <span
                :class="selectedUser?.status ? 'border-emerald-300/40 bg-emerald-400/20 text-emerald-100' : 'border-rose-300/40 bg-rose-400/20 text-rose-100'"
                class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
              >
                {{ selectedUser?.status ? 'Active' : 'Inactive' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Stats + Back -->
        <div class="flex flex-col items-start gap-3 md:items-end">
          <button
            v-if="canGoBackToUsers"
            type="button"
            @click="router.push({ name: 'User' })"
            class="flex items-center gap-1.5 rounded-xl border border-sky-300/30 bg-sky-400/20 px-4 py-2 text-sm font-medium text-sky-50 backdrop-blur-sm transition hover:bg-sky-400/30"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Users
          </button>
          <div class="flex flex-wrap gap-2">
            <div class="rounded-xl bg-white/10 px-4 py-2 text-center backdrop-blur-sm ring-1 ring-inset ring-white/10">
              <p class="text-xs text-slate-300">Department</p>
              <p class="text-sm font-semibold text-white">{{ heroDepartment }}</p>
            </div>
            <div class="rounded-xl bg-white/10 px-4 py-2 text-center backdrop-blur-sm ring-1 ring-inset ring-white/10">
              <p class="text-xs text-slate-300">Shift</p>
              <p class="text-sm font-semibold text-white">{{ selectedUser?.office_shift?.name || 'None' }}</p>
            </div>
            <div class="rounded-xl bg-white/10 px-4 py-2 text-center backdrop-blur-sm ring-1 ring-inset ring-white/10">
              <p class="text-xs text-slate-300">Biometric ID</p>
              <p class="text-sm font-semibold text-white">{{ biometricData?.user_id || '-' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- User not found -->
    <div
      v-if="!selectedUser"
      class="rounded-xl border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800 dark:border-yellow-700 dark:bg-yellow-950/20 dark:text-yellow-200"
    >
      User not found. Make sure the user list has been loaded.
    </div>

    <template v-else>
      <!-- ── TABS ── -->
      <div class="flex gap-1 rounded-[24px] border border-slate-200 bg-white p-1.5 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <button
          type="button"
          @click="activeTab = 'userinfo'"
          :class="[
            'flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-all',
            activeTab === 'userinfo'
              ? 'bg-sky-500 text-white shadow-sm'
              : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
          ]"
        >User Info</button>
        <button
          type="button"
          @click="activeTab = 'biometrics'"
          :class="[
            'flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-all',
            activeTab === 'biometrics'
              ? 'bg-sky-500 text-white shadow-sm'
              : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
          ]"
        >Attendance</button>
        <button
          type="button"
          @click="activeTab = 'print'"
          :class="[
            'flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-all',
            activeTab === 'print'
              ? 'bg-sky-500 text-white shadow-sm'
              : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
          ]"
        >Print Record</button>
      </div>

      <!-- ── TAB: USER INFO ── -->
      <div v-if="activeTab === 'userinfo'">
        <ProfileCard :user="selectedUser" />
      </div>

      <!-- ── TAB: ATTENDANCE ── -->
      <div v-if="activeTab === 'biometrics'" class="space-y-4">
        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-4 rounded-[24px] border border-slate-200 bg-white px-5 py-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Year</label>
            <select
              v-model.number="selectedYear"
              class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
              <option v-for="year in yearOptions" :key="`year-${year}`" :value="year">{{ year }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Month</label>
            <select
              v-model.number="selectedMonth"
              class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
              <option v-for="month in monthOptions" :key="`month-${month.value}`" :value="month.value">{{ month.label }}</option>
            </select>
          </div>
          <div v-if="checkinoutLoading" class="flex items-center gap-2 pb-0.5 text-sm text-slate-500 dark:text-slate-400">
            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            Loading...
          </div>
        </div>

        <!-- Attendance table -->
        <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
          <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h3 class="text-base font-semibold text-slate-800 dark:text-white/90">Attendance Record</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
              {{ monthOptions.find(m => m.value === selectedMonth)?.label }} {{ selectedYear }}
            </p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
              <thead>
                <tr class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                  <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Day</th>
                  <template v-for="(slot, index) in scheduleSlots" :key="`head-${slot.sequence || index}`">
                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      Check In{{ scheduleSlots.length > 1 ? ` ${index + 1}` : '' }}
                    </th>
                    <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      Check Out{{ scheduleSlots.length > 1 ? ` ${index + 1}` : '' }}
                    </th>
                  </template>
                  <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Logs</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-if="checkinoutLoading">
                  <td :colspan="2 + (scheduleSlots.length * 2)" class="px-5 py-10 text-center text-sm text-slate-400">
                    Loading attendance data…
                  </td>
                </tr>
                <tr v-else-if="!attendanceRows.length">
                  <td :colspan="2 + (scheduleSlots.length * 2)" class="px-5 py-10 text-center text-sm text-slate-400">
                    No attendance records for selected month.
                  </td>
                </tr>
                <tr
                  v-for="row in attendanceRows"
                  :key="row.date"
                  class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/30"
                >
                  <td class="px-5 py-3 text-center">
                    <span class="text-sm font-semibold text-slate-800 dark:text-white">{{ formatDateOnly(row.date) }}</span>
                  </td>
                  <template v-for="(slot, index) in row.slots" :key="`slot-${row.date}-${index}`">
                    <td class="px-5 py-3 text-center">
                      <span
                        :class="slot.check_in ? 'font-semibold text-emerald-600 dark:text-emerald-400' : 'text-slate-300 dark:text-slate-600'"
                        class="text-sm"
                      >{{ formatTimeOnly(slot.check_in) }}</span>
                    </td>
                    <td class="px-5 py-3 text-center">
                      <span
                        :class="slot.check_out ? 'font-semibold text-rose-500 dark:text-rose-400' : 'text-slate-300 dark:text-slate-600'"
                        class="text-sm"
                      >{{ formatTimeOnly(slot.check_out) }}</span>
                    </td>
                  </template>
                  <td class="px-5 py-3 text-center">
                    <button
                      type="button"
                      @click="openRawLogs(row.date)"
                      class="rounded-lg border border-sky-200 px-2.5 py-1 text-xs font-medium text-sky-700 transition hover:bg-sky-50 dark:border-sky-800/60 dark:text-sky-300 dark:hover:bg-sky-900/20"
                    >
                      View Logs
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Raw Logs Modal -->
        <Modal v-if="rawLogModalOpen" @close="closeRawLogs">
          <template #body>
            <div class="relative m-2 w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl bg-white p-6 dark:bg-slate-900">
              <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                  <h4 class="text-lg font-semibold text-slate-800 dark:text-white/90">Raw Biometric Logs</h4>
                  <p class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ formatDateOnly(rawLogModalDate) }}</p>
                  <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">All entries shown as-is, including duplicates.</p>
                </div>
                <button
                  @click="closeRawLogs"
                  type="button"
                  class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                >Close</button>
              </div>

              <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="max-h-[55vh] overflow-auto">
                  <table class="min-w-full">
                    <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800">
                      <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">#</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Type</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date / Time</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                      <tr v-if="!rawLogRows.length">
                        <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">No biometric logs for this day.</td>
                      </tr>
                      <tr
                        v-else
                        v-for="(log, index) in rawLogRows"
                        :key="`user-log-${index}-${log.CHECKTIME}`"
                        class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40"
                      >
                        <td class="px-4 py-2.5 text-sm text-slate-400 dark:text-slate-500">{{ index + 1 }}</td>
                        <td class="px-4 py-2.5">
                          <span
                            :class="log.CHECKTYPE === 'I'
                              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                              : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'"
                            class="rounded-md px-2 py-0.5 text-xs font-bold"
                          >
                            {{ log.CHECKTYPE === 'I' ? 'IN' : log.CHECKTYPE === 'O' ? 'OUT' : log.CHECKTYPE || '-' }}
                          </span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200">{{ formatLogDateTime(log.CHECKTIME) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </template>
        </Modal>
      </div>

      <!-- ── TAB: PRINT ── -->
      <div v-if="activeTab === 'print'" class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <PrintableAttendance
          :user="selectedUser"
          :selected-year="selectedYear"
          :selected-month="selectedMonth"
          :attendance-records="buildPrintableAttendanceRecords()"
          :company-name="companySchoolName"
        />
      </div>
    </template>
  </div>
</template>
