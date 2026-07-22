<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import flatPickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/common/Modal.vue'
import { useUserStore } from '@/store/UserStore'
import { useAppSettingStore } from '@/store/AppSettingStore'
import { storeToRefs } from 'pinia'
import PrintableAttendance from '@/views/user/components/PrintableAttendance.vue'

const userStore = useUserStore()
const appSettingStore = useAppSettingStore()
const { officeShifts, departments, colleges } = storeToRefs(userStore)
const { companySchoolName, companySchoolLogo, companySchoolLogoPrintEnabled } = storeToRefs(appSettingStore)

const now = new Date()
const formatDateInput = (date) => {
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-')
}

const parseDateInputValue = (value) => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))) {
    return null
  }

  const [year, month, day] = String(value).split('-').map(Number)
  const date = new Date(year, month - 1, day)

  if (
    Number.isNaN(date.getTime())
    || date.getFullYear() !== year
    || date.getMonth() !== (month - 1)
    || date.getDate() !== day
  ) {
    return null
  }

  return date
}

const toDateOnlyKey = (value) => {
  const parsed = parseDateInputValue(value)
  return parsed ? formatDateInput(parsed) : null
}

const formatRangeModelValue = (dateFrom, dateTo) => {
  const from = toDateOnlyKey(dateFrom)
  const to = toDateOnlyKey(dateTo)

  if (!from && !to) return ''
  if (from && !to) return from
  if (from && to && from === to) return from
  return `${from} to ${to}`
}

const applyRangeSelection = (selectedDates = []) => {
  if (!Array.isArray(selectedDates) || selectedDates.length === 0) {
    filters.value.date_from = ''
    filters.value.date_to = ''
    customDateRange.value = ''
    return
  }

  const from = selectedDates[0] instanceof Date ? formatDateInput(selectedDates[0]) : ''
  const to = selectedDates[1] instanceof Date
    ? formatDateInput(selectedDates[1])
    : from

  filters.value.date_from = from
  filters.value.date_to = to
  customDateRange.value = formatRangeModelValue(from, to)
}

const customRangePickerConfig = {
  mode: 'range',
  dateFormat: 'Y-m-d',
  altInput: true,
  altFormat: 'M j, Y',
  allowInput: false,
  onChange: applyRangeSelection,
  onClose: applyRangeSelection,
}
const monthStart = new Date(now.getFullYear(), now.getMonth(), 1)
const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0)

const filterMode = ref('monthly')
const filters = ref({
  year: now.getFullYear(),
  month: now.getMonth() + 1,
  date_from: formatDateInput(monthStart),
  date_to: formatDateInput(monthEnd),
  office_shift_id: '',
  department_id: '',
  college_id: '',
})
const customDateRange = ref('')

watch(
  () => [filters.value.date_from, filters.value.date_to],
  ([dateFrom, dateTo]) => {
    customDateRange.value = formatRangeModelValue(dateFrom, dateTo)
  },
  { immediate: true },
)

const reportUsers = ref([])
const loading = ref(false)
const preparingPrintData = ref(false)
const copiesPerUser = ref(1)
const calculateUndertime = ref(false)
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

const customRangeLabel = computed(() => {
  const from = filters.value.date_from
  const to = filters.value.date_to

  if (!from || !to) {
    return 'Select date range'
  }

  const fromDate = parseDateInputValue(from)
  const toDate = parseDateInputValue(to)
  if (!fromDate || !toDate) {
    return 'Invalid date range'
  }

  const fromLabel = fromDate.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
  const toLabel = toDate.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })

  return `${fromLabel} - ${toLabel}`
})

const periodLabel = computed(() => (filterMode.value === 'custom' ? customRangeLabel.value : monthYearLabel.value))

const selectedPeriodStart = computed(() => {
  if (filterMode.value === 'custom') {
    return filters.value.date_from || null
  }

  return `${filters.value.year}-${String(filters.value.month).padStart(2, '0')}-01`
})

const selectedPeriodYear = computed(() => {
  const start = selectedPeriodStart.value
  if (!start) return Number(filters.value.year)

  const [year] = String(start).split('-').map(Number)
  return Number.isNaN(year) ? Number(filters.value.year) : year
})

const selectedPeriodMonth = computed(() => {
  const start = selectedPeriodStart.value
  if (!start) return Number(filters.value.month)

  const [, month] = String(start).split('-').map(Number)
  return Number.isNaN(month) ? Number(filters.value.month) : month
})

const getCustomRangeBounds = () => {
  const dateFrom = filters.value.date_from
  const dateTo = filters.value.date_to

  if (!dateFrom || !dateTo) {
    return { error: 'Select both start and end dates for custom filter.' }
  }

  const fromDate = parseDateInputValue(dateFrom)
  const toDate = parseDateInputValue(dateTo)

  if (!fromDate || !toDate) {
    return { error: 'Invalid custom date range.' }
  }

  if (fromDate > toDate) {
    return { error: 'Start date must not be later than end date.' }
  }

  return {
    dateFrom,
    dateTo,
    fromDate,
    toDate,
    fromYear: fromDate.getFullYear(),
    fromMonth: fromDate.getMonth() + 1,
    toYear: toDate.getFullYear(),
    toMonth: toDate.getMonth() + 1,
  }
}

const buildDateFilterPayload = () => {
  if (filterMode.value === 'custom') {
    const range = getCustomRangeBounds()
    if (range.error) {
      return { error: range.error }
    }

    if (range.fromYear !== range.toYear || range.fromMonth !== range.toMonth) {
      return { error: 'Custom filter currently supports dates within the same month only.' }
    }

    return {
      year: range.fromYear,
      month: range.fromMonth,
      date_from: range.dateFrom,
      date_to: range.dateTo,
    }
  }

  return {
    year: Number(filters.value.year),
    month: Number(filters.value.month),
  }
}

const getSelectedDateKeys = () => {
  if (filterMode.value === 'custom') {
    const range = getCustomRangeBounds()
    if (range.error) {
      return []
    }

    const keys = []
    const cursor = new Date(range.fromDate)
    const end = new Date(range.toDate)

    while (cursor <= end) {
      keys.push(formatDateInput(cursor))
      cursor.setDate(cursor.getDate() + 1)
    }

    return keys
  }

  const totalDaysInMonth = new Date(Number(filters.value.year), Number(filters.value.month), 0).getDate()
  const keys = []

  for (let day = 1; day <= totalDaysInMonth; day += 1) {
    keys.push(`${filters.value.year}-${String(filters.value.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`)
  }

  return keys
}

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
  const dateFilterPayload = buildDateFilterPayload()
  if (dateFilterPayload.error) {
    toastResult(dateFilterPayload.error, 'info')
    return
  }

  loading.value = true
  preparingPrintData.value = false

  try {
    const payload = {
      ...dateFilterPayload,
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
    loading.value = false

    if (reportUsers.value.length) {
      preparingPrintData.value = true
      fetchOverridesForUsers(reportUsers.value).finally(() => {
        preparingPrintData.value = false
      })
    }
  } catch (error) {
    reportUsers.value = []
    toastResult(error?.response?.data?.message || 'Unable to generate report', 'error')
  } finally {
    loading.value = false
  }
}

const fetchOverridesForUsers = async (users) => {
  const dateFilterPayload = buildDateFilterPayload()
  if (dateFilterPayload.error) {
    return
  }

  const concurrency = 8
  let cursor = 0

  const prepareUser = async (user) => {
    try {
      const resp = await axios.post('/api/user/checkinout', {
        user_id: user.id,
        ...dateFilterPayload,
      })
      user._effective_checkinouts = resp?.data?.checkinouts || []
      user._overrides = resp?.data?.overrides || []
      user._printable_attendance_records = buildPrintableAttendanceRecords(user, user._effective_checkinouts)
    } catch (err) {
      user._effective_checkinouts = []
      user._overrides = []
      user._printable_attendance_records = []
    }
  }

  const workers = Array.from({ length: Math.min(concurrency, users.length) }, async () => {
    while (cursor < users.length) {
      const user = users[cursor]
      cursor += 1
      await prepareUser(user)
    }
  })

  await Promise.all(workers)
}

const printReport = async () => {
  if (!reportUsers.value.length) {
    toastResult('Generate report first', 'info')
    return
  }

  if (preparingPrintData.value) {
    toastResult('Preparing print data, please try again in a moment', 'info')
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

  const printWhenReady = () => {
    win.focus()
    win.print()
    win.close()
  }

  const images = Array.from(win.document.images || [])
  if (!images.length) {
    printWhenReady()
    return
  }

  let remaining = images.length
  const finish = () => {
    remaining -= 1
    if (remaining <= 0) {
      printWhenReady()
    }
  }

  images.forEach((img) => {
    if (img.complete) {
      finish()
      return
    }

    img.addEventListener('load', finish, { once: true })
    img.addEventListener('error', finish, { once: true })
  })
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
  const dateFilterPayload = buildDateFilterPayload()
  if (dateFilterPayload.error) {
    toastResult(dateFilterPayload.error, 'info')
    return
  }

  biometricModalOpen.value = true
  biometricModalLoading.value = true
  biometricLogUser.value = user
  biometricLogRows.value = []
  biometricLogOverrides.value = []

  try {
    const resp = await axios.post('/api/user/checkinout', {
      user_id: user.id,
      ...dateFilterPayload,
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

const toMinutesFromScheduleTime = (value) => {
  if (!value) return null

  const [h, m] = String(value).split(':')
  const hours = Number(h)
  const minutes = Number(m)

  if (Number.isNaN(hours) || Number.isNaN(minutes)) {
    return null
  }

  return (hours * 60) + minutes
}

const toMinutesFromDateTime = (value) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return null
  }

  return (date.getHours() * 60) + date.getMinutes()
}

const toMinutesFromTimeString = (value) => {
  if (!value) return null

  const parts = String(value).split(':')
  if (parts.length < 2) {
    return null
  }

  const hours = Number(parts[0])
  const minutes = Number(parts[1])

  if (Number.isNaN(hours) || Number.isNaN(minutes)) {
    return null
  }

  return (hours * 60) + minutes
}

const formatTimeOnly = (value) => {
  if (!value) return ''

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

const formatUndertimeParts = (minutesValue) => {
  const total = Math.max(0, Number(minutesValue) || 0)
  return {
    hrs: Math.floor(total / 60),
    min: total % 60,
  }
}

const getScheduleSlots = (user) => {
  const schedules = user?.office_shift?.schedules || user?.officeShift?.schedules || []
  const slots = [...schedules].sort((a, b) => (a.sequence || 0) - (b.sequence || 0))

  if (!slots.length) {
    return [{ sequence: 1, time_in: null, time_out: null, is_next_day: false }]
  }

  return slots
}

const hasOvernightShift = (user) => {
  return getScheduleSlots(user).some((row) => {
    if (row?.is_next_day) {
      return true
    }

    const timeIn = String(row?.time_in || '')
    const timeOut = String(row?.time_out || '')
    return timeIn && timeOut && timeOut < timeIn
  })
}

const resolveLogicalDateKey = (user, recordOrValue) => {
  const value = typeof recordOrValue === 'object' && recordOrValue !== null
    ? recordOrValue.CHECKTIME
    : recordOrValue
  const dateTime = new Date(value)
  if (Number.isNaN(dateTime.getTime())) {
    return null
  }

  const logicalDate = new Date(dateTime)

  return `${logicalDate.getFullYear()}-${String(logicalDate.getMonth() + 1).padStart(2, '0')}-${String(logicalDate.getDate()).padStart(2, '0')}`
}

const resolveCheckInSlotIndex = (minutes, slotMeta) => {
  if (minutes === null || !slotMeta.length) {
    return null
  }

  for (let index = 0; index < slotMeta.length; index += 1) {
    const nextRow = slotMeta[index + 1]
    const currentEnd = slotMeta[index].outMinute
    const boundary = currentEnd ?? nextRow?.inMinute ?? null

    if (boundary !== null && minutes < boundary) {
      return index
    }
  }

  return slotMeta.length - 1
}

const resolveCheckOutSlotIndex = (minutes, slotMeta, graceAfter = 0) => {
  if (minutes === null || !slotMeta.length) {
    return null
  }

  for (let index = 0; index < slotMeta.length; index += 1) {
    const slot = slotMeta[index]
    const isOvernight = Boolean(slot.isNextDay) || (slot.inMinute !== null && slot.outMinute !== null && slot.outMinute <= slot.inMinute)

    if (!isOvernight || slot.outMinute === null) {
      continue
    }

    if (minutes <= slot.outMinute + graceAfter) {
      return index
    }
  }

  for (let index = 0; index < slotMeta.length - 1; index += 1) {
    const nextStart = slotMeta[index + 1].inMinute
    if (nextStart !== null && minutes < nextStart) {
      return index
    }
  }

  return slotMeta.length - 1
}

const getShiftGraceSettings = (user) => {
  const officeShift = user?.office_shift || user?.officeShift

  return {
    enabled: Boolean(officeShift?.grace_enabled),
    before: Math.max(0, Number(officeShift?.grace_before_minutes || 0)),
    after: Math.max(0, Number(officeShift?.grace_after_minutes || 0)),
  }
}

const resolveGraceCorrectedCheckType = (user, record, slotMeta) => {
  const rawType = String(record?.CHECKTYPE || '').toUpperCase()
  const minutes = toMinutesFromDateTime(record?.CHECKTIME)
  const grace = getShiftGraceSettings(user)

  if (!grace.enabled || minutes === null || !slotMeta.length) {
    return rawType
  }

  const candidates = []

  slotMeta.forEach((slot) => {
    if (slot.inMinute !== null && minutes >= slot.inMinute - grace.before && minutes <= slot.inMinute + grace.after) {
      candidates.push({ type: 'I', distance: Math.abs(minutes - slot.inMinute) })
    }

    if (slot.outMinute !== null && minutes >= slot.outMinute - grace.before && minutes <= slot.outMinute + grace.after) {
      candidates.push({ type: 'O', distance: Math.abs(minutes - slot.outMinute) })
    }
  })

  if (!candidates.length) {
    return rawType
  }

  return candidates.sort((a, b) => a.distance - b.distance)[0].type
}

const getScheduledMinutes = (user) => {
  const slots = getScheduleSlots(user)
  if (!Array.isArray(slots) || !slots.length) {
    return null
  }

  const firstSlot = slots[0]
  const lastSlot = slots.at(-1)
  const startMinute = toMinutesFromScheduleTime(firstSlot?.time_in)
  const endMinute = toMinutesFromScheduleTime(lastSlot?.time_out)

  if (startMinute === null || endMinute === null) {
    return null
  }

  return Math.max(0, endMinute - startMinute)
}

const getActualWorkedMinutes = (row) => {
  const amIn = toMinutesFromTimeString(row?.slots?.[0]?.check_in ? formatTimeOnly(row.slots[0].check_in) : '')
  const amOut = toMinutesFromTimeString(row?.slots?.[0]?.check_out ? formatTimeOnly(row.slots[0].check_out) : '')
  const pmIn = toMinutesFromTimeString(row?.slots?.[1]?.check_in ? formatTimeOnly(row.slots[1].check_in) : '')
  const pmOut = toMinutesFromTimeString(row?.slots?.[1]?.check_out ? formatTimeOnly(row.slots[1].check_out) : '')

  let total = 0

  if (amIn !== null && amOut !== null && amOut > amIn) {
    total += amOut - amIn
  }

  if (pmIn !== null && pmOut !== null && pmOut > pmIn) {
    total += pmOut - pmIn
  }

  return total > 0 ? total : null
}

const buildAttendanceRowsFromCheckinouts = (user, checkinouts = []) => {
  const grouped = new Map()
  const records = [...checkinouts].sort((a, b) => new Date(a.CHECKTIME) - new Date(b.CHECKTIME))

  records.forEach((record) => {
    const dateKey = resolveLogicalDateKey(user, record)
    if (!dateKey) {
      return
    }

    if (!grouped.has(dateKey)) {
      grouped.set(dateKey, [])
    }

    grouped.get(dateKey).push(record)
  })

  const scheduleSlots = getScheduleSlots(user)
  const slotMeta = scheduleSlots.map((slot) => ({
    inMinute: toMinutesFromScheduleTime(slot?.time_in),
    outMinute: toMinutesFromScheduleTime(slot?.time_out),
    isNextDay: Boolean(slot?.is_next_day),
  }))
  const hasScheduleBoundaries = slotMeta.some((slot) => slot.inMinute !== null || slot.outMinute !== null)

  const buildAttendanceRow = (date, recordsInDay = []) => {
    const sorted = recordsInDay.sort((a, b) => new Date(a.CHECKTIME) - new Date(b.CHECKTIME))
    const normalizedPunches = []

    sorted.forEach((item) => {
      const type = resolveGraceCorrectedCheckType(user, item, slotMeta)
      if (type !== 'I' && type !== 'O') {
        return
      }

      const lastPunch = normalizedPunches[normalizedPunches.length - 1]
      if (!lastPunch || lastPunch.type !== type) {
        normalizedPunches.push({ type, time: item.CHECKTIME })
        return
      }

      if (type === 'I' && hasScheduleBoundaries) {
        normalizedPunches.push({ type, time: item.CHECKTIME })
        return
      }

      if (type === 'O') {
        lastPunch.time = item.CHECKTIME
      }
    })

    const sessions = []
    let currentSession = null

    normalizedPunches.forEach((punch) => {
      if (punch.type === 'I') {
        if (!currentSession || (currentSession.check_in && currentSession.check_out)) {
          currentSession = { check_in: punch.time, check_out: null }
        } else if (currentSession.check_in && !currentSession.check_out) {
          sessions.push(currentSession)
          currentSession = { check_in: punch.time, check_out: null }
        } else {
          currentSession = { check_in: punch.time, check_out: null }
        }
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
      }
    })

    if (currentSession && (currentSession.check_in || currentSession.check_out)) {
      sessions.push(currentSession)
    }

    const slots = scheduleSlots.map(() => ({ check_in: null, check_out: null }))

    if (hasScheduleBoundaries) {
      normalizedPunches.forEach((punch) => {
        const minutes = toMinutesFromDateTime(punch.time)
        const grace = getShiftGraceSettings(user)
        const graceAfter = grace.enabled ? grace.after : 0
        const slotIndex = punch.type === 'I'
          ? resolveCheckInSlotIndex(minutes, slotMeta)
          : resolveCheckOutSlotIndex(minutes, slotMeta, graceAfter)

        if (slotIndex === null || !slots[slotIndex]) {
          return
        }

        if (punch.type === 'I') {
          if (!slots[slotIndex].check_in || new Date(punch.time) < new Date(slots[slotIndex].check_in)) {
            slots[slotIndex].check_in = punch.time
          }
          return
        }

        if (!slots[slotIndex].check_out || new Date(punch.time) > new Date(slots[slotIndex].check_out)) {
          slots[slotIndex].check_out = punch.time
        }
      })
    } else {
      sessions.slice(0, slots.length).forEach((session, index) => {
        slots[index] = {
          check_in: session.check_in,
          check_out: session.check_out,
        }
      })
    }

    return { date, slots }
  }

  const dateKeys = getSelectedDateKeys()
  const rows = []

  for (const dateKey of dateKeys) {
    rows.push(buildAttendanceRow(dateKey, grouped.get(dateKey) || []))
  }

  return rows.sort((a, b) => new Date(a.date) - new Date(b.date))
}

const buildPrintableAttendanceRecords = (user, checkinouts = []) => {
  const scheduledMinutes = getScheduledMinutes(user)

  return buildAttendanceRowsFromCheckinouts(user, checkinouts).map((row) => {
    const [year, month, day] = String(row.date).split('-').map(Number)
    const dateObj = new Date(year, month - 1, day)
    const amIn = row.slots[0]?.check_in ? formatTimeOnly(row.slots[0].check_in) : ''
    const amOut = row.slots[0]?.check_out ? formatTimeOnly(row.slots[0].check_out) : ''
    const pmIn = row.slots[1]?.check_in ? formatTimeOnly(row.slots[1].check_in) : ''
    const pmOut = row.slots[1]?.check_out ? formatTimeOnly(row.slots[1].check_out) : ''
    const actualWorkedMinutes = getActualWorkedMinutes(row)
    const undertimeMinutes = scheduledMinutes !== null && actualWorkedMinutes !== null
      ? Math.max(0, scheduledMinutes - actualWorkedMinutes)
      : 0
    const undertime = formatUndertimeParts(undertimeMinutes)

    return {
      date: row.date,
      dateDisplay: dateObj.toLocaleDateString('en-US', { month: 'short', day: '2-digit' }),
      am_in: amIn,
      am_out: amOut,
      pm_in: pmIn,
      pm_out: pmOut,
      undertimeHrs: undertime.hrs,
      undertimeMin: String(undertime.min).padStart(2, '0'),
    }
  })
}

const getPrintableRecords = (user) => {
  const records = Array.isArray(user?._printable_attendance_records) && user._printable_attendance_records.length
    ? user._printable_attendance_records
    : (Array.isArray(user?.attendance_records) ? user.attendance_records : [])

  if (calculateUndertime.value) {
    return records
  }

  return records.map((record) => ({
    ...record,
    undertimeHrs: '',
    undertimeMin: '',
  }))
}
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
      <section
        class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] p-5 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
        <div class="flex flex-col gap-1 xl:flex-row xl:items-end xl:justify-between">
          <div class="max-w-xl">
            <p class="hidden text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">Attendance Reporting Deck</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white lg:text-4xl">Biometric Report</h1>
            
            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
              <span
                class="inline-flex rounded-full bg-white/10 px-3 py-1 font-medium text-slate-100 ring-1 ring-inset ring-white/10">
                Period: {{ periodLabel }}
              </span>
              <span class="inline-flex rounded-full px-3 py-1 font-medium ring-1 ring-inset"
                :class="loading ? 'bg-amber-400/15 text-amber-100 ring-amber-300/30' : 'bg-emerald-400/15 text-emerald-100 ring-emerald-300/30'">
                {{ loading ? 'Generating Report...' : (preparingPrintData ? 'Preparing Print Data...' : 'Ready to Generate') }}
              </span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-1 sm:grid-cols-4 xl:min-w-[500px]">
            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
              <p class="text-xs  tracking-[0.25em] text-slate-300">Users</p>
              <p class="mt-2 text-3xl font-semibold text-white">{{ reportUsers.length }}</p>
              <p class="mt-1 text-xs text-slate-300">Matched records</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
              <p class="text-xs  tracking-[0.25em] text-slate-300">Selected</p>
              <p class="mt-2 text-3xl font-semibold text-white">{{ selectedCount }}</p>
              <p class="mt-1 text-xs text-slate-300">Ready to print</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
              <p class="text-xs  tracking-[0.25em] text-slate-300">Unselected</p>
              <p class="mt-2 text-3xl font-semibold text-white">{{ unselectedCount }}</p>
              <p class="mt-1 text-xs text-slate-300">Excluded from print</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
              <p class="text-xs  tracking-[0.25em] text-slate-300">Copies/User</p>
              <p class="mt-2 text-3xl font-semibold text-white">{{ copiesPerUser }}</p>
              <p class="mt-1 text-xs text-slate-300">Print multiplier</p>
            </div>
          </div>
        </div>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200/90 hidden ">
              Generate and print attendance forms for multiple users by month, office shift, department, and college.
            </p>
      </section>
      <aside
        class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Print Options</h3>
        <div class="mt-3 space-y-3">
          <label
            class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-700">
            <span class="font-medium text-slate-700 dark:text-slate-200">Calculate Undertime</span>
            <input v-model="calculateUndertime" type="checkbox" class="h-4 w-4" />
          </label>
          <div class="flex gap-2">
          <div class="w-full">
            <label
              class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Copies
              Per User</label>
            <select v-model.number="copiesPerUser"
              class="h-11 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
              <option v-for="n in 10" :key="`copies-${n}`" :value="n">{{ n }}</option>
            </select>
          </div>
          <button @click="printReport" type="button"
            class="mt-auto inline-flex h-11 w-full items-center justify-center rounded-lg border border-sky-200 bg-sky-50 px-4 text-sm font-medium text-sky-700 transition hover:bg-sky-100 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/30">
            {{ preparingPrintData ? 'Preparing...' : 'Print Selected' }}
          </button>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400">Only selected users will be included in printing.</p>
        </div>
      </aside>
    </div>
    <section class="grid gap-4 ">
      <div
        class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03] lg:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Report Filters</h2>
            <!-- <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set criteria to generate the attendance
              population.</p> -->
          </div>
          
        </div>

        <div class="mt-2 space-y-3">
          <div>
            <label
              class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Filter Mode</label>
            <div class="inline-flex rounded-lg border border-slate-300 p-1 dark:border-slate-700">
              <button type="button" @click="filterMode = 'monthly'"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="filterMode === 'monthly' ? 'bg-sky-500 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'">
                Month
              </button>
              <button type="button" @click="filterMode = 'custom'"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="filterMode === 'custom' ? 'bg-sky-500 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'">
                Custom
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
            <div v-if="filterMode === 'monthly'">
              <label
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Year</label>
              <select v-model.number="filters.year"
                class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
                <option v-for="year in yearOptions" :key="`year-${year}`" :value="year">{{ year }}</option>
              </select>
            </div>
            <div v-if="filterMode === 'monthly'">
              <label
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Month</label>
              <select v-model.number="filters.month"
                class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
                <option v-for="month in monthOptions" :key="`month-${month.value}`" :value="month.value">{{ month.label }}
                </option>
              </select>
            </div>
            <div v-if="filterMode === 'custom'" class="md:col-span-2">
              <label
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date Range</label>
              <flat-pickr
                v-model="customDateRange"
                :config="customRangePickerConfig"
                class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700"
                placeholder="Select date range"
              />
            </div>
            <div>
              <label
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Office
                Shift</label>
              <select v-model="filters.office_shift_id"
                class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
                <option value="">All</option>
                <option v-for="shift in officeShifts" :key="`report-shift-${shift.id}`" :value="String(shift.id)">{{
                  shift.name }}</option>
              </select>
            </div>
            <div>
              <label
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Department</label>
              <select v-model="filters.department_id"
                class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
                <option value="">All</option>
                <option v-for="department in departments" :key="`report-department-${department.id}`"
                  :value="String(department.id)">{{ department.department_name }}</option>
              </select>
            </div>
            <div>
              <label
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">College</label>
              <select v-model="filters.college_id"
                class="h-10 w-full rounded-lg border border-slate-300 bg-transparent px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700">
                <option value="">All</option>
                <option v-for="college in colleges" :key="`report-college-${college.id}`" :value="String(college.id)">{{
                  college.college_long || college.college_short || `College #${college.id}` }}</option>
              </select>
            </div>
            <Button @click="generateReport" size="sm" variant="primary"
              :className="'mt-auto h-11 bg-sky-500 hover:bg-sky-600 text-white'">Generate</Button>
          </div>
        </div>
      </div>


    </section>

    <section
      class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
        <div class="text-sm text-slate-600 dark:text-slate-300">
          <span class="font-semibold text-slate-900 dark:text-white">{{ loading ? 'Generating...' : reportUsers.length
            }}</span> user(s) matched for {{ periodLabel }}
          <span v-if="!loading" class="ml-2">({{ selectedCount }} selected)</span>
          <span v-if="preparingPrintData" class="ml-2 text-xs text-amber-600 dark:text-amber-300">Preparing print data...</span>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="bg-slate-50 dark:bg-slate-900/60">
            <tr>
              <th class="px-4 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="h-4 w-4" />
              </th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Office Shift
              </th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Department
              </th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">College</th>
              <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Biometrics
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr v-for="user in reportUsers" :key="`report-user-${user.id}`"
              class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <td class="px-4 py-2 text-center text-sm">
                <input v-model="selectedUserIds" type="checkbox" :value="user.id" class="h-4 w-4" />
              </td>
              <td class="px-4 py-2 text-sm font-medium text-slate-800 dark:text-slate-100">{{ user.name }}</td>
              <td class="px-4 py-2 text-sm text-slate-700 dark:text-slate-200">{{ user.office_shift?.name || '-' }}</td>
              <td class="px-4 py-2 text-sm text-slate-700 dark:text-slate-200">{{ user.department || '-' }}</td>
              <td class="px-4 py-2 text-sm text-slate-700 dark:text-slate-200">{{ user.college || '-' }}</td>
              <td class="px-4 py-2 text-sm">
                <button @click="openBiometricLogs(user)" type="button"
                  class="rounded-md border border-sky-200 px-2.5 py-1 text-xs font-medium text-sky-700 transition hover:bg-sky-50 dark:border-sky-800/60 dark:text-sky-300 dark:hover:bg-sky-900/20">
                  View All Logs
                </button>
              </td>
            </tr>
            <tr v-if="!reportUsers.length && !loading">
              <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">No report data yet. Apply filters and
                click Generate.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Modal v-if="biometricModalOpen" @close="closeBiometricLogs">
      <template #body>
        <div
          class="relative m-2 w-full max-w-5xl max-h-[92vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-800 dark:bg-slate-950 lg:p-6">
          <section
            class="overflow-hidden rounded-[24px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_45%,_#0f766e_100%)] p-5 text-white shadow-sm dark:border-slate-800">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
              <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">Attendance Audit</p>
                <h4 class="mt-3 text-2xl font-semibold tracking-tight text-white">Raw Biometric Logs</h4>
                <p class="mt-2 text-sm text-slate-200/90">
                  {{ biometricLogUser?.name || '-' }} - {{ periodLabel }}
                </p>
                <p class="mt-2 text-xs text-slate-300/90">All entries are shown as-is, including duplicate IN/OUT
                  punches.</p>
              </div>

              <div class="grid grid-cols-3 gap-3 sm:min-w-[340px]">
                <div class="rounded-2xl border border-white/10 bg-white/10 p-3 backdrop-blur-sm">
                  <p class="text-[10px] uppercase tracking-[0.24em] text-slate-300">Total</p>
                  <p class="mt-1 text-2xl font-semibold text-white">{{ mergedBiometricLogs.length }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 p-3 backdrop-blur-sm">
                  <p class="text-[10px] uppercase tracking-[0.24em] text-slate-300">Check In</p>
                  <p class="mt-1 text-2xl font-semibold text-white">{{mergedBiometricLogs.filter(log =>
                    normalizeCheckType(log?.CHECKTYPE) === 'I').length }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 p-3 backdrop-blur-sm">
                  <p class="text-[10px] uppercase tracking-[0.24em] text-slate-300">Check Out</p>
                  <p class="mt-1 text-2xl font-semibold text-white">{{mergedBiometricLogs.filter(log =>
                    normalizeCheckType(log?.CHECKTYPE) === 'O').length }}</p>
                </div>
              </div>
            </div>
          </section>

          <div class="mt-4 flex items-center justify-between gap-2">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Chronological Event Stream
            </p>
            <button @click="closeBiometricLogs" type="button"
              class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
              Close
            </button>
          </div>

          <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
            <div class="max-h-[60vh] overflow-auto">
              <table class="min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-900/70">
                  <tr>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      #</th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      Type</th>
                    <th
                      class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                      Date/Time</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr v-if="biometricModalLoading">
                    <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">Loading
                      biometric logs...</td>
                  </tr>
                  <tr v-else-if="!mergedBiometricLogs.length">
                    <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No
                      biometric logs for selected user and period.</td>
                  </tr>
                  <tr v-else v-for="(log, index) in mergedBiometricLogs" :key="`log-${index}-${log.CHECKTIME}`"
                    class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
                    <td class="px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-200">{{ index + 1 }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                      <div class="flex items-center gap-2">
                        <span
                          class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.2em]"
                          :class="checkTypeBadgeClass(log.CHECKTYPE)">
                          {{ formatCheckTypeLabel(log.CHECKTYPE) }}
                        </span>
                        <span v-if="log._override_action"
                          class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                          {{ log._override_action === 'add' ? 'added' : log._override_action }}
                        </span>
                      </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ formatLogDateTime(log.CHECKTIME)
                      }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>
    </Modal>

    <div class="hidden">
      <PrintableAttendance v-for="user in reportUsers" :key="`report-printable-${user.id}`" ref="printableRefs"
        :user="user" :selected-year="selectedPeriodYear" :selected-month="selectedPeriodMonth"
        :attendance-records="getPrintableRecords(user)" :company-name="companySchoolName"
        :company-logo="companySchoolLogo" :show-logo="companySchoolLogoPrintEnabled" :show-controls="false"
        :calculate-undertime="calculateUndertime" />
    </div>
  </div>
</template>
