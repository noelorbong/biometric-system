<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import flatPickr from 'vue-flatpickr-component'
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect'
import 'flatpickr/dist/flatpickr.css'
import 'flatpickr/dist/plugins/monthSelect/style.css'

const month = ref('')
const shiftId = ref('')
const report = ref(null)
const loading = ref(false)
const error = ref('')
const search = ref('')
const pickerConfig = { disableMobile: true, plugins: [new monthSelectPlugin({ dateFormat: 'Y-m', altFormat: 'F Y' })], altInput: true }
const leaveTypes = ['cto', 'vacation', 'sick', 'spl', 'without_pay']
const leaveLabel = type => ({ cto: 'CTO', vacation: 'Vacation', sick: 'Sick', spl: 'SPL', without_pay: 'W/out Pay' }[type] || 'Unclassified')
const durationValue = duration => duration === 'whole_day' ? 1 : 0.5
const durationLabel = duration => duration === 'whole_day' ? '' : duration === 'morning' ? ' AM' : ' PM'

async function load() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.post('/api/report/monthly-attendance', { month: month.value || null, office_shift_id: shiftId.value || null })
        report.value = data
        if (!month.value) month.value = data.month
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Unable to load the report.'
    } finally { loading.value = false }
}
onMounted(load)
watch([month, shiftId], () => { if (month.value) load() })

const rows = computed(() => {
    const term = search.value.trim().toLowerCase()
    return (report.value?.employees || []).map(employee => {
        const absences = employee.days.filter(day => day.absence).map(day => ({ date: day.date, ...day.absence }))
        const totals = Object.fromEntries(leaveTypes.map(type => [type, 0]))
        for (const absence of absences) {
            if (absence.leave_type) totals[absence.leave_type] += durationValue(absence.duration)
        }
        const filed = absences.filter(absence => absence.status === 'filed').length
        return {
            id: employee.id, name: employee.name, shift: employee.shift_name, filed,
            daysAbsent: absences.reduce((sum, absence) => sum + durationValue(absence.duration), 0),
            dates: absences.map(absence => `${new Date(`${absence.date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}${durationLabel(absence.duration)} (${leaveLabel(absence.leave_type || 'without_pay')})`).join(', '),
            ...totals, times: employee.late_count + employee.undertime_count,
            hours: Math.floor(employee.total_minutes / 60), minutes: employee.total_minutes % 60,
        }
    }).filter(row => !term || `${row.name} ${row.shift}`.toLowerCase().includes(term))
})
const shifts = computed(() => report.value?.office_shifts || [])
const number = value => Number.isInteger(value) ? value : value.toFixed(1)
function exportCsv() {
    const values = [['Employee name', 'Filed', 'Days absent', 'Date absent', 'CTO', 'Vacation', 'Sick', 'SPL', 'W/out Pay', 'Times undertime/tardy', 'Hours', 'Minutes'], ...rows.value.map(row => [row.name, row.filed, number(row.daysAbsent), row.dates, ...leaveTypes.map(type => number(row[type])), row.times, row.hours, row.minutes])]
    const text = values.map(row => row.map(value => `"${String(value ?? '').replaceAll('"', '""')}"`).join(',')).join('\r\n')
    const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(new Blob(['\uFEFF' + text], { type: 'text/csv;charset=utf-8;' })), download: `absences-tardiness-${report.value?.month || 'report'}.csv` })
    link.click()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
}
</script>

<template>
    <div class="space-y-3 text-slate-800 dark:text-slate-200">
        <section
            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold dark:text-white">Absences, Tardiness and Undertimes</h1>
                    <p class="mt-1 text-xs text-slate-500">Monthly employee summary of filed leave, absence dates, and
                        attendance penalties.</p>
                </div>
                <div class="flex gap-2"><button type="button" @click="load" :disabled="loading" class="action-button">{{
                    loading ? 'Loading...' : 'Refresh' }}</button><button type="button" @click="exportCsv"
                        :disabled="!report" class="action-button">Export CSV</button></div>
            </div>
            <div class="mt-4 flex flex-wrap items-end gap-3"><label class="label">Month<flat-pickr v-model="month"
                        :config="pickerConfig" class="filter-input mt-1 block" /></label><label class="label">Office
                    shift<select v-model="shiftId" class="filter-input mt-1 block">
                        <option value="">All shifts</option>
                        <option v-for="shift in shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option>
                    </select></label><label class="label">Search<input v-model="search" type="search"
                        placeholder="Employee or shift" class="filter-input mt-1 block" /></label></div>
        </section>
        <p v-if="error" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
        <section
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
            <div class="overflow-auto">
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Employee name</th>
                            <th rowspan="2">Filed</th>
                            <th rowspan="2">Days absent</th>
                            <th rowspan="2" class="dates">Date absent</th>
                            <th colspan="5">Total number of days absent</th>
                            <th rowspan="2">Times<br>undertime / tardy</th>
                            <th colspan="2">Undertime / tardy</th>
                        </tr>
                        <tr>
                            <th v-for="type in leaveTypes" :key="type">{{ leaveLabel(type) }}</th>
                            <th>Hours</th>
                            <th>Minutes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id">
                            <td class="name">{{ row.name }}
                                <!-- <span>{{ row.shift }}</span> -->
                            </td>
                            <td>{{ row.filed }}</td>
                            <td>{{ number(row.daysAbsent) }}</td>
                            <td class="dates">{{ row.dates || '-' }}</td>
                            <td v-for="type in leaveTypes" :key="type">{{ number(row[type]) }}</td>
                            <td>{{ row.times }}</td>
                            <td>{{ row.hours }}</td>
                            <td>{{ row.minutes }}</td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="12" class="empty">{{ loading ? 'Preparing report...' : 'No employees match the selected filters.' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-200 p-3 text-xs text-slate-500 dark:border-slate-800">Morning and afternoon
                absences count as 0.5 day. Unfiled absences are listed as without pay and their unattended periods are
                included in tardiness totals.</p>
        </section>
    </div>
</template>

<style scoped>
.action-button {
    @apply rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-medium text-sky-700 disabled:opacity-50 dark:border-sky-900 dark:bg-sky-900/20 dark:text-sky-300;
}

.label {
    @apply text-xs font-medium;
}

:deep(.filter-input) {
    @apply h-9 max-w-full rounded-md border border-slate-300 bg-white px-3 text-xs dark:border-slate-700 dark:bg-slate-900;
}

.summary-table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
    font-size: 12px;
}

.summary-table th,
.summary-table td {
    border: 1px dotted #94a3b8;
    padding: 7px 8px;
    text-align: center;
    vertical-align: middle;
}

.summary-table th {
    background: #f8fafc;
    font-weight: 700;
}

.summary-table .name,
.summary-table .dates {
    text-align: left;
}

.summary-table .name span {
    display: block;
    font-size: 10px;
    color: #64748b;
}

.summary-table .dates {
    min-width: 250px;
}

.summary-table .empty {
    padding: 28px;
    text-align: left;
    color: #64748b;
}

:global(.dark) .summary-table th {
    background: #172033;
}

:global(.dark) .summary-table th,
:global(.dark) .summary-table td {
    border-color: #334155;
}
</style>