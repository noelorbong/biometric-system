import assert from 'node:assert/strict'
import { absenceCsv, absenceValues, buildAbsenceSummary } from '../../resources/js/utils/absenceSummary.js'

const employees = Array.from({ length: 1314 }, (_, index) => ({
  id: index + 1, name: `Employee ${index + 1}`, shift_name: 'Regular',
  late_count: 1, undertime_count: 2, total_minutes: 95,
  days: [{ date: '2026-11-02', absence: null }],
}))
employees[1313] = {
  ...employees[1313], name: 'Last, "Employee"',
  days: [
    { date: '2026-11-02', absence: { status: 'filed', duration: 'morning', leave_type: 'sick' } },
    { date: '2026-11-03', absence: { status: 'filed', duration: 'whole_day', leave_type: 'vacation' } },
    { date: '2026-11-04', absence: { status: 'unfiled', duration: 'afternoon', leave_type: 'without_pay' } },
  ],
}
const visible = buildAbsenceSummary({ employees: employees.slice(0, 50) })
const full = buildAbsenceSummary({ employees })
assert.equal(visible.length, 50)
assert.equal(full.length, 1314)
assert.deepEqual(buildAbsenceSummary(null), [])
const last = full.at(-1)
assert.equal(last.filed, 2)
assert.equal(last.daysAbsent, 2)
assert.equal(last.sick, 0.5)
assert.equal(last.vacation, 1)
assert.equal(last.without_pay, 0.5)
assert.equal(last.times, 3)
assert.equal(last.hours, 1)
assert.equal(last.minutes, 35)
const csv = absenceCsv(full)
assert.equal(csv.split('\r\n').length, 1315, 'CSV must include all matching employees plus its header.')
assert.ok(csv.includes('"Last, ""Employee"""'), 'CSV must escape names correctly.')
assert.equal(full.map(absenceValues).length, 1314, 'Print data must include every employee.')
assert.deepEqual(absenceValues(last).slice(4, 9), [0, 1, '0.5', 0, '0.5'])
console.log('Absence report checks passed: 50 displayed versus 1314 CSV/print records, with half-day leave totals and escaped names preserved.')
