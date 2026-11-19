const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]))

// Build from the full export model, independently of the currently visible page.
export function monthlyPrintTable(data, view) {
  const { periods, gridColumns, excludedDate, rows } = view
  const header = `<thead><tr><th rowspan="4" class="employee-name">Employee Name</th><th colspan="${gridColumns.length}">For the Month of ${escape(data.month_label)}</th><th rowspan="4" class="total-cell">Total minutes<br>(Tardy + Undertime)</th><th rowspan="4" class="total-cell">Total times<br>tardy</th><th rowspan="4" class="total-cell">Total times<br>undertime</th></tr>
    <tr>${data.days.map(day => `<th colspan="${excludedDate(day) ? 1 : periods.length * 2}" class="${day.weekend ? 'weekend' : ''}">${day.day}${day.holidays.length ? '*' : ''}</th>`).join('')}</tr>
    <tr>${data.days.map(day => excludedDate(day) ? `<th rowspan="2">${escape(day.weekday)}</th>` : periods.map(period => `<th colspan="2">${escape(period.label)}</th>`).join('')).join('')}</tr>
    <tr>${gridColumns.filter(column => !column.weekend).map(column => `<th>${column.direction === 'in' ? 'I' : 'O'}</th>`).join('')}</tr></thead>`
  const body = rows.map((row, index) => `<tr><td class="employee-name">${index + 1}. ${escape(row.name)}</td>${row.gridCells.map(cell => `<td class="${cell.holiday ? 'holiday-cell' : cell.missing ? 'missing-record' : cell.weekend ? 'weekend-cell' : cell.exempt ? 'exempt' : ''}">${cell.weekend ? '' : escape(cell.display)}</td>`).join('')}<td>${row.total_minutes}</td><td>${row.late_count}</td><td>${row.undertime_count}</td></tr>`).join('')
  return `<table>${header}<tbody>${body}</tbody></table>`
}
