<template>
    <div class="space-y-4">
        <!-- Print Header Controls -->
        <div v-if="showControls"
            class="flex flex-col md:flex-row gap-4 justify-between items-start md:items-center bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 print:hidden">
            <div class="flex flex-col gap-2">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Printable Daily Time Record</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Print official attendance record for payroll</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <label
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">Copies:</label>
                    <select v-model.number="copies"
                        class="h-9 px-3 rounded border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-200 font-medium">
                        <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
                    </select>
                </div>
                <button @click="handlePrint"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                    Print Record
                </button>
            </div>
        </div>

        <!-- Print Layout -->
        <div ref="printContainer"
            style="background: white; padding: 0px; border: 1px solid #d1d5db; border-radius: 8px; color: #111827;">
            <!-- Header Section -->
            <div style=" margin-top: 10px; border-bottom: 2px solid #111827;">
                <p style="font-size: 8px; font-weight: 600; color: #374151; margin-bottom: 4px; padding-left: 10px;">CSC
                    Form No. 48</p>
                <h1 style="text-align: center; font-size: 8pt; font-weight: 700; color: #111827; margin:0; padding:0;">
                    DAILY TIME RECORD</h1>
                <p style="text-align: center; font-size: 6pt; color: #4b5563; margin:0; padding:0;">{{ companyName || 'Company / School Name' }}</p>
           
                <p style="text-align: center; font-size: 6pt; color: #4b5563;  margin-top: 8pt; ">
                    {{ user?.department || user?.department_ref?.department_name || 'Department' }}
                </p>
                <h1 style="text-align: center; font-size: 12pt; font-weight: 700; color: #111827; margin-top:5px; ">{{ user?.name }}
                </h1>
            </div>

            <!-- Employee Info Section -->
            <div
                style="display: grid; grid-template-columns: 1fr 1fr; column-gap: 16px; row-gap: 10px; margin-bottom: 16px; font-size: 14px;">

                <div style="line-height:8pt; grid-column: span 2; display: flex; align-items: center; margin-top:10px">
                    <p style="color: #4b5563; font-size: 7.5pt; white-space: nowrap; padding-right: 5px;">For the Month
                        of</p>
                    <p
                        style="font-weight: 600; text-align: center; color: #111827; width: 100%; font-size: 8pt; border-bottom: 1px solid #111827;">
                        {{ monthYearDisplay }}</p>
                </div>
                <div style="line-height:2pt; display: flex; align-items: center;">
                    <p style="color: #4b5563; font-size: 7.5pt;">Official Hours</p>
                    <p style="font-weight: 600; color: #111827;"></p>
                </div>
                <div style="line-height:8pt; display: flex; align-items: center;">
                    <span style="color: #4b5563; font-size: 7.5pt;  white-space: nowrap; padding-right: 5px;">
                        Regular Days
                    </span>
                    <span style="flex: 1; font-weight: 600; color: #111827; border-bottom: 1px solid #111827;"></span>
                </div>
                <div style="line-height:8pt; display: flex; align-items: center; ">
                    <p style="color: #4b5563; font-size: 7.5pt;">Arrival and Departure</p>
                    <p style="font-weight: 600; color: #111827; border-bottom: 1px solid #111827;"></p>
                </div>
                <div style="line-height:8pt; display: flex;  align-items: center; ">
                    <p style=" color: #4b5563; font-size: 7.5pt; padding-right:10px;">Saturdays</p>
                    <p style="width: 100%; font-weight: 600; color: #111827; border-bottom: 1px solid #111827;"></p>
                </div>
            </div>

            <!-- Time Record Table -->
            <div style="overflow: visible; margin-bottom: 20px;">
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #111827; font-size: 14px;">
                    <thead>
                        <tr style="background: #f3f4f6;">
                            <th rowspan="2"
                                style="font-size: 8.5pt; border: 1px solid #111827; padding: 8px; text-align: center; vertical-align: middle; font-weight: 700; color: #111827; ">
                                DAY
                            </th>
                            <th colspan="2"
                                style="font-size: 8.5pt; border: 1px solid #111827; padding: 8px; text-align: center; font-weight: 700; color: #111827;">
                                A.M.
                            </th>
                            <th colspan="2"
                                style="font-size: 8.5pt; border: 1px solid #111827; padding: 8px; text-align: center; font-weight: 700; color: #111827;">
                                P.M.
                            </th>
                            <th colspan="2"
                                style="font-size: 8.5pt; border: 1px solid #111827; padding: 8px; text-align: center; font-weight: 700; color: #111827;">
                                UNDERTIME
                            </th>
                        </tr>
                        <tr style="background: #f3f4f6;">
                            <th
                                style="font-weight: 300; border: 1px solid #111827;  text-align: center;  color: #111827; font-size: 8.5pt;">
                                IN</th>
                            <th
                                style="font-weight: 300; border: 1px solid #111827;  text-align: center;  color: #111827; font-size: 8.5pt;">
                                OUT</th>
                            <th
                                style="font-weight: 300; border: 1px solid #111827;  text-align: center;  color: #111827; font-size: 8.5pt;">
                                IN</th>
                            <th
                                style="font-weight: 300; border: 1px solid #111827;  text-align: center;  color: #111827; font-size: 8.5pt;">
                                OUT</th>
                            <th
                                style="font-weight: 300; border: 1px solid #111827;  text-align: center;  color: #111827; font-size: 8.5pt;">
                                Hrs.</th>
                            <th
                                style="font-weight: 300; border: 1px solid #111827;  text-align: center; color: #111827; font-size: 8.5pt;">
                                Min.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="day in totalDaysInMonth" :key="day">
                            <td style="border: 1px solid #111827; font-size: 6pt; text-align: center;  color: #111827;">
                                {{ day }}
                            </td>
                            <td style="border: 1px solid #111827; font-size: 6pt; text-align: center; ">
                                {{ getRecordValue(day, 'am_in') }}
                            </td>
                            <td style="border: 1px solid #111827; font-size: 6pt; text-align: center; ">
                                {{ getRecordValue(day, 'am_out') }}
                            </td>
                            <td style="border: 1px solid #111827; font-size: 6pt; text-align: center; ">
                                {{ getRecordValue(day, 'pm_in') }}
                            </td>
                            <td style="border: 1px solid #111827; font-size: 6pt; text-align: center; ">
                                {{ getRecordValue(day, 'pm_out') }}
                            </td>
                            <td
                                style="border: 1px solid #111827;  font-size: 6pt; text-align: center;">
                                {{ getRecordValue(day, 'undertime_hrs') }}
                            </td>
                            <td
                                style="border: 1px solid #111827;  font-size: 6pt; text-align: center;">
                                {{ getRecordValue(day, 'undertime_min') }}
                            </td>
                        </tr>
                        <!-- TOTAL Row -->
                        <tr style="background: #f3f4f6; font-weight: 700;">
                            <td colspan="7"
                                style="border: 1px solid #111827; padding: 8px; text-align: left; color: #111827;">
                                TOTAL
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Certification Section -->
            <div style=" color: #374151;">
                <p style="font-style: italic; margin-bottom: 16px; font-size: 8pt;">
                    &emsp; &emsp; I CERTIFY on my honor that the above is a true and correct report of the hours of work
                    performed, a record of which was made daily at the time of arrival at and departure from office.
                </p>

                <div style=" margin-top: 32px;">
                    <!-- Employee Signature -->
                    <div style="margin-left:auto; width:50%; text-align: center;">
                        <div style="border-top: 1px solid #111827; "></div>
                    </div>

                    <div style="">
                        <p style="font-style: italic; margin-bottom: 16px; font-size: 8pt;">
                            &emsp; &emsp; Verified as to the prescribed office hours.
                        </p>
                    </div>

                    <!-- In-Charge -->
                    <div style="margin-left:auto; width:50%; text-align: center;">
                        <div style="border-top: 1px solid #111827; "></div>
                        <p style="color: #111827;  margin-top: 0px; font-style: italic; font-size: 8pt;">In-Charge</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const printContainer = ref(null)
const copies = ref(4)

const props = defineProps({
    user: Object,
    selectedYear: Number,
    selectedMonth: Number,
    attendanceRecords: Array,
    companyName: {
        type: String,
        default: 'Biometric System'
    },
    showControls: {
        type: Boolean,
        default: true,
    }
})

// Computed Properties
const monthYearDisplay = computed(() => {
    if (!props.selectedMonth || !props.selectedYear) return 'N/A'
    const date = new Date(props.selectedYear, props.selectedMonth - 1)
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const officeHoursDisplay = computed(() => {
    if (!props.user?.officeShift?.schedule) return 'N/A'
    return props.user.officeShift.schedule
})

const totalDaysInMonth = computed(() => {
    if (!props.selectedMonth || !props.selectedYear) return []
    const days = new Date(props.selectedYear, props.selectedMonth, 0).getDate()
    return Array.from({ length: days }, (_, i) => i + 1)
})

// Methods
const getRecordValue = (day, field) => {
    if (!props.attendanceRecords || !Array.isArray(props.attendanceRecords)) return ''

    // Find attendance record for this day
    const dayStr = String(day).padStart(2, '0')
    const monthStr = String(props.selectedMonth).padStart(2, '0')
    const dateStr = `${props.selectedYear}-${monthStr}-${dayStr}`

    const record = props.attendanceRecords.find(r => r.date === dateStr)
    if (!record) return ''

    // Return appropriate field value
    switch (field) {
        case 'am_in':
            return record.am_in || ''
        case 'am_out':
            return record.am_out || ''
        case 'pm_in':
            return record.pm_in || ''
        case 'pm_out':
            return record.pm_out || ''
        case 'undertime_hrs':
            return record.undertimeHrs || ''
        case 'undertime_min':
            return record.undertimeMin || ''
        default:
            return ''
    }
}

const getPrintContent = () => {
    return printContainer.value?.innerHTML || ''
}

const buildPrintBodyHtml = (content, copiesCount) => {
    const n = copiesCount || 1
    const perRow = 4

    let bodyHtml = ''
    for (let i = 0; i < n; i += perRow) {
        const rowCopies = []
        for (let j = 0; j < perRow; j++) {
            if (i + j < n) {
                rowCopies.push(`<div class="form-copy">${content}</div>`)
            }
        }
        // Join copies with vertical cut lines
        const rowInner = rowCopies.join('')
        const isLastRow = i + perRow >= n
        const rowSeparator = !isLastRow
            ? ''
            : ''
        bodyHtml += `<div class="page-wrapper">${rowInner}</div>${rowSeparator}`
    }

        return bodyHtml
}

const printStyles = `
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: Arial, sans-serif; background: white; color: black; }

                    @page {
                        size: 13in 8.5in landscape;
                        margin: 0;
                    }

                    .page-wrapper {
                        display: flex;
                        flex-direction: row;
                        align-items: flex-start;
                        width: 330.2mm;
                    }

                    .form-copy {
                        width: 82.55mm;
                        height: 215.9mm;
                        overflow: hidden;
                        page-break-inside: avoid;
                        flex-shrink: 0;
                        padding: 4px 3px 0 3px;
                    }

                    /* Horizontal cut line between rows */
                    .cut-line-h {
                        width: 330.2mm;
                        margin:  0;
                        border-top: 1px dashed #666;
                        text-align: center;
                        position: relative;
                    }
                    .cut-line-h span {
                        position: absolute;
                        top: -7px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: white;
                        padding: 0px;
                        font-size: 7px;
                        color: #555;
                        letter-spacing: 1px;
                    }

                    h1 { font-size: 10px; font-weight: bold; }
                    p { font-size: 7px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
                    th, td { border: 1px solid black; padding: 1.5px 2px; text-align: center; font-size: 6.5px; }
                    thead tr { background-color: #e5e7eb; }
                    th { font-weight: bold; }
                    .text-center { text-align: center; }
                    .text-left { text-align: left; }
                    .text-right { text-align: right; }
                    .font-bold { font-weight: bold; }
                    .font-semibold { font-weight: 600; }
                    .italic { font-style: italic; }
                    .grid { display: grid; }
                    .grid-cols-2 { grid-template-columns: 1fr 1fr; gap: 3px; margin-bottom: 4px; }
                    .grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-top: 6px; }
                    .mb-1 { margin-bottom: 1px; }
                    .mb-2 { margin-bottom: 2px; }
                    .mb-6 { margin-bottom: 4px; }
                    .mb-8 { margin-bottom: 5px; }
                    .mt-8 { margin-top: 6px; }
                    .pb-4 { padding-bottom: 3px; }
                    .border-b-2 { border-bottom: 2px solid black; }
                    .border-t { border-top: 1px solid black; }
                    .h-12 { height: 14px; display: block; }
                    .text-xs { font-size: 6px; }
                    .text-sm { font-size: 7px; }
                    .text-2xl { font-size: 11px; }
                    .space-y-4 > * + * { margin-top: 4px; }
                    .overflow-x-auto { overflow: visible; }
                    .p-8 { padding: 5px; }
                    .p-2 { padding: 1.5px; }
                    .rounded-lg, .rounded { border-radius: 0; }
                    .space-y-4 { display: block; }
`

const getPrintPayload = (copiesCount = copies.value || 1) => {
        const content = printContainer.value?.innerHTML
        if (!content) {
                return null
        }

        return {
                bodyHtml: buildPrintBodyHtml(content, copiesCount),
                styles: printStyles,
        }
}

const handlePrint = () => {
        const payload = getPrintPayload(copies.value || 1)
        if (!payload) {
                return
        }

    const win = window.open('', '_blank')
    win.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="UTF-8" />
        <title>Daily Time Record</title>
        <style>
                    ${payload.styles}
        </style>
      </head>
      <body>
                ${payload.bodyHtml}
      </body>
    </html>
  `)
    win.document.close()
    win.focus()
    win.print()
    win.close()
}

defineExpose({ getPrintPayload, getPrintContent })
</script>

<style scoped>
/* Print Styles */
@media print {
    :deep(body) {
        background: white;
    }

    .print\:bg-white {
        background-color: white !important;
    }

    .print\:border-none {
        border: none !important;
    }

    .print\:p-0 {
        padding: 0 !important;
    }

    .print\:hidden {
        display: none !important;
    }

    .print\:hover\:bg-white:hover {
        background-color: white !important;
    }

    /* Ensure table visibility in print */
    table {
        page-break-inside: avoid;
    }

    tr {
        page-break-inside: avoid;
    }

    /* Adjust margins for printing */
    @page {
        margin: 0in;
    }
}

/* Dark mode adjustments for print */
@media print {
    * {
        background: white !important;
        color: black !important;
        border-color: black !important;
    }

    .dark\:bg-gray-900,
    .dark\:bg-gray-800,
    .dark\:bg-gray-700 {
        background-color: white !important;
    }

    .dark\:text-white,
    .dark\:text-gray-300,
    .dark\:text-gray-400 {
        color: black !important;
    }

    .dark\:border-gray-600,
    .dark\:border-gray-700 {
        border-color: black !important;
    }
}
</style>
