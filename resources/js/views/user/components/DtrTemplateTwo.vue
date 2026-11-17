<script setup>
import { computed } from 'vue'
const props = defineProps({
  user: Object,
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
  <article class="dtr-two">
    <header class="dtr-two-header">
      <img v-if="companyLogo" :src="companyLogo" alt="Company logo" />
      <div>Republic of the Philippines</div>
      <strong>{{ companyName }}</strong>
      <strong v-if="departmentName">{{ departmentName }}</strong>
      <div v-if="headerAddress">{{ headerAddress }}</div>
    </header>
    <h1>DAILY TIME RECORD</h1>
    <div class="dtr-two-info">
      <span>Name: <strong>{{ user?.name }}</strong></span>
      <span>For the Month of: <strong>{{ monthYear }}</strong></span>
    </div>
    <table>
      <colgroup>
        <col style="width: 5%" />
        <col v-for="n in 6" :key="n" style="width: 9%" />
        <col style="width: 7%" /><col style="width: 7%" /><col style="width: 27%" />
      </colgroup>
      <thead>
        <tr>
          <th rowspan="2">DATE</th><th colspan="2">AM</th><th colspan="2">PM</th>
          <th colspan="2">OVERTIME</th><th rowspan="2">UNDER<br />TIME</th>
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
