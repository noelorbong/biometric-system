<script setup>
import { finishPrintWindow } from '@/utils/printPreview'
import { absenceCsv, absenceValues, buildAbsenceSummary, summaryNumber as number } from '@/utils/absenceSummary'
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import axios from 'axios'
import flatPickr from 'vue-flatpickr-component'
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect'
import 'flatpickr/dist/flatpickr.css'
import 'flatpickr/dist/plugins/monthSelect/style.css'

const printGrid = ref(null)
const month = ref('')
const shiftId = ref('')
const report = shallowRef(null)
const appliedFilters = shallowRef(null)
const shifts = ref([])
const loading = ref(false)
const exporting = ref(false)
const initializing = ref(true)
const error = ref('')
const search = ref('')
const page = ref(1)
const pageSize = ref(50)
const pickerConfig = { disableMobile: true, plugins: [new monthSelectPlugin({ dateFormat: 'Y-m', altFormat: 'F Y' })], altInput: true }
const leaveTypes = ['cto', 'vacation', 'sick', 'spl', 'without_pay']
const leaveLabel = type => ({ cto: 'CTO', vacation: 'Vacation', sick: 'Sick', spl: 'SPL', without_pay: 'W/out Pay' }[type] || 'Unclassified')
let requestId = 0
let controller
let searchTimer

async function load({ refresh = false } = {}) {
    const id = ++requestId
    controller?.abort()
    controller = new AbortController()
    loading.value = true
    error.value = ''
    report.value = null
    const payload = { month: month.value || null, office_shift_id: shiftId.value || null,
        page: page.value, per_page: pageSize.value, search: search.value.trim(), refresh }
    try {
        const { data } = await axios.post('/api/report/monthly-attendance', payload, { signal: controller.signal })
        if (id !== requestId) return
        report.value = data
        appliedFilters.value = { ...payload, month: data.month }
        shifts.value = data.office_shifts
        page.value = data.pagination?.page || 1
        if (!month.value) month.value = data.month
    } catch (exception) {
        if (id !== requestId || axios.isCancel(exception)) return
        error.value = exception.response?.data?.message || 'Unable to load the report.'
    } finally { if (id === requestId) loading.value = false }
}
onMounted(async () => { await load(); initializing.value = false })
watch([month, shiftId, pageSize], () => {
    clearTimeout(searchTimer)
    page.value = 1
    if (!initializing.value) load()
})
watch(search, () => {
    clearTimeout(searchTimer)
    ++requestId
    controller?.abort()
    loading.value = false
    report.value = null
    searchTimer = setTimeout(() => { page.value = 1; load() }, 300)
})
onBeforeUnmount(() => { clearTimeout(searchTimer); ++requestId; controller?.abort() })

const rows = computed(() => buildAbsenceSummary(report.value))
const pagination = computed(() => report.value?.pagination || { page: 1, per_page: 50, total: 0, last_page: 1, from: 0, to: 0 })
const goToPage = async next => {
    if (loading.value || next < 1 || next > pagination.value.last_page) return
    page.value = next
    await load()
}
async function loadFullExport() {
    if (!report.value || !appliedFilters.value || exporting.value) return null
    const { page, per_page, refresh, ...filters } = appliedFilters.value
    exporting.value = true
    error.value = ''
    try {
        const { data } = await axios.post('/api/report/monthly-attendance', { ...filters, export_all: true })
        return { data, rows: buildAbsenceSummary(data) }
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Unable to prepare the complete report. Please try again.'
        return null
    } finally { exporting.value = false }
}
async function exportCsv() {
    const snapshot = await loadFullExport()
    if (!snapshot) return
    const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(new Blob(['\uFEFF' + absenceCsv(snapshot.rows)], { type: 'text/csv;charset=utf-8;' })), download: `absences-tardiness-${snapshot.data.month}.csv` })
    link.click()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
}
async function printReport() {
    if (!report.value || !printGrid.value || exporting.value) return
    // Open synchronously to retain the browser's user-initiated popup permission.
    const preview = window.open('', '_blank', 'width=1400,height=900')
    if (!preview) { error.value = 'Allow pop-ups to open the print preview.'; return }
    preview.opener = null
    const table = printGrid.value.cloneNode(false)
    table.append(printGrid.value.tHead.cloneNode(true))
    preview.document.write('<!doctype html><html><body><p>Preparing the complete report...</p></body></html>')
    preview.document.close()
    const snapshot = await loadFullExport()
    if (!snapshot || preview.closed) { if (!preview.closed) preview.close(); return }
    const body = table.createTBody()
    for (const row of snapshot.rows) {
        const tr = body.insertRow()
        const values = absenceValues(row)
        values[3] ||= '-'
        values.forEach(value => { tr.insertCell().textContent = String(value) })
    }
    preview.document.open()
    preview.document.write('<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4 landscape;margin:10mm}body{font:10px Arial;color:#111;margin:0}h1{font-size:16px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #aaa;padding:4px;text-align:center}td:first-child{text-align:left}thead{display:table-header-group}tr{break-inside:avoid}</style></head><body></body></html>')
    preview.document.close()
    preview.document.title = `Absences, Tardiness and Undertimes - ${snapshot.data.month_label}`
    const heading = preview.document.createElement('h1')
    heading.textContent = preview.document.title
    preview.document.body.append(heading, table)
    void finishPrintWindow(preview)
}
</script>

<template>
    <div class="space-y-3 text-slate-800 dark:text-slate-200">
        <section
            class="admin-page-header dark -ml-4 -mt-4 md:-ml-6 md:-mt-6 sticky px-2 py-1.5 top-0 z-20 -mr-4 border pb-2 shadow-sm md:-mr-6">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-base font-semibold dark:text-white">Absences, Tardiness and Undertimes</h1>
                </div>
                <div class="ml-auto"><slot name="navigation" /></div>
            </div>
            <div class="mt-2 flex flex-wrap items-end gap-2 border-t border-white/10 pt-2"><label class="label">Month<flat-pickr v-model="month"
                        :config="pickerConfig" class="filter-input mt-1 block" /></label><label class="label">Office
                    shift<select v-model="shiftId" class="filter-input mt-1 block">
                        <option value="">All shifts</option>
                        <option v-for="shift in shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option>
                    </select></label><label class="label">Search<input v-model="search" type="search"
                        placeholder="Employee or shift" class="filter-input mt-1 block" /></label>                <div class="ml-auto flex flex-wrap items-center gap-1.5"><button type="button" @click="load({ refresh: true })" :disabled="loading || exporting" class="action-button">{{
                    loading ? 'Loading...' : 'Refresh' }}</button><button type="button" @click="exportCsv"
                        :disabled="loading || exporting || !report || !pagination.total" class="action-button">{{ exporting ? 'Preparing export...' : 'Export CSV' }}</button><button type="button" :disabled="loading || exporting || !report || !pagination.total" @click="printReport" class="action-button">Print</button></div></div>
        </section>
        <p v-if="error" class="rounded-none bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
        <section
            class="overflow-hidden rounded-none border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
            <div class="overflow-auto">
                <table ref="printGrid" class="summary-table">
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
                            <td class="name"><span class="employee-name" :title="row.name">{{ row.name }}</span>
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
            <div v-if="report" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-3 py-2 text-[10px] text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                <label class="flex items-center gap-1.5">Show <select v-model.number="pageSize" class="h-7 rounded-none border border-slate-300 bg-white py-0 pl-2 pr-6 text-[11px] dark:border-slate-600 dark:bg-slate-900"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select> employees</label>
                <span>{{ pagination.from }}?{{ pagination.to }} of {{ pagination.total }} employees</span>
                <div class="flex items-center gap-2"><button type="button" class="action-button" :disabled="loading || pagination.page <= 1" @click="goToPage(pagination.page - 1)">Previous</button><span>Page {{ pagination.page }} of {{ pagination.last_page }}</span><button type="button" class="action-button" :disabled="loading || pagination.page >= pagination.last_page" @click="goToPage(pagination.page + 1)">Next</button></div>
            </div>
            <p class="border-t border-slate-200 p-3 text-xs text-slate-500 dark:border-slate-800">Morning and afternoon
                absences count as 0.5 day. Unfiled absences are listed as without pay and their unattended periods are
                included in tardiness totals.</p>
        </section>
    </div>
</template>

<style scoped>
.action-button {
    @apply inline-flex h-7 items-center justify-center whitespace-nowrap rounded-none border border-cyan-300/30 bg-teal-700 px-2 text-[11px] font-semibold text-white transition hover:bg-teal-600 disabled:cursor-not-allowed disabled:opacity-50;
}

.label {
    @apply min-w-0 text-[10px] font-medium text-slate-300;
}

:deep(.filter-input) {
    @apply h-7 min-w-0 max-w-full rounded-none border border-slate-300 bg-white py-0 pl-2 pr-6 text-[11px] focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-slate-700 dark:bg-slate-900;
}

.summary-table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
    font-size: 11px;
}

.summary-table th,
.summary-table td {
    border: 1px dotted #94a3b8;
    padding: 4px 6px;
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

.dark .summary-table th {
    background: #172033;
}

.dark .summary-table th,
.dark .summary-table td {
    border-color: #334155;
}

.summary-table .name .employee-name { width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: inherit; }
.summary-table .dates { max-width: 280px; overflow-wrap: anywhere; }
.admin-page-header button:focus-visible { outline: 2px solid #5eead4; outline-offset: 2px; }
@media print { .summary-table .name .employee-name { width: auto; overflow: visible; white-space: normal; } }
</style>
