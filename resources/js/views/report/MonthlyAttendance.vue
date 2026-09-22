<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import flatPickr from 'vue-flatpickr-component'
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect'
import 'flatpickr/dist/flatpickr.css'
import 'flatpickr/dist/plugins/monthSelect/style.css'
import { useAuthStore } from '@/store/AuthStore'
import AttendanceLogEditor from '@/components/common/AttendanceLogEditor.vue'
import AbsencesSummary from './AbsencesSummary.vue'

const auth = useAuthStore()
const route = useRoute()
const activeTab = ref(route.query.tab === 'absences' ? 'absences' : 'attendance')
const month = ref('')
const shiftId = ref('')
const report = ref(null)
const shifts = ref([])
const loading = ref(false)
const initializing = ref(true)
const error = ref('')
const search = ref('')
const issuesOnly = ref(false)
const sort = ref('late_count')
const grid = ref(null)
const editorContext = ref(null)
const notice = ref('')
const pickerConfig = {
  disableMobile: true,
  plugins: [new monthSelectPlugin({ dateFormat: 'Y-m', altFormat: 'F Y' })],
  altInput: true,
}
let requestId = 0
let controller

async function load() {
  const id = ++requestId
  controller?.abort()
  controller = new AbortController()
  loading.value = true
  notice.value = ''
  error.value = ''
  report.value = null
  try {
    const { data } = await axios.post('/api/report/monthly-attendance', {
      month: month.value || null, office_shift_id: shiftId.value || null,
    }, { signal: controller.signal })
    if (id !== requestId) return
    report.value = data
    shifts.value = data.office_shifts
    if (!month.value) month.value = data.month
  } catch (e) {
    if (id !== requestId || axios.isCancel(e)) return
    error.value = e.response?.data?.message || 'Unable to load the monthly report. Please try again.'
    if (e.response?.status === 401) auth.clearAccount()
  } finally {
    if (id === requestId) loading.value = false
  }
}
watch([month, shiftId], () => {
  if (initializing.value) return
  if (month.value) load()
  else {
    ++requestId
    controller?.abort()
    loading.value = false
    report.value = null
    error.value = 'Select a month to view the report.'
  }
})
onMounted(async () => { await load(); initializing.value = false })
onBeforeUnmount(() => { ++requestId; controller?.abort() })

const keyFor = period => period.next_day ? `1-${period.scheduled_out}` : period.scheduled_out === '12:00' ? 'AM' : period.scheduled_in === '13:00' ? 'PM' : `0-${period.scheduled_out}`
const selectedShiftName = computed(() => shifts.value.find(s => String(s.id) === String(shiftId.value))?.name || 'All shifts')
const periods = computed(() => {
  const columns = new Map()
  for (const employee of report.value?.employees || []) {
    for (const day of employee.days) {
      for (const period of day.periods) {
        const key = keyFor(period)
        columns.set(key, { key, end: period.scheduled_out, nextDay: period.next_day,
          label: key === 'AM' || key === 'PM' ? key : period.next_day ? period.scheduled_out + ' +1' : period.scheduled_out < '12:00' ? 'Early' : period.scheduled_out })
      }
    }
  }
  if (!columns.size) return [{ key: 'AM', end: '12:00', label: 'AM' }, { key: 'PM', end: '17:00', label: 'PM' }]
  return [...columns.values()].sort((a, b) => Number(a.nextDay || false) - Number(b.nextDay || false) || a.end.localeCompare(b.end))
})
const excludedDate = day => report.value?.employees.length ? report.value.employees.every(employee => {
  const record = employee.days.find(d => d.date === day.date)
  return record?.rest_day || (record?.working_day == null && day.weekend)
}) : day.weekend
const dayColumns = computed(() => (report.value?.days || []).flatMap(day => periods.value.flatMap(period =>
  ['in', 'out'].map(direction => ({ day, period, direction, key: `${day.date}-${period.key}-${direction}` }))
)))
const gridColumns = computed(() => (report.value?.days || []).flatMap(day => excludedDate(day)
  ? [{ key: `${day.date}-weekend`, day, weekend: true }]
  : dayColumns.value.filter(column => column.day.date === day.date)
))
const statusForMissing = (period, day, direction) => {
  const oppositeDirection = direction === 'in' ? 'out' : 'in'

  return period?.[`${direction}_state`] === 'Missing'
    && period?.[`${oppositeDirection}_state`] === 'Missing'
    && !day.future
    && !day.weekend
    && !period.exempt
}
const rows = computed(() => {
  const term = search.value.trim().toLowerCase()
  return (report.value?.employees || []).filter(row =>
    (!term || `${row.name} ${row.shift_name}`.toLowerCase().includes(term)) && (!issuesOnly.value || row.total_minutes > 0)
  ).map(row => {
    const days = new Map(row.days.map(day => [day.date, day]))
    const cells = dayColumns.value.map(column => {
        const day = days.get(column.day.date)
        const period = day?.periods.find(p => keyFor(p) === column.period.key)
        const restDay = Boolean(day?.rest_day || (day?.working_day == null && column.day.weekend))
        const value = restDay ? 0 : period?.[column.direction === 'in' ? 'late_minutes' : 'undertime_minutes'] || 0
        const holiday = (day?.holidays || column.day.holidays).some(h => !h.is_working_day && (h.duration === 'full_day' || (period && ((h.duration === 'morning' && period.scheduled_in < '12:00') || (h.duration === 'afternoon' && period.scheduled_in >= '12:00')))))
        const missing = !restDay && statusForMissing(period, { ...column.day, weekend: false }, column.direction)
        const absence = day?.absence || null
        const status = period ? period[`${column.direction}_state`] : day?.status || 'Not assigned'
        const filedAbsence = status === 'Filed absence'
        const unfiledAbsence = status === 'Unfiled absence'
        const display = holiday ? 'H' : restDay ? '?' : filedAbsence ? 'A' : missing ? 'M' : value || ''
        const description = holiday ? `Holiday exemption: ${(day?.holidays || column.day.holidays).map(h => h.name).join(', ')}` : restDay ? 'Rest day excluded from monthly totals' : filedAbsence ? `Filed ${absence?.duration?.replace('_', ' ')} absence (exempt)` : unfiledAbsence ? `Unfiled ${absence?.duration?.replace('_', ' ')} absence; ${value} min ${column.direction === 'in' ? 'late' : 'undertime'}` : value ? `${value} min ${column.direction === 'in' ? 'late' : 'undertime'}`
          : period?.exempt ? 'Holiday exemption' : status === 'Recorded' ? 'No late / undertime assessed' : status
        return {
          key: column.key, value, missing, holiday, filedAbsence, unfiledAbsence, display, restDay, weekend: restDay, future: column.day.future,
          date: column.day.date, direction: column.direction, label: column.period.label, period,
          absence,
          overnight: day?.periods.some(p => p.next_day) || false,
          exempt: period?.exempt, title: `${column.day.date} · ${column.period.label} ${column.direction.toUpperCase()} · ${description}${day?.schedule_name ? ` ? ${day.schedule_name}` : ''}${day?.holiday_credit_minutes ? ` ? ${day.holiday_credit_minutes / 60} credited hours that day` : ''}`
        }
      })
    const cellsByKey = new Map(cells.map(cell => [cell.key, cell]))
    return {
      ...row,
      cells,
      gridCells: gridColumns.value.map(column => column.weekend
        ? { ...column, title: `${column.day.date} (${column.day.weekday}) · Weekend excluded from monthly totals` }
        : cellsByKey.get(column.key)),
    }
  }).sort((a, b) => sort.value === 'name' ? a.name.localeCompare(b.name)
    : b[sort.value] - a[sort.value] || b.total_minutes - a.total_minutes || a.name.localeCompare(b.name))
})
const totals = computed(() => rows.value.reduce((sum, row) => ({
  minutes: sum.minutes + row.total_minutes, late: sum.late + row.late_count, under: sum.under + row.undertime_count,
}), { minutes: 0, late: 0, under: 0 }))
function openCell(row, cell) {
  editorContext.value = { ...cell, userId: row.id, name: row.name, shiftName: row.shift_name }
}
async function logSaved() {
  editorContext.value = null
  await load()
  notice.value = report.value ? 'Attendance log saved. Monthly totals have been recalculated.' : 'Attendance log saved. Refresh the report to see the updated totals.'
}
const reportNote = computed(() => `${selectedShiftName.value}. Each work period counts separately. IN = late minutes; OUT = undertime minutes. ${report.value?.provisional ? 'Provisional: the reporting month or its final overnight shift is not complete. ' : ''}H = Holiday. M = Missing. A = Filed absence. Unfiled absences show their assessed minutes. ? = excluded rest day. M = no attendance recorded for a completed work period. Future/pending periods and exemptions are not marked missing. Effective-dated schedules, weekly exceptions, and holiday/suspension exemptions apply. Configured rest days are excluded from monthly totals and missing-record checks. Leave rules are not configured.`)

function exportCsv() {
  if (!report.value) return
  // Quote every field and neutralize spreadsheet formulas in employee/shift names.
  const csvCell = value => {
    const text = String(value ?? '')
    return `"${(/^[\s]*[=+\-@]/.test(text) ? "'" + text : text).replaceAll('"', '""')}"`
  }
  const lines = [
    ['Monthly Tardiness and Undertime', report.value.month_label, selectedShiftName.value],
    [reportNote.value],
    ['Employee name', 'Shift', ...dayColumns.value.map(c => `${c.day.date} ${c.period.label} ${c.direction.toUpperCase()}`), 'Total minutes', 'Times tardy', 'Times undertime'],
    ...rows.value.map(row => [row.name, row.shift_name, ...row.cells.map(c => c.display), row.total_minutes, row.late_count, row.undertime_count]),
  ]
  const blob = new Blob(['\uFEFF' + lines.map(line => line.map(csvCell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `monthly-tardiness-${report.value.month}-${shiftId.value || 'all'}.csv`
  link.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
function printReport() {
  if (!grid.value || !report.value) return
  const preview = window.open('', '_blank', 'width=1400,height=900')
  if (!preview) { error.value = 'Allow pop-ups to open the printable report.'; return }
  const escape = value => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]))
  const printGrid = grid.value.cloneNode(true)
  printGrid.removeAttribute('style')
  printGrid.querySelectorAll('button').forEach(button => button.replaceWith(document.createTextNode(button.textContent)))
  preview.opener = null
  preview.document.write(`<!doctype html><html><head><title>Monthly Tardiness — ${escape(report.value.month_label)}</title><style>
    @page { size: A3 landscape; margin: 8mm; }
    body { font-family: Arial, sans-serif; color: #111; margin: 0; }
    h1 { font-size: 14px; margin: 0 0 6px; } p { font-size: 9px; }
    table { border-collapse: collapse; table-layout: fixed; width: 100%; font-size: ${periods.value.length > 2 ? '4' : '5'}pt; }
    th, td { border: .5pt dotted #777; padding: 2px 0; text-align: center; overflow-wrap: anywhere; }
    th.employee-name { width: 32mm; } th.total-cell { width: 11mm; }
    .employee-name { text-align: left; padding-left: 3px; } .employee-shift { display: none; }
    .weekend { color: #c00; } .holiday, .exempt { background: #eee; } .missing-record { color: #92400e; background: #fef3c7; } .holiday-cell { color: #075985; background: #e0f2fe; }
    thead { display: table-header-group; } tr { break-inside: avoid; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  </style></head><body><h1>Monthly Tardiness and Undertime — ${escape(report.value.month_label)}</h1><p>${escape(selectedShiftName.value)} · ${rows.value.length} employees · Generated ${escape(report.value.generated_at)} (${escape(report.value.timezone)})</p>${printGrid.outerHTML}<p>${escape(reportNote.value)}</p></body></html>`)
  preview.document.close()
  preview.focus()
  setTimeout(() => { if (!preview.closed) preview.print() }, 300)
}
</script>

<template>
  <div class="monthly-attendance space-y-3 text-slate-800 dark:text-slate-200">
    <nav class="flex w-fit border-b border-slate-200 text-sm dark:border-slate-700" aria-label="Monthly attendance reports">
      <button type="button" @click="activeTab = 'attendance'" :class="activeTab === 'attendance' ? 'tab-active' : 'tab-inactive'">Monthly tardiness</button>
      <button type="button" @click="activeTab = 'absences'" :class="activeTab === 'absences' ? 'tab-active' : 'tab-inactive'">Absences and tardiness</button>
    </nav>
    <template v-if="activeTab === 'attendance'">
    <section
      class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-lg font-semibold dark:text-white">Monthly Tardiness and Undertime</h1>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">A monthly grid of late arrivals and early
            departures, in minutes.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" @click="load" :disabled="loading" class="action-button">{{ loading ? 'Loading…' :
            'Refresh' }}</button>
          <button type="button" @click="exportCsv" :disabled="!report || !rows.length" class="action-button">Export
            CSV</button>
          <button type="button" @click="printReport" :disabled="!report || !rows.length" class="action-button">Print /
            PDF</button>
        </div>
      </div>
      <div class="mt-4 flex flex-wrap items-end gap-3">
        <label class="text-xs font-medium">Month<flat-pickr v-model="month" :config="pickerConfig"
            :disabled="initializing && loading" placeholder="Select month" class="filter-input mt-1 block" /></label>
        <label class="text-xs font-medium">Office shift<select v-model="shiftId" :disabled="initializing && loading"
            class="filter-input mt-1 block">
            <option value="">All shifts</option>
            <option v-for="shift in shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option>
          </select></label>
        <label class="text-xs font-medium">Search<input v-model="search" type="search" placeholder="Employee or shift…"
            class="filter-input mt-1 block" /></label>
        <label class="text-xs font-medium">Sort<select v-model="sort" class="filter-input mt-1 block">
            <option value="late_count">Times tardy ↓</option>
            <option value="undertime_count">Times undertime ↓</option>
            <option value="total_minutes">Total minutes ↓</option>
            <option value="name">Employee name A–Z</option>
          </select></label>
        <label class="flex items-center gap-2 pb-2 text-xs"><input v-model="issuesOnly" type="checkbox"
            class="rounded border-slate-300" /> With late / undertime only</label>
      </div>
      <div v-if="report" class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
        <span><strong>{{ rows.length }}</strong> employees shown</span><span><strong>{{ totals.minutes.toLocaleString()
            }}</strong> total minutes</span><span><strong>{{ totals.late }}</strong> times tardy</span><span><strong>{{
              totals.under }}</strong> times undertime</span>
        <span v-if="report.provisional"
          class="rounded bg-amber-50 px-2 py-1 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">Provisional</span>
      </div>
    </section>
    <div v-if="error" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{
      error }} <button type="button" @click="load" class="ml-2 underline">Retry</button></div>
    <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200">{{ notice }}</p>
    <section
      class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]"
      :aria-busy="loading">
      <div v-if="report" class="grid-scroll" tabindex="0"
        aria-label="Monthly attendance grid; scroll horizontally to view all days">
        <table ref="grid" class="month-grid" :style="{ minWidth: `${220 + gridColumns.length * 25 + 240}px` }">
          <thead>
            <tr>
              <th rowspan="4" class="employee-name">Employee Name</th>
              <th :colspan="gridColumns.length" class="month-heading">For the Month of {{
                report.month_label.toUpperCase() }}</th>
              <th rowspan="4" class="total-cell minutes-total">Total minutes<br />(Tardy + Undertime)</th>
              <th rowspan="4" class="total-cell tardy-total">Total times<br />tardy</th>
              <th rowspan="4" class="total-cell under-total">Total times<br />undertime</th>
            </tr>
            <tr>
              <th v-for="day in report.days" :key="day.date" :colspan="excludedDate(day) ? 1 : periods.length * 2"
                :class="{ weekend: day.weekend, holiday: day.holidays.length }"
                :title="`${day.date} (${day.weekday})${day.holidays.length ? ' · ' + day.holidays.map(h => h.name).join(', ') : ''}`">
                {{ day.day }}<span v-if="day.holidays.length">*</span></th>
            </tr>
            <tr><template v-for="day in report.days" :key="day.date">
                <th v-if="excludedDate(day)" rowspan="2" class="weekend-heading" :title="`${day.weekday} excluded from monthly totals`"><span>{{ day.weekday }}</span></th>
                <th v-for="period in periods" :key="period.key" colspan="2"
                  v-else
                  :title="`Period ending ${period.end}${period.nextDay ? ' next day' : ''}`">{{ period.label }}</th>
              </template>
            </tr>
            <tr>
              <th v-for="column in gridColumns.filter(column => !column.weekend)" :key="column.key"
                :class="{ 'day-start': column.period.key === periods[0].key && column.direction === 'in' }">{{
                  column.direction ===
                'in' ? 'I' : 'O' }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in rows" :key="row.id">
              <td class="employee-name"
                :title="`${row.name} · ${row.shift_name}${row.review_days ? ' · ' + row.review_days + ' day(s) with missing or inconsistent punches' : ''}`">
                <span class="mr-1 text-slate-400">{{ index + 1 }}.</span>{{ row.name }}
                <!-- <span class="employee-shift">{{
                  row.shift_name }}</span> -->
              </td>
              <td v-for="cell in row.gridCells" :key="cell.key" :title="cell.title"
                :class="{ 'has-value': cell.value, 'missing-record': cell.missing, 'holiday-cell': cell.holiday, exempt: cell.exempt, 'weekend-cell': cell.weekend, 'future-cell': cell.future, 'day-start': !cell.weekend && cell.period?.key === periods[0].key && cell.direction === 'in' }">
                <span v-if="cell.weekend" aria-hidden="true">&nbsp;</span>
                <button v-else type="button" @click="openCell(row, cell)" :aria-label="`${row.name}, ${cell.date}, ${cell.label} ${cell.direction.toUpperCase()}: ${cell.holiday ? 'Holiday exemption' : cell.filedAbsence ? 'Absent (filed)' : cell.unfiledAbsence ? 'Unfiled absence: ' + cell.value + ' minutes' : cell.missing ? 'Missing punch' : cell.value + ' minutes'}. Edit attendance logs`" class="cell-edit">{{ cell.display }}</button></td>
              <td class="total-cell minutes-total">{{ row.total_minutes }}</td>
              <td class="total-cell tardy-total">{{ row.late_count }}</td>
              <td class="total-cell under-total">{{ row.undertime_count }}</td>
            </tr>
            <tr v-if="!rows.length">
              <td :colspan="gridColumns.length + 4" class="empty-cell">No employees match these filters.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="px-4 py-10 text-center text-sm text-slate-500">{{ loading ? 'Preparing monthly report…' :
        'Select a month and refresh to view the report.' }}</div>
      <div
        class="space-y-1 border-t border-slate-200 p-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
        <p><strong>I</strong> = late minutes · <strong>O</strong> = undertime minutes · Red dates = weekends · * =
          holiday.
          Click any IN/OUT cell, including a blank cell, to add or override attendance logs or mark the day absent. Totals recalculate automatically.</p>
        <p>Each affected work period counts once. Morning and afternoon lateness count as two occurrences on the same
          day.
          H = Holiday. Gray ? = excluded rest day. M = Missing attendance for a completed work period. A = Filed absence. Unfiled absences show their assessed minutes. Filed absences are exempt from penalties. Absences may cover the whole day, morning, or afternoon. An unfiled absence is assessed as tardy. Pending/future periods, holiday exemptions, and unassigned periods are not marked missing.</p>
        <p>Totals and exports follow the visible employee filters. Reports use effective-dated schedules and holiday
          exemptions. Saturdays and Sundays are excluded from monthly totals and missing-record checks; leave rules are not configured. Print uses A3 landscape.</p>
      </div>
    </section>
    <AttendanceLogEditor v-if="editorContext" :context="editorContext" @close="editorContext = null" @saved="logSaved" />
    </template>
    <AbsencesSummary v-else />
  </div>
</template>

<style scoped>
.cell-edit { display: block; width: 100%; min-height: 22px; color: inherit; font: inherit; cursor: pointer; }
.cell-edit:hover, .cell-edit:focus-visible { background: #e0f2fe; outline: 2px solid #38bdf8; outline-offset: -2px; }
:global(.dark) .cell-edit:hover, :global(.dark) .cell-edit:focus-visible { background: #0c4a6e; }
.tab-active { @apply border-b-2 border-sky-600 px-3 py-2 font-semibold text-sky-700 dark:border-sky-400 dark:text-sky-300; }
.tab-inactive { @apply px-3 py-2 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200; }
.action-button {
  @apply rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-medium text-sky-700 disabled:opacity-50 dark:border-sky-900 dark:bg-sky-900/20 dark:text-sky-300;
}

:deep(.filter-input) {
  @apply h-9 max-w-full rounded-md border border-slate-300 bg-white px-3 text-xs dark:border-slate-700 dark:bg-slate-900;
}

.grid-scroll {
  max-height: 70vh;
  overflow: auto;
}

.month-grid {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;
  font-size: 10px;
}

.month-grid th,
.month-grid td {
  height: 27px;
  padding: 3px 1px;
  text-align: center;
  border-right: 1px dotted #cbd5e1;
  border-bottom: 1px dotted #cbd5e1;
  font-variant-numeric: tabular-nums;
}

.month-grid thead {
  position: sticky;
  top: 0;
  z-index: 5;
}

.month-grid th {
  background: #f8fafc;
  font-weight: 600;
}

.month-grid .month-heading {
  font-size: 12px;
  text-align: left;
  padding-left: 12px;
}

.month-grid .employee-name {
  position: sticky;
  left: 0;
  z-index: 2;
  width: 220px;
  padding: 5px 8px;
  text-align: left;
  background: white;
  font-size: 11px;
  border-right: 1px solid #94a3b8;
}

.month-grid th.employee-name {
  background: #f8fafc;
}

.employee-shift {
  display: block;
  margin-left: 12px;
  font-size: 9px;
  color: #64748b;
}

.month-grid .total-cell {
  position: sticky;
  width: 80px;
  background: #f8fafc;
  z-index: 2;
  font-weight: 600;
}

.month-grid .minutes-total {
  right: 160px;
  border-left: 1px solid #94a3b8;
}

.month-grid .tardy-total {
  right: 80px;
}

.month-grid .under-total {
  right: 0;
}

.month-grid .weekend {
  color: #dc2626;
}

.month-grid .weekend-heading,
.month-grid .weekend-cell {
  width: 22px;
  min-width: 22px;
  padding: 0;
}

.month-grid .weekend-heading span {
  display: inline-block;
  writing-mode: vertical-rl;
  text-orientation: upright;
  letter-spacing: 1px;
  color: #334155;
}

.month-grid .weekend-cell,
.month-grid .holiday,
.month-grid .exempt {
  background: #f1f5f9;
}

.month-grid .future-cell {
  color: #94a3b8;
}

.month-grid .has-value {
  color: #b91c1c;
  font-weight: 600;
}

.month-grid .day-start {
  border-left: 1px solid #94a3b8;
}

.month-grid .empty-cell {
  padding: 24px;
  text-align: left;
}

:global(.dark) .month-grid th,
:global(.dark) .month-grid .total-cell {
  background: #172033;
}

:global(.dark) .month-grid .employee-name {
  background: #111827;
}

:global(.dark) .month-grid th,
:global(.dark) .month-grid td {
  border-color: #334155;
}

:global(.dark) .month-grid .weekend-cell,
:global(.dark) .month-grid .holiday,
:global(.dark) .month-grid .exempt {
  background: #1e293b;
}

:global(.dark) .month-grid .has-value,
:global(.dark) .month-grid .weekend {
  color: #fca5a5;
}

.month-grid td.missing-record { background: #fef3c7; color: #92400e; font-weight: 600; }
:global(.dark) .month-grid td.missing-record { background: #451a03; color: #fcd34d; }

.month-grid td.holiday-cell { background: #e0f2fe; color: #075985; font-weight: 600; }
:global(.dark) .month-grid td.holiday-cell { background: #0c4a6e; color: #7dd3fc; }

@media (max-width: 800px) {
  .month-grid .total-cell {
    position: static;
  }
}
</style>
