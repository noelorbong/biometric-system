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
const visibleRules = computed(() => data.value.rules.filter(rule => !month.value || rule.effective_from.slice(0, 7) <= month.value && (!rule.effective_to || rule.effective_to.slice(0, 7) >= month.value)))
watch(() => form.value.office_shift_id, () => { form.value.user_id = '' })
watch(() => form.value.profile_key, value => { form.value.working_days = value === 'existing' ? [1, 2, 3, 4, 5] : [1, 2, 3, 4] })
watch(month, () => { if (loaded.value && !busy.value) load() })

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
    if (!month.value) month.value = response.data.month
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
const scheduleText = profile => (profile.schedules || []).map(s => `${s.time_in.slice(0, 5)}–${s.time_out.slice(0, 5)}${s.is_next_day ? ' (+1 day)' : ''}`).join(', ')
onMounted(load)
</script>

<template>
  <div class="space-y-4 text-slate-800 dark:text-slate-200">
    <section class="panel">
      <div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-lg font-semibold">Weekly Schedule Exceptions</h1><p class="mt-1 text-xs text-slate-500">Set schedules from an effective date and review exceptions for a particular week.</p></div><button @click="load" :disabled="busy" class="button">Refresh</button></div>
      <label class="mt-3 block text-xs">Review month<flat-pickr v-model="month" :config="monthConfig" :disabled="busy" class="input mt-1" /></label>
      <p class="mt-3 text-xs text-slate-500">Compressed: 7:00–12:00, 13:00–18:00 (10 hours). Standard: 8:00–12:00, 13:00–17:00 (8 hours). Both presets default to Monday–Thursday; Friday is a rest day.</p>
    </section>
    <p v-if="error && !scheduleOpen" role="alert" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
    <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ notice }}</p>
    <section class="panel">
      <h2 class="text-sm font-semibold">Suggested eight-hour weeks</h2><p class="mt-1 text-xs text-slate-500">A Friday holiday or work suspension suggests the standard schedule. Review and save the exception to apply it. Existing saved exceptions take precedence.</p>
      <p v-if="!data.suggestions.length" class="mt-3 text-xs text-slate-500">No pending suggestions for this month.</p>
      <div v-for="item in data.suggestions" :key="`${item.office_shift_id}-${item.user_id}-${item.week_start}`" class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-900/20 dark:text-amber-200"><span><strong>{{ item.scope }}</strong> · {{ item.week_start }} to {{ item.week_end }}<br />{{ item.reason }}</span><button @click="reviewSuggestion(item)" :disabled="busy" class="button">Review exception</button></div>
    </section>
    <button type="button" @click="openSchedule" :disabled="busy" class="button">Add effective schedule or weekly exception</button>
    <dialog ref="scheduleDialog" aria-labelledby="schedule-modal-title" class="schedule-dialog panel text-slate-800 dark:text-slate-200" @cancel.prevent="closeSchedule" @click="event => { if (event.target === scheduleDialog) closeSchedule() }">
    <form id="schedule-form" @submit.prevent="saveRule">
      <div class="flex items-center justify-between gap-3">
        <h2 id="schedule-modal-title" class="text-sm font-semibold">Add effective schedule or weekly exception</h2>
        <button type="button" @click="closeSchedule" :disabled="busy" aria-label="Close schedule form" class="button">Close</button>
      </div>
      <p v-if="error && scheduleOpen" role="alert" class="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
      <fieldset :disabled="busy" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <label class="text-xs">Schedule type<select v-model="form.kind" class="input"><option value="permanent">Effective schedule (until superseded)</option><option value="weekly">Weekly exception (Monday–Sunday)</option></select></label>
        <label class="text-xs">Office shift<select v-model="form.office_shift_id" required class="input"><option value="" disabled>Select shift</option><option v-for="shift in data.office_shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option></select></label>
        <label class="text-xs">Employees<select v-model="form.user_id" class="input"><option value="">All employees in this shift</option><option v-for="user in employees" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
        <label class="text-xs">Schedule profile<select v-model="form.profile_key" class="input"><option value="compressed">Compressed — 10 hours</option><option value="standard">Standard — 8 hours</option><option value="existing">Copy an existing office shift</option></select></label>
        <label v-if="form.profile_key === 'existing'" class="text-xs">Copy hours from<select v-model="form.source_shift_id" required class="input"><option value="" disabled>Select source shift</option><option v-for="shift in data.office_shifts" :key="shift.id" :value="shift.id">{{ shift.name }} · {{ shift.schedule }}</option></select></label>
        <label class="text-xs">{{ form.kind === 'weekly' ? 'Week starting Monday' : 'Effective from' }}<flat-pickr v-model="form.effective_from" :config="scheduleDateConfig" required class="input" placeholder="Select start date" /></label>
        <div class="sm:col-span-2 lg:col-span-3"><p class="text-xs">Working days</p><div class="mt-2 flex flex-wrap gap-3"><label v-for="(label, index) in weekdays" :key="label" class="flex items-center gap-1 text-xs"><input v-model="form.working_days" type="checkbox" :value="index + 1" />{{ label }}</label></div></div>
        <label class="text-xs sm:col-span-2 lg:col-span-3">Reason / reference<textarea v-model="form.reason" required maxlength="1000" rows="2" class="input !h-auto" placeholder="Policy reference or reason for the schedule change" /></label>
      </fieldset>
      <p class="mt-3 text-xs text-slate-500">Earlier dates retain the historical schedule. An employee-specific exception takes priority over a shift-wide exception. Saved profiles retain a copy of their hours.</p>
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" @click="closeSchedule" :disabled="busy" class="button">Cancel</button>
        <button type="submit" :disabled="busy" class="button">{{ busy ? 'Saving…' : 'Save schedule' }}</button>
      </div>
    </form>
    </dialog>
    <section class="panel overflow-x-auto"><h2 class="mb-3 text-sm font-semibold">Schedules applicable to the review month</h2><table class="w-full text-left text-xs"><thead><tr><th class="p-2">Applies to</th><th class="p-2">Dates</th><th class="p-2">Schedule</th><th class="p-2">Reason</th><th class="p-2">Action</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-700"><tr v-for="rule in visibleRules" :key="rule.id"><td class="p-2">{{ rule.user?.name || rule.office_shift?.name }}<br /><span class="text-slate-500">{{ rule.kind }}</span></td><td class="whitespace-nowrap p-2">{{ rule.effective_from }}<br />{{ rule.effective_to || 'Until superseded' }}</td><td class="p-2">{{ rule.profile.name }}<br />{{ scheduleText(rule.profile) }}<br />{{ (rule.profile.working_days || []).map(d => weekdays[d - 1]).join(', ') }}</td><td class="p-2">{{ rule.reason }}<br /><span class="text-slate-500">{{ rule.creator?.name }}</span></td><td class="p-2"><button @click="remove('work-schedule', rule.id)" :disabled="busy" class="text-rose-600 underline">Remove</button></td></tr><tr v-if="!visibleRules.length"><td colspan="5" class="p-3 text-slate-500">No effective schedules or exceptions saved yet.</td></tr></tbody></table></section>
    <form @submit.prevent="saveSuspension" class="panel"><h2 class="text-sm font-semibold">Work suspensions</h2><p class="mt-1 text-xs text-slate-500">Record suspensions here. Maintain holidays on the Holidays page. A suspension during a working period credits that period's scheduled hours.</p><fieldset :disabled="busy" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><label class="text-xs">Name / reference<input v-model="suspension.name" required maxlength="120" class="input" /></label><label class="text-xs">Date<flat-pickr v-model="suspension.suspension_date" :config="dateConfig" required class="input" /></label><label class="text-xs">Duration<select v-model="suspension.duration" class="input"><option value="full_day">Full day</option><option value="morning">Morning</option><option value="afternoon">Afternoon</option></select></label><label class="text-xs">Applies to<select v-model="suspension.office_shift_id" class="input"><option value="">All shifts</option><option v-for="shift in data.office_shifts" :key="shift.id" :value="shift.id">{{ shift.name }}</option></select></label></fieldset><button type="submit" :disabled="busy" class="button mt-3">Save suspension</button><div v-for="item in data.suspensions" :key="item.id" class="mt-3 flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-2 text-xs dark:border-slate-700"><span>{{ item.suspension_date }} · {{ item.name }} · {{ item.duration.replaceAll('_', ' ') }} · {{ item.office_shift?.name || 'All shifts' }}</span><button type="button" @click="remove('work-suspension', item.id)" :disabled="busy" class="text-rose-600 underline">Remove</button></div></form>
  </div>
</template>

<style scoped>
.panel { @apply rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900; }
.schedule-dialog { width: min(56rem, calc(100% - 2rem)); max-height: calc(100dvh - 2rem); margin: auto; overflow-y: auto; }
.schedule-dialog::backdrop { background: rgb(15 23 42 / 50%); }
.schedule-dialog :deep(.flatpickr-wrapper) { display: block; }
.button { @apply rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-medium text-sky-700 disabled:opacity-50 dark:border-sky-900 dark:bg-sky-900/20 dark:text-sky-300; }
:deep(.input) { @apply mt-1 block h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-950; }
</style>
