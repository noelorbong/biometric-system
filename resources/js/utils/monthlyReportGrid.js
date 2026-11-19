import { computed } from 'vue'

const leaveTypes = ['cto', 'vacation', 'sick', 'spl', 'without_pay']

export function buildMonthlyGrid(data, filters = {}) {
  const report = { value: data }
  const search = { value: filters.search || '' }
  const issuesOnly = { value: Boolean(filters.issuesOnly) }
  const sort = { value: filters.sort || 'late_count' }
const keyFor = period => period.next_day ? `1-${period.scheduled_out}` : period.scheduled_out === '12:00' ? 'AM' : period.scheduled_in === '13:00' ? 'PM' : `0-${period.scheduled_out}`
const periods = computed(() => {
  if (report.value?.periods?.length) return report.value.periods
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
const excludedDate = day => report.value?.excluded_dates && day.date in report.value.excluded_dates ? report.value.excluded_dates[day.date] : report.value?.employees.length ? report.value.employees.every(employee => {
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

const leaveLabel = type => ({ cto: 'CTO', vacation: 'Vacation', sick: 'Sick', spl: 'SPL', without_pay: 'W/out Pay' }[type] || 'Unclassified')
const durationValue = duration => duration === 'whole_day' ? 1 : 0.5
const durationLabel = duration => duration === 'whole_day' ? '' : duration === 'morning' ? ' AM' : ' PM'
const numberValue = value => Number.isInteger(value) ? value : Number(value || 0).toFixed(1)
const absencesRows = computed(() => {
  const term = search.value.trim().toLowerCase()

  return (report.value?.employees || []).map(employee => {
    const absences = employee.days.filter(day => day.absence).map(day => ({ date: day.date, ...day.absence }))
    const leaveTotals = Object.fromEntries(leaveTypes.map(type => [type, 0]))

    for (const absence of absences) {
      leaveTotals[absence.leave_type || 'without_pay'] += durationValue(absence.duration)
    }

    return {
      id: employee.id,
      name: employee.name,
      shift: employee.shift_name,
      filed: absences.filter(absence => absence.status === 'filed').length,
      daysAbsent: absences.reduce((sum, absence) => sum + durationValue(absence.duration), 0),
      dates: absences.map(absence => `${new Date(`${absence.date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}${durationLabel(absence.duration)} (${leaveLabel(absence.leave_type || 'without_pay')})`).join(', '),
      ...leaveTotals,
      times: employee.late_count + employee.undertime_count,
      late_count: employee.late_count,
      undertime_count: employee.undertime_count,
      hours: Math.floor(employee.total_minutes / 60),
      minutes: employee.total_minutes % 60,
    }
  }).filter(row => !term || `${row.name} ${row.shift}`.toLowerCase().includes(term))
})


  return { periods: periods.value, excludedDate, dayColumns: dayColumns.value, gridColumns: gridColumns.value, rows: rows.value, totals: totals.value, absencesRows: absencesRows.value }
}
