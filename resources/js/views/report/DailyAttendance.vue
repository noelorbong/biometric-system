<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import flatPickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'
import { useAuthStore } from '@/store/AuthStore'

const auth = useAuthStore()
const date = ref('')
const datePickerConfig = {
  mode: 'single',
  dateFormat: 'Y-m-d',
  altInput: true,
  altFormat: 'F j, Y',
  disableMobile: true,
}
const shiftId = ref('')
const report = ref(null)
const shifts = ref([])
const loading = ref(false)
const error = ref('')
const issuesOnly = ref(false)
const search = ref('')
const sort = ref('late')
const expanded = ref(null)
let requestId = 0
let controller
const initializing = ref(true)

async function load() {
  const id = ++requestId
  controller?.abort()
  controller = new AbortController()
  loading.value = true
  error.value = ''
  report.value = null
  expanded.value = null
  try {
    const { data } = await axios.post('/api/report/daily-attendance', {
      date: date.value || null,
      office_shift_id: shiftId.value || null,
    }, { signal: controller.signal })
    if (id !== requestId) return
    report.value = data
    shifts.value = data.office_shifts
    if (!date.value) date.value = data.date
  } catch (e) {
    if (id !== requestId || axios.isCancel(e)) return
    error.value = e.response?.data?.message || 'Unable to load attendance. Please try again.'
    if (e.response?.status === 401) auth.clearAccount()
  } finally {
    if (id === requestId) loading.value = false
  }
}

watch([date, shiftId], () => {
  if (initializing.value) return
  if (date.value) {
    load()
  } else {
    ++requestId
    controller?.abort()
    loading.value = false
    report.value = null
    error.value = 'Select a date to view attendance.'
  }
})
onMounted(async () => { await load(); initializing.value = false })
onBeforeUnmount(() => { ++requestId; controller?.abort() })

const clock = (value) => {
  if (!value) return '—'
  const time = value.includes(' ') ? value.split(' ')[1] : value
  const [hours, minutes] = time.split(':').map(Number)
  return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${hours >= 12 ? 'PM' : 'AM'}`
}
const columnKey = (period) => `${period.next_day ? '1' : '0'}-${period.scheduled_out}`
const selectedShifts = computed(() => shifts.value.filter(s => !shiftId.value || String(s.id) === String(shiftId.value)))
const columns = computed(() => {
  const slots = new Map()
  for (const employee of report.value?.employees || []) {
    for (const period of employee.periods) {
      const key = columnKey(period)
      slots.set(key, { key, end: period.scheduled_out, nextDay: period.next_day })
    }
  }
  return [...slots.values()].sort((a, b) => a.key.localeCompare(b.key))
})
const employees = computed(() => {
  const term = search.value.trim().toLowerCase()
  return (report.value?.employees || []).filter(row =>
    (!term || `${row.name} ${row.shift_name}`.toLowerCase().includes(term)) &&
    (!issuesOnly.value || row.late_minutes || row.undertime_minutes || row.missing_count)
  ).map(row => ({ ...row, cells: columns.value.map(column => row.periods.find(p => columnKey(p) === column.key)) }))
    .sort((a, b) => {
      if (sort.value === 'in') return (b.first_in || '').localeCompare(a.first_in || '') || a.name.localeCompare(b.name)
      if (sort.value === 'name') return a.name.localeCompare(b.name)
      return b.late_minutes - a.late_minutes || a.name.localeCompare(b.name)
    })
})
const cards = computed(() => [
  { label: 'Employees late', value: report.value?.summary.late_count, unit: '' },
  { label: 'Late minutes', value: report.value?.summary.late_minutes, unit: 'min' },
  { label: 'Employees with undertime', value: report.value?.summary.undertime_count, unit: '' },
  { label: 'Undertime minutes', value: report.value?.summary.undertime_minutes, unit: 'min' },
])
</script>

<template>
  <div class="space-y-3 text-slate-800 dark:text-slate-200">
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-lg font-semibold dark:text-white">Daily Attendance Monitoring</h1>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Review arrivals, departures, and attendance issues by work period.</p>
        </div>
        <button type="button" @click="load" :disabled="loading" class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-medium text-sky-700 disabled:opacity-50 dark:border-sky-900 dark:bg-sky-900/20 dark:text-sky-300">{{ loading ? 'Loading…' : 'Refresh' }}</button>
      </div>
      <div class="mt-4 flex flex-wrap items-end gap-3">
        <label class="text-xs font-medium">Date
          <flat-pickr v-model="date" :config="datePickerConfig" :disabled="initializing && loading" placeholder="Select date" class="mt-1 block h-9 rounded-md border border-slate-300 bg-transparent px-3 text-xs dark:border-slate-700" />
        </label>
        <label class="text-xs font-medium">Office shift
          <select v-model="shiftId" :disabled="initializing && loading" class="mt-1 block h-9 min-w-44 rounded-md border border-slate-300 bg-white px-3 text-xs dark:border-slate-700 dark:bg-slate-900">
            <option value="">All shifts</option>
            <option v-for="shift in shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option>
          </select>
        </label>
        <p v-if="report" class="pb-2 text-xs text-slate-500">Updated {{ clock(report.generated_at) }} · {{ report.timezone }}</p>
      </div>
      <div class="mt-4 grid grid-cols-2 gap-2 lg:grid-cols-4">
        <div v-for="card in cards" :key="card.label" class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900/70">
          <p class="text-xs text-slate-500 dark:text-slate-400">{{ card.label }}</p>
          <p class="mt-1 text-xl font-semibold tabular-nums">{{ card.value ?? '—' }} <span class="text-xs font-normal">{{ card.unit }}</span></p>
        </div>
      </div>
      <p class="mt-2 text-xs text-slate-500">Counts are unique employees for the selected date and shift. Minutes include all their work periods.</p>
    </section>

    <div v-if="error" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }} <button type="button" @click="load" class="ml-2 underline">Retry</button></div>
    <div v-if="report?.holidays.length" class="rounded-lg bg-sky-50 p-3 text-xs text-sky-800 dark:bg-sky-900/20 dark:text-sky-200">
      <span v-for="holiday in report.holidays" :key="holiday.id" class="mr-3">{{ holiday.name }} · {{ holiday.duration.replaceAll('_', ' ') }} · {{ holiday.is_working_day ? 'Working' : 'Exempt from late / undertime' }}</span>
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]" :aria-busy="loading">
      <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-3 dark:border-slate-800">
        <h2 class="text-sm font-semibold">Employees <span v-if="report" class="font-normal text-slate-500">({{ employees.length }} / {{ report.employees.length }})</span></h2>
        <input v-model="search" type="search" aria-label="Search employees" placeholder="Search employee or shift…" class="h-9 rounded-md border border-slate-300 bg-transparent px-3 text-xs dark:border-slate-700" />
        <label class="flex items-center gap-2 text-xs"><input v-model="issuesOnly" type="checkbox" class="rounded border-slate-300" /> Issues only</label>
        <label class="ml-auto flex items-center gap-2 text-xs">Sort
          <select v-model="sort" class="h-9 rounded-md border border-slate-300 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-900">
            <option value="late">Late minutes ↓</option><option value="in">First IN ↓</option><option value="name">Name A–Z</option>
          </select>
        </label>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-xs">
          <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
            <tr>
              <th rowspan="2" class="px-3 py-2">Name</th><th rowspan="2" class="px-3 py-2">Shift</th>
              <th v-for="column in columns" :key="column.key" colspan="2" class="border-l border-slate-200 px-3 py-2 text-center font-medium dark:border-slate-800">Until {{ clock(column.end) }}{{ column.nextDay ? ' (+1 day)' : '' }}</th>
              <th rowspan="2" class="px-3 py-2 text-right">Late min</th><th rowspan="2" class="px-3 py-2 text-right">Undertime min</th><th rowspan="2" class="px-3 py-2">Status</th>
            </tr>
            <tr><template v-for="column in columns" :key="column.key"><th class="border-l border-slate-200 px-3 pb-2 dark:border-slate-800">IN</th><th class="px-3 pb-2">OUT</th></template></tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            <template v-for="row in employees" :key="row.id">
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="px-3 py-3"><button type="button" @click="expanded = expanded === row.id ? null : row.id" :aria-expanded="expanded === row.id" class="text-left font-medium text-sky-700 hover:underline dark:text-sky-300">{{ row.name }}</button></td>
                <td class="whitespace-nowrap px-3 py-3">{{ row.shift_name }}<span class="block text-[10px] text-slate-500">{{ row.schedule_name }}</span></td>
                <template v-for="(period, index) in row.cells" :key="index">
                  <td v-for="direction in ['in', 'out']" :key="direction" class="whitespace-nowrap px-3 py-3 tabular-nums" :class="direction === 'in' ? 'border-l border-slate-100 dark:border-slate-800' : ''">
                    <template v-if="period">
                      <span :title="`Scheduled ${clock(period.scheduled_in)} – ${clock(period.scheduled_out)}${period.next_day ? ' next day' : ''}`" :class="(direction === 'in' ? period.late_minutes : period.undertime_minutes) ? 'font-semibold text-rose-600 dark:text-rose-400' : ''">{{ period[direction] ? clock(period[direction]) : period[`${direction}_state`] }}</span>
                      <span v-if="direction === 'in' && period.late_minutes" class="ml-1 text-[10px] text-rose-600 dark:text-rose-400">⚠ +{{ period.late_minutes }}m</span>
                      <span v-if="direction === 'out' && period.undertime_minutes" class="ml-1 text-[10px] text-rose-600 dark:text-rose-400">↓ −{{ period.undertime_minutes }}m</span>
                      <span v-if="period.exempt" class="ml-1 text-[10px] text-sky-600">Exempt</span>
                    </template><span v-else class="text-slate-400">—</span>
                  </td>
                </template>
                <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ row.late_minutes }}</td><td class="px-3 py-3 text-right font-semibold tabular-nums">{{ row.undertime_minutes }}</td>
                <td class="whitespace-nowrap px-3 py-3"><span class="rounded bg-slate-100 px-2 py-1 text-[10px] dark:bg-slate-800" :class="row.missing_count ? 'text-amber-700 dark:text-amber-300' : ''">{{ row.status }}</span></td>
              </tr>
              <tr v-if="expanded === row.id"><td :colspan="5 + columns.length * 2" class="bg-slate-50 px-4 py-3 dark:bg-slate-900/50">
                <p class="font-medium">Schedule: {{ row.periods.map(p => `${clock(p.scheduled_in)} – ${clock(p.scheduled_out)}${p.next_day ? ' (+1 day)' : ''}`).join(' · ') || row.status }}</p>
                <p v-if="row.holiday_credit_minutes" class="mt-2">Holiday / suspension credit: {{ row.holiday_credit_minutes / 60 }} hours</p>
                <p class="mt-2">Punches on selected date: <span v-for="(punch, index) in row.punches" :key="index" class="mr-3 inline-block">{{ clock(punch.time) }} {{ punch.type }}{{ punch.corrected ? ' (corrected)' : '' }}</span><span v-if="!row.punches.length">None</span></p>
                <p v-if="row.missing_count" class="mt-2 text-amber-700 dark:text-amber-300">Missing or inconsistent punches need review. They are not automatically counted as absence or undertime.</p>
              </td></tr>
            </template>
            <tr v-if="!employees.length"><td :colspan="5 + columns.length * 2" class="px-4 py-8 text-center text-slate-500">{{ loading ? 'Loading attendance…' : error ? 'Attendance unavailable.' : 'No employees match these filters.' }}</td></tr>
          </tbody>
        </table>
      </div>
      <div class="space-y-1 border-t border-slate-200 p-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
        <p>⚠ Late · ↓ Undertime · — Work period not assigned. Click an employee to view their schedule and punches.</p>
        <p>Late minutes start at scheduled IN. Schedule grace settings correct punch types only. Undertime is calculated after the work period ends.</p>
        <p>Reports use effective-dated schedules and weekly exceptions. Flexible shifts and employees without schedules are not assessed for lateness. Leave and rest-day rules are not configured.</p>
      </div>
    </section>
  </div>
</template>
