import assert from 'node:assert/strict'
import { buildMonthlyGrid } from '../../resources/js/utils/monthlyReportGrid.js'
import { monthlyPrintTable } from '../../resources/js/utils/monthlyReportPrint.js'

const days = Array.from({ length: 30 }, (_, index) => {
  const date = new Date(2026, 10, index + 1)
  return { date: `2026-11-${String(index + 1).padStart(2, '0')}`, day: index + 1, weekday: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][date.getDay()], weekend: [0, 6].includes(date.getDay()), future: false, holidays: [] }
})
const periods = [
  { key: 'AM', end: '12:00', label: 'AM', nextDay: false },
  { key: 'PM', end: '17:00', label: 'PM', nextDay: false },
  { key: '0-18:00', end: '18:00', label: '18:00', nextDay: false },
]
const employees = Array.from({ length: 1314 }, (_, index) => ({
  id: index + 1, name: index === 1313 ? '<script>unsafe</script>' : `Employee ${String(index + 1).padStart(4, '0')}`,
  shift_name: 'Regular', total_minutes: index, late_count: index, undertime_count: 0,
  days: days.map(day => ({ date: day.date, rest_day: day.weekend, working_day: !day.weekend, absence: null, holidays: [], periods: [
    { scheduled_in: '08:00', scheduled_out: '12:00', next_day: false, late_minutes: index, undertime_minutes: 0, in_state: 'Recorded', out_state: 'Recorded', exempt: false },
    { scheduled_in: '13:00', scheduled_out: index > 1000 ? '18:00' : '17:00', next_day: false, late_minutes: 0, undertime_minutes: 0, in_state: 'Recorded', out_state: 'Recorded', exempt: false },
  ] })),
}))
const report = { month_label: 'November 2026', days, employees, periods, excluded_dates: Object.fromEntries(days.map(day => [day.date, day.weekend])) }
const empty = buildMonthlyGrid(null)
assert.equal(empty.rows.length, 0)
const first = buildMonthlyGrid({ ...report, employees: employees.slice(0, 50) }, { sort: 'name' })
const second = buildMonthlyGrid({ ...report, employees: employees.slice(50, 100) }, { sort: 'name' })
assert.equal(first.rows.length, 50)
assert.equal(first.periods.length, 3, 'Off-page shift periods must remain in the visible columns.')
assert.deepEqual(first.gridColumns, second.gridColumns, 'Page changes must not shift column layouts.')
const full = buildMonthlyGrid(report, { sort: 'name' })
assert.equal(full.rows.length, 1314)
assert.equal(full.absencesRows.length, 1314)
const html = monthlyPrintTable(report, full)
assert.equal((html.match(/<td class="employee-name">/g) || []).length, 1314, 'Printing must include every employee, not just 50.')
assert.ok(html.includes('Employee 1313'))
assert.ok(html.includes('&lt;script&gt;unsafe&lt;/script&gt;'))
assert.ok(!html.includes('<script>unsafe'))
assert.equal(buildMonthlyGrid(report, { search: 'Employee 1313' }).rows.length, 1)
console.log(`Monthly grid checks passed: ${first.rows.length} visible employees versus ${full.rows.length} exported employees, stable columns and escaped print output.`)
