<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import flatPickr from 'vue-flatpickr-component'
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect'
import 'flatpickr/dist/flatpickr.css'
import 'flatpickr/dist/plugins/monthSelect/style.css'

const month = ref('')
const data = ref({ office_shifts: [], users: [], rules: [], suggestions: [], suspensions: [] })
const busy = ref(false)
const error = ref('')
const notice = ref('')
const loaded = ref(false)
const scheduleDialog = ref(null)
const scheduleOpen = ref(false)
const form = ref({ kind: 'permanent', office_shift_id: '', user_id: '', profile_key: 'compressed', source_shift_id: '', effective_from: '', working_days: [1, 2, 3, 4], reason: '' })
const suspension = ref({ name: '', suspension_date: '', duration: 'full_day', office_shift_id: '' })
const dateConfig = { dateFormat: 'Y-m-d', altInput: true, altFormat: 'F j, Y', disableMobile: true }
const scheduleDateConfig = { ...dateConfig, static: true }
const monthConfig = { altInput: true, disableMobile: true, plugins: [new monthSelectPlugin({ dateFormat: 'Y-m', altFormat: 'F Y' })] }
const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const employees = computed(() => data.value.users.filter(u => String(u.office_shift_id) === String(form.value.office_shift_id)))
const ruleLimit = ref(10)
const filteredRules = computed(() => data.value.rules.filter(rule => !month.value || (
  rule.effective_from.slice(0, 7) <= month.value
  && (!rule.effective_to || rule.effective_to.slice(0, 7) >= month.value)
)))
const visibleRules = computed(() => filteredRules.value.slice(0, ruleLimit.value))
watch(() => form.value.office_shift_id, () => { form.value.user_id = '' })
watch(() => form.value.profile_key, value => { form.value.working_days = value === 'existing' ? [1, 2, 3, 4, 5] : [1, 2, 3, 4] })
watch(month, () => {
  ruleLimit.value = 10
  if (loaded.value && !busy.value) load()
})

function openSchedule() {
  error.value = ''
  scheduleOpen.value = true
  scheduleDialog.value.showModal()
}
function closeSchedule() {
  if (busy.value) return
  scheduleDialog.value.close()
  scheduleOpen.value = false
}

async function load() {
  busy.value = true
  error.value = ''
  try {
    const response = await axios.post('/api/work-schedules', { month: month.value || null })
    data.value = response.data
  } catch (e) { error.value = e.response?.data?.message || 'Unable to load work schedules.' }
  finally { busy.value = false; loaded.value = true }
}
async function saveRule() {
  busy.value = true; error.value = ''; notice.value = ''
  try {
    await axios.post('/api/work-schedule/store', { ...form.value, user_id: form.value.user_id || null, source_shift_id: form.value.source_shift_id || null })
    scheduleDialog.value.close()
    scheduleOpen.value = false
    notice.value = 'Schedule saved. Attendance and reports will use it for the applicable dates.'
    await load()
  } catch (e) { error.value = e.response?.data?.message || 'Unable to save schedule.' }
  finally { busy.value = false }
}
async function saveSuspension() {
  busy.value = true; error.value = ''; notice.value = ''
  try {
    await axios.post('/api/work-suspension/store', { ...suspension.value, office_shift_id: suspension.value.office_shift_id || null })
    notice.value = 'Work suspension saved.'
    suspension.value.name = ''; suspension.value.suspension_date = ''
    await load()
  } catch (e) { error.value = e.response?.data?.message || 'Unable to save suspension.' }
  finally { busy.value = false }
}
async function reviewSuggestion(suggestion) {
  form.value.office_shift_id = suggestion.office_shift_id
  // Let the scope watcher clear the previous selection first.
  await nextTick()
  Object.assign(form.value, { kind: 'weekly', user_id: suggestion.user_id || '', profile_key: 'standard', working_days: [1, 2, 3, 4], effective_from: suggestion.week_start, reason: suggestion.reason })
  openSchedule()
}
async function remove(type, id) {
  if (busy.value) return
  const result = await Swal.fire({
    title: type === 'work-schedule' ? 'Remove this schedule?' : 'Remove this work suspension?',
    text: 'Reports for affected dates will be recalculated.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, remove',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#e11d48',
    focusCancel: true,
  })
  if (!result.isConfirmed) return
  busy.value = true; error.value = ''
  try {
    await axios.post(`/api/${type}/delete`, { id })
    notice.value = 'Removed. Reports will recalculate using the remaining applicable schedules and events.'
    await load()
  } catch (e) { error.value = e.response?.data?.message || 'Unable to remove record.' }
  finally { busy.value = false }
}
const formatScheduleTime = value => {
  const match = String(value || '').match(/^(\d{1,2}):(\d{2})/)
  if (!match) return '-'
  const hour = Number(match[1])
  return `${hour % 12 || 12}:${match[2]} ${hour >= 12 ? 'PM' : 'AM'}`
}
const scheduleText = profile => (profile.schedules || []).map(s => `${formatScheduleTime(s.time_in)}–${formatScheduleTime(s.time_out)}${s.is_next_day ? ' (+1 day)' : ''}`).join(', ')
onMounted(load)
</script>

<template>
  <div class="space-y-3 text-slate-800 dark:text-slate-200">
    <section class="admin-page-header dark -ml-4 -mt-4 md:-ml-6 md:-mt-6 sticky px-2 py-1.5 top-0 z-20 -mr-4 border pb-2 shadow-sm md:-mr-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="relative flex items-center gap-2">
          <h1 class="text-base font-semibold text-white">Weekly Schedule Exceptions</h1>
          <div class="group">
            <button type="button" aria-label="About weekly schedule exceptions" aria-describedby="weekly-schedule-info" class="flex h-6 w-6 items-center justify-center text-teal-200 transition hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-300">
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v6m0-10v.5" /></svg>
            </button>
            <div id="weekly-schedule-info" role="tooltip" class="invisible absolute left-0 top-full z-50 w-[min(340px,calc(100vw_-_2rem))] pt-2 opacity-0 transition-opacity group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
              <div class="space-y-2 border border-teal-200 bg-white p-3 text-[11px] leading-relaxed text-slate-600 shadow-lg">
                <p class="font-semibold text-slate-800">Weekly schedule exceptions</p>
                <p>Set schedules from an effective date and review exceptions for a particular week.</p>
                <p><strong class="text-teal-700">Compressed:</strong> 7:00-12:00, 13:00-18:00 (10 hours).</p>
                <p><strong class="text-teal-700">Standard:</strong> 8:00-12:00, 13:00-17:00 (8 hours).</p>
                <p>Both presets default to Monday-Thursday; Friday is a rest day.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="ml-auto flex min-w-0 flex-wrap items-center justify-end gap-2">
          <label class="relative min-w-0">
            <span class="sr-only">Month filter (optional)</span>
            <svg class="pointer-events-none absolute left-2 top-1/2 z-10 h-3.5 w-3.5 -translate-y-1/2 text-teal-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" /><path d="M16 3v4M8 3v4M3 11h18" /></svg>
            <flat-pickr v-model="month" :config="monthConfig" :disabled="busy" class="input header-month !mt-0" placeholder="All dates" />
            <svg class="pointer-events-none absolute right-2 top-1/2 h-3 w-3 -translate-y-1/2 text-teal-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
          </label>
          <button v-if="month" type="button" class="button" :disabled="busy" @click="month = ''">Clear filter</button>
          <button type="button" @click="openSchedule" :disabled="busy" class="button">Add effective schedule or weekly exception</button>
          <button type="button" @click="load" :disabled="busy" class="button">Refresh</button>
        </div>
      </div>
    </section>
    <p v-if="error && !scheduleOpen" role="alert" class="border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>

    
    <dialog ref="scheduleDialog" aria-labelledby="schedule-modal-title" class="schedule-dialog panel text-slate-800 dark:text-slate-200" @cancel.prevent="closeSchedule" @click="event => { if (event.target === scheduleDialog) closeSchedule() }">
    <form id="schedule-form" @submit.prevent="saveRule">
      <div class="flex items-center justify-between gap-3">
        <h2 id="schedule-modal-title" class="text-[11px] font-semibold">Add effective schedule or weekly exception</h2>
        <button type="button" @click="closeSchedule" :disabled="busy" aria-label="Close schedule form" class="button">Close</button>
      </div>
      <p v-if="error && scheduleOpen" role="alert" class="mt-3 border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
      <fieldset :disabled="busy" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <label class="text-[11px]">Schedule type<select v-model="form.kind" class="input"><option value="permanent">Effective schedule (until superseded)</option><option value="weekly">Weekly exception (Monday–Sunday)</option></select></label>
        <label class="text-[11px]">Office shift<select v-model="form.office_shift_id" required class="input"><option value="" disabled>Select shift</option><option v-for="shift in data.office_shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option></select></label>
        <label class="text-[11px]">Employees<select v-model="form.user_id" class="input"><option value="">All employees in this shift</option><option v-for="user in employees" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
        <label class="text-[11px]">Schedule profile<select v-model="form.profile_key" class="input"><option value="compressed">Compressed — 10 hours</option><option value="standard">Standard — 8 hours</option><option value="existing">Copy an existing office shift</option></select></label>
        <label v-if="form.profile_key === 'existing'" class="text-[11px]">Copy hours from<select v-model="form.source_shift_id" required class="input"><option value="" disabled>Select source shift</option><option v-for="shift in data.office_shifts" :key="shift.id" :value="shift.id">{{ shift.name }} · {{ shift.schedule }}</option></select></label>
        <label class="text-[11px]">{{ form.kind === 'weekly' ? 'Week starting Monday' : 'Effective from' }}<flat-pickr v-model="form.effective_from" :config="scheduleDateConfig" required class="input" placeholder="Select start date" /></label>
        <div class="sm:col-span-2 lg:col-span-3"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Working days</p><div class="mt-2 flex flex-wrap gap-2"><label v-for="(label, index) in weekdays" :key="label" class="flex items-center gap-1 border border-slate-200 bg-white px-2 py-1 text-[11px] dark:border-slate-700 dark:bg-slate-950"><input v-model="form.working_days" type="checkbox" :value="index + 1" />{{ label }}</label></div></div>
        <label class="text-[11px] sm:col-span-2 lg:col-span-3">Reason / reference<textarea v-model="form.reason" required maxlength="1000" rows="2" class="input !h-auto" placeholder="Policy reference or reason for the schedule change" /></label>
      </fieldset>
      <p class="mt-3 text-[11px] text-slate-500">Earlier dates retain the historical schedule. An employee-specific exception takes priority over a shift-wide exception. Saved profiles retain a copy of their hours.</p>
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" @click="closeSchedule" :disabled="busy" class="button">Cancel</button>
        <button type="submit" :disabled="busy" class="button">{{ busy ? 'Saving…' : 'Save schedule' }}</button>
      </div>
    </form>
    </dialog>
    <section class="panel !p-0 overflow-x-auto"><table class="saved-schedules w-full border-collapse text-left text-[11px]"><thead class="bg-[linear-gradient(135deg,_#0f172a_0%,_#1e293b_50%,_#0f766e_100%)] text-slate-100"><tr><th class="px-2 py-1 font-semibold uppercase tracking-wide">Applies to</th><th class="px-2 py-1 font-semibold uppercase tracking-wide">Dates</th><th class="px-2 py-1 font-semibold uppercase tracking-wide">Schedule</th><th class="px-2 py-1 font-semibold uppercase tracking-wide">Reason</th><th class="px-2 py-1 font-semibold uppercase tracking-wide">Action</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-700"><tr v-for="rule in visibleRules" :key="rule.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40"><td class="px-2 py-1 font-medium text-slate-800 dark:text-slate-100">{{ rule.user?.name || rule.office_shift?.name }}<br /><span class="text-slate-500">{{ rule.kind }}</span></td><td class="whitespace-nowrap p-2">{{ rule.effective_from }}<br />{{ rule.effective_to || 'Until superseded' }}</td><td class="px-2 py-1">{{ rule.profile.name }}<br />{{ scheduleText(rule.profile) }}<br />{{ (rule.profile.working_days || []).map(d => weekdays[d - 1]).join(', ') }}</td><td class="px-2 py-1">{{ rule.reason }}<br /><span class="text-slate-500">{{ rule.creator?.name }}</span></td><td class="px-2 py-1"><button @click="remove('work-schedule', rule.id)" :disabled="busy" class="rounded-none border border-rose-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-50 disabled:opacity-50 dark:border-rose-800 dark:bg-slate-900 dark:text-rose-300">Remove</button></td></tr><tr v-if="!visibleRules.length"><td colspan="5" class="p-3 text-slate-500">No effective schedules or exceptions saved yet.</td></tr></tbody></table>
      <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-3 py-2 dark:border-slate-700">
        <p class="text-[11px] text-slate-500">Showing {{ visibleRules.length }} of {{ filteredRules.length }} schedules</p>
        <button v-if="visibleRules.length < filteredRules.length" type="button" class="button" :disabled="busy" @click="ruleLimit += 10">Load more</button>
      </div>
    </section>
    <form @submit.prevent="saveSuspension" class="panel"><h2 class="text-[11px] font-semibold">Work suspensions</h2><p class="mt-1 text-[11px] text-slate-500">Record suspensions here. Maintain holidays on the Holidays page. A suspension during a working period credits that period's scheduled hours.</p><fieldset :disabled="busy" class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-4"><label class="text-[11px]">Name / reference<input v-model="suspension.name" required maxlength="120" class="input" /></label><label class="text-[11px]">Date<flat-pickr v-model="suspension.suspension_date" :config="dateConfig" required class="input" /></label><label class="text-[11px]">Duration<select v-model="suspension.duration" class="input"><option value="full_day">Full day</option><option value="morning">Morning</option><option value="afternoon">Afternoon</option></select></label><label class="text-[11px]">Applies to<select v-model="suspension.office_shift_id" class="input"><option value="">All shifts</option><option v-for="shift in data.office_shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option></select></label></fieldset><button type="submit" :disabled="busy" class="button mt-3">Save suspension</button><div v-for="item in data.suspensions" :key="item.id" class="mt-3 flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-2 text-[11px] dark:border-slate-700"><span>{{ item.suspension_date }} · {{ item.name }} · {{ item.duration.replaceAll('_', ' ') }} · {{ item.office_shift?.name || 'All shifts' }}</span><button type="button" @click="remove('work-suspension', item.id)" :disabled="busy" class="text-rose-600 underline">Remove</button></div></form>
  </div>
</template>

<style scoped>
.panel { @apply border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900; }
.schedule-dialog { width: min(56rem, calc(100% - 2rem)); max-height: calc(100dvh - 2rem); margin: auto; overflow-y: auto; }
.schedule-dialog::backdrop { background: rgb(15 23 42 / 50%); }
.schedule-dialog :deep(.flatpickr-wrapper) { display: block; }
.button { @apply inline-flex min-h-7 items-center justify-center rounded-none border border-cyan-300/30 bg-teal-700 px-2 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-teal-600 disabled:cursor-not-allowed disabled:opacity-50; }
:deep(.input) { @apply mt-1 block h-7 min-w-0 w-full rounded-none border border-slate-300 bg-white py-0 pl-2 pr-6 text-[11px] shadow-sm outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/15 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100; }

:deep(.header-month) { width: 150px; padding-left: 30px; padding-right: 26px; border-color: #99f6e4; background: #f0fdfa; color: #115e59; font-weight: 500; cursor: pointer; }
:deep(.header-month::placeholder) { color: #0f766e; opacity: 1; }
:deep(.header-month:hover) { border-color: #2dd4bf; background: #ccfbf1; }
.panel fieldset > label { min-width: 0; }
.button:focus-visible { outline: 2px solid #5eead4; outline-offset: 2px; }
.saved-schedules tbody td { line-height: 1.35; vertical-align: middle; }
.saved-schedules tbody td br { line-height: inherit; }
</style>
