<script setup>
import { computed } from 'vue'
const props = defineProps({
  user: Object,
  layoutSettings: { type: Object, default: () => ({}) },
  companyName: String,
  headerAddress: String,
  companyLogo: String,
  monthYear: String,
  days: Array,
  recordValue: Function,
  undertimeValue: Function,
  timekeeperName: String,
  timekeeperSignature: String,
  signatoryName: String,
  signatorySignature: String,
})
const layout = computed(() => {
  const settings = props.layoutSettings
  const size = (key, fallback) => {
    const value = Number(settings[`biometricDtrTemplate2${key}`] ?? fallback)
    return Number.isFinite(value) ? Math.min(18, Math.max(1, value)) : fallback
  }
  return {
    '--dtr-republic-size': `${size('RepublicFontSize', 8)}pt`,
    '--dtr-department-size': `${size('DepartmentFontSize', 8)}pt`,
    '--dtr-address-size': `${size('AddressFontSize', 8)}pt`,
    '--dtr-title-size': `${size('TitleFontSize', 13)}pt`,
    '--dtr-name-size': `${size('NameFontSize', 10)}pt`,
    '--dtr-company-size': `${size('CompanyFontSize', 8)}pt`,
    '--dtr-approved-size': `${size('ApprovedFontSize', 10)}pt`,
    '--dtr-timekeeper-size': `${size('TimekeeperFontSize', 10)}pt`,
    '--dtr-month-size': `${size('MonthFontSize', 10)}pt`,
    '--dtr-table-content-size': `${size('TableContentFontSize', 7)}pt`,
    '--dtr-table-header-size': `${size('TableHeaderFontSize', 7)}pt`,
    '--dtr-employee-size': `${size('EmployeeFontSize', 10)}pt`,
  }
})
const logoPosition = computed(() => {
  const position = props.layoutSettings.biometricDtrTemplate2LogoPosition
  if (position === 'center') return 'top'
  return ['top', 'left', 'right', 'bottom'].includes(position) ? position : 'left'
})
const logoOffsetStyle = computed(() => {
  const defaultOffsets = { Left: 0, Right: 4, Top: 0, Bottom: 3 }
  const offset = direction => {
    const value = Number(props.layoutSettings[`biometricDtrTemplate2Logo${direction}`] ?? defaultOffsets[direction])
    return Number.isFinite(value) ? Math.max(0, Math.min(30, value)) : defaultOffsets[direction]
  }
  const dimension = name => {
    const value = Number(props.layoutSettings[`biometricDtrTemplate2Logo${name}`] ?? 15)
    return `${Number.isFinite(value) ? Math.max(1, Math.min(50, value)) : 15}mm`
  }
  return {
    translate: `${offset('Right') - offset('Left')}mm ${offset('Bottom') - offset('Top')}mm`,
    width: dimension('Width'),
    height: dimension('Height'),
  }
})
const departmentName = computed(() => props.user?.department_ref?.department_name
  || props.user?.departmentRef?.department_name
  || (typeof props.user?.department === 'string' ? props.user.department : props.user?.department?.department_name)
  || '')

const undertime = (day, value) => {
  const hours = value(day, 'hrs')
  const minutes = value(day, 'min')
  return hours || minutes ? `${hours || '0'}:${String(minutes || '0').padStart(2, '0')}` : ''
}
</script>

<template>
  <article class="dtr-two" :style="layout" :class="`dtr-two-logo-${logoPosition}`">
    <header class="dtr-two-header" :class="{ 'dtr-two-header-with-logo': companyLogo }">
      <img v-if="companyLogo" :src="companyLogo" :style="logoOffsetStyle" alt="Company logo" />
      <div class="dtr-two-republic">Republic of the Philippines</div>
      <strong class="dtr-two-company">{{ companyName }}</strong>
      <strong v-if="departmentName" class="dtr-two-department">{{ departmentName }}</strong>
      <div v-if="headerAddress" class="dtr-two-address">{{ headerAddress }}</div>
    </header>
    <h1>DAILY TIME RECORD</h1>
    <div class="dtr-two-info">
      <span class="dtr-two-name">Name: <strong>{{ user?.name }}</strong></span>
      <span class="dtr-two-month">For the Month of: <strong>{{ monthYear }}</strong></span>
    </div>
    <table>
      <colgroup>
        <col style="width: 6%" />
        <col v-for="column in 6" :key="column" style="width: 9%" />
        <col style="width: 8%" />
        <col style="width: 8%" />
        <col style="width: 24%" />
      </colgroup>
      <thead>
        <tr>
          <th rowspan="2">DATE</th><th colspan="2">AM</th><th colspan="2">PM</th>
          <th colspan="2">OVER<wbr />TIME</th><th rowspan="2">UNDER<br />TIME</th>
          <th rowspan="2">OVER<br />TIME</th><th rowspan="2">REMARKS</th>
        </tr>
        <tr><th v-for="(label, i) in ['IN', 'OUT', 'IN', 'OUT', 'IN', 'OUT']" :key="i">{{ label }}</th></tr>
      </thead>
      <tbody>
        <tr v-for="day in days" :key="day">
          <td>{{ day }}</td>
          <td v-for="field in ['am_in', 'am_out', 'pm_in', 'pm_out']" :key="field">{{ recordValue(day, field) }}</td>
          <td></td><td></td><td>{{ undertime(day, undertimeValue) }}</td><td></td><td></td>
        </tr>
        <tr class="dtr-two-total"><td colspan="10">TOTAL</td></tr>
      </tbody>
    </table>
    <p class="dtr-two-certification">I CERTIFY on my honor that the above statement is correct and that the services were applied to the public service.</p>
    <p class="dtr-two-dated">Dated at {{ departmentName || companyName }} on, __________________</p>
    <div class="dtr-two-employee"><strong>{{ user?.name }}</strong><div>Signature</div></div>
    <div class="dtr-two-footer">
      <div class="dtr-two-approved">
        <p><strong>Approved:</strong> <em>as certified and verified</em></p>
        <div class="dtr-two-signature">
          <img v-if="signatorySignature" :src="signatorySignature" alt="Signatory e-signature" />
          <strong>{{ signatoryName }}</strong>
        </div>
      </div>
      <div>
        <p class="dtr-two-certification">I CERTIFY ON MY HONOR that the record of the hour of work was made daily at the time of arrival and departure from office and at beginning and ending of works.</p>
        <div class="dtr-two-keeper-signature">
          <img v-if="timekeeperSignature" :src="timekeeperSignature" alt="Time keeper e-signature" />
          <strong>{{ timekeeperName }}</strong>
        </div>
        <div class="dtr-two-keeper">Time Keeper</div>
      </div>
    </div>
  </article>
</template>

<style src="@/utils/dtrTemplateTwo.css"></style>
