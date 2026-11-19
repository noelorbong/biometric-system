const leaveTypes = ['cto', 'vacation', 'sick', 'spl', 'without_pay']
const leaveLabel = type => ({ cto: 'CTO', vacation: 'Vacation', sick: 'Sick', spl: 'SPL', without_pay: 'W/out Pay' }[type] || 'Unclassified')
const durationValue = duration => duration === 'whole_day' ? 1 : 0.5
const durationLabel = duration => duration === 'whole_day' ? '' : duration === 'morning' ? ' AM' : ' PM'

export function buildAbsenceSummary(report) {
    return (report?.employees || []).map(employee => {
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
    })
}

export const summaryNumber = value => Number.isInteger(value) ? value : Number(value || 0).toFixed(1)
export function absenceValues(row) {
  return [row.name, row.filed, summaryNumber(row.daysAbsent), row.dates, ...leaveTypes.map(type => summaryNumber(row[type])), row.times, row.hours, row.minutes]
}
export function absenceCsv(rows) {
  const values = [['Employee name', 'Filed', 'Days absent', 'Date absent', 'CTO', 'Vacation', 'Sick', 'SPL', 'W/out Pay', 'Times undertime/tardy', 'Hours', 'Minutes'], ...rows.map(absenceValues)]
  return values.map(row => row.map(value => `"${String(value ?? '').replaceAll('"', '""')}"`).join(',')).join('\r\n')
}
