<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import Modal from './Modal.vue'

const props = defineProps({ context: { type: Object, required: true } })
const emit = defineEmits(['close', 'saved'])
const logs = ref([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const form = ref(null)
const removing = ref(null)
const dateInput = ref(null)
const minuteInput = ref('')
const absenceSaving = ref(false)
const absenceDuration = ref(props.context.absence?.duration || 'whole_day')
const absenceLeaveType = ref(props.context.absence?.leave_type || 'sick')
const controller = new AbortController()
let disposed = false
const shiftDate = (date, days) => {
  const [year, month, day] = date.split('-').map(Number)
  return new Date(Date.UTC(year, month - 1, day + days)).toISOString().slice(0, 10)
}
const endDate = computed(() => props.context.overnight ? shiftDate(props.context.date, 1) : props.context.date)
const selectedType = computed(() => props.context.direction === 'in' ? 'I' : 'O')
const selectedLabel = computed(() => selectedType.value === 'I' ? 'Late' : 'Undertime')
const scheduledTime = computed(() => {
  const period = props.context.period
  if (!period) return ''
  const day = props.context.direction === 'out' && period.next_day ? shiftDate(props.context.date, 1) : props.context.date
  return `${day}T${props.context.direction === 'in' ? period.scheduled_in : period.scheduled_out}`
})
const formTitle = computed(() => form.value?.mode === 'add' ? 'Add attendance log' : form.value?.mode === 'edit' ? 'Edit saved correction' : 'Override attendance log')
const close = () => { if (!saving.value) emit('close') }

async function loadLogs() {
  loading.value = true
  error.value = ''
  try {
    const months = [...new Set([props.context.date.slice(0, 7), endDate.value.slice(0, 7)])]
    const responses = await Promise.all(months.map(month => {
      const [year, monthNumber] = month.split('-').map(Number)
      return axios.post('/api/user/checkinout', { user_id: props.context.userId, year, month: monthNumber }, { signal: controller.signal })
    }))
    if (disposed) return
    const unique = new Map()
    for (const response of responses) {
      for (const log of response.data.checkinouts || []) {
        const date = String(log.CHECKTIME || '').slice(0, 10)
        if (date >= props.context.date && date <= endDate.value) unique.set(String(log.id), log)
      }
    }
    logs.value = [...unique.values()].sort((a, b) => String(a.CHECKTIME).localeCompare(String(b.CHECKTIME)))
    if (!logs.value.length) editLog()
  } catch (e) {
    if (!axios.isCancel(e)) error.value = e.response?.data?.message || 'Unable to load attendance logs.'
  } finally {
    if (!disposed) loading.value = false
  }
}
async function editLog(log = null) {
  removing.value = null
  minuteInput.value = ''
  form.value = {
    mode: log ? (log._override_id ? 'edit' : 'override') : 'add',
    id: log?._override_id || log?.id || null,
    dateTime: log ? String(log.CHECKTIME).replace(' ', 'T') : scheduledTime.value || `${props.context.date}T08:00`,
    type: log ? String(log.CHECKTYPE).toUpperCase() : selectedType.value,
  }
  await nextTick()
  dateInput.value?.focus()
}
function applyMinutes() {
  const minutes = Number(minuteInput.value)
  if (!scheduledTime.value || minuteInput.value === '' || !Number.isInteger(minutes) || minutes < 0 || minutes > 1440) return
  // Work with wall-clock components so browser timezone/DST cannot move a punch.
  const [date, time] = scheduledTime.value.split('T')
  const [year, month, day] = date.split('-').map(Number)
  const [hour, minute] = time.split(':').map(Number)
  const shifted = new Date(Date.UTC(year, month - 1, day, hour, minute + (selectedType.value === 'I' ? minutes : -minutes)))
  form.value.dateTime = shifted.toISOString().slice(0, 19)
  form.value.type = selectedType.value
}
async function save() {
  if (saving.value || !form.value) return
  saving.value = true
  error.value = ''
  const [year, month] = props.context.date.split('-').map(Number)
  const payload = { new_checktime: form.value.dateTime, new_checktype: form.value.type, year, month }
  const editing = form.value.mode === 'edit'
  if (editing) payload.id = form.value.id
  else Object.assign(payload, { user_id: props.context.userId, action_type: form.value.mode,
    ...(form.value.mode === 'override' ? { checkinout_id: form.value.id } : {}) })
  try {
    await axios.post(`/api/user/checkinout/override/${editing ? 'update' : 'store'}`, payload)
    emit('saved')
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to save attendance log.'
  } finally { saving.value = false }
}
async function removeCorrection() {
  if (saving.value || !removing.value?._override_id) return
  saving.value = true
  error.value = ''
  const [year, month] = props.context.date.split('-').map(Number)
  try {
    await axios.post('/api/user/checkinout/override/delete', { id: removing.value._override_id, year, month })
    emit('saved')
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to remove correction.'
  } finally { saving.value = false }
}
async function setAbsence(status = null) {
  if (saving.value || absenceSaving.value) return
  absenceSaving.value = true
  error.value = ''
  try {
    await axios.post(`/api/attendance/absence/${status ? 'store' : 'delete'}`, {
      user_id: props.context.userId,
      absence_date: props.context.date,
      ...(status ? { status, duration: absenceDuration.value, leave_type: absenceLeaveType.value } : {}),
    })
    emit('saved')
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to update absence status.'
  } finally { absenceSaving.value = false }
}
onMounted(loadLogs)
onBeforeUnmount(() => { disposed = true; controller.abort() })
</script>

<template>
  <Teleport to="body">
    <Modal @close="close">
      <template #body>
        <div role="dialog" aria-modal="true" aria-labelledby="attendance-log-title" @keydown.esc.stop.prevent="close" class="relative m-3 max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-5 text-slate-800 shadow-xl dark:bg-slate-900 dark:text-slate-200">
          <div class="flex items-start justify-between gap-3">
            <div><h2 id="attendance-log-title" class="text-lg font-semibold">Attendance logs — {{ context.name }}</h2><p class="mt-1 text-sm">{{ context.date }} · {{ context.label }} {{ selectedType === 'I' ? 'IN' : 'OUT' }} · {{ context.value }} {{ selectedLabel.toLowerCase() }} minutes</p></div>
            <button type="button" @click="close" :disabled="saving" class="editor-button">Close</button>
          </div>
          <p v-if="context.period" class="mt-2 text-xs text-slate-500">Scheduled {{ context.period.scheduled_in }} – {{ context.period.scheduled_out }}{{ context.period.next_day ? ' (next day)' : '' }}. {{ context.shiftName }}</p>
          <p v-else class="mt-2 text-xs text-amber-700 dark:text-amber-300">This employee has no assigned schedule for this column. Added logs are saved, but this cell may remain blank.</p>
          <p class="mt-2 rounded-lg bg-sky-50 p-3 text-xs text-sky-800 dark:bg-sky-900/20 dark:text-sky-200">Changes update attendance logs and recalculate reports. Original device logs are preserved. To change an existing punch, choose Override or Edit below; adding another punch may not replace it.</p>
          <p v-if="context.period?.exempt" class="mt-2 text-xs text-amber-700 dark:text-amber-300">This period is holiday-exempt. Its late/undertime minutes remain zero even when logs are changed.</p>
          <p v-if="context.weekend" class="mt-2 text-xs text-slate-500">This rest day is excluded from monthly totals. You can still correct its attendance logs.</p>
          <section class="mt-3 rounded-lg border border-slate-200 p-3 text-xs dark:border-slate-700">
            <div class="flex flex-wrap items-center justify-between gap-2"><div><h3 class="font-semibold">Absence status</h3><p class="mt-1 text-slate-500 dark:text-slate-400">{{ context.absence?.status === 'filed' ? `Filed ${context.absence.duration.replace('_', ' ')} ${context.absence.leave_type?.replace('_', ' ')} absence: affected periods are exempt from tardiness.` : context.absence?.status === 'unfiled' ? `Unfiled ${context.absence.duration.replace('_', ' ')} absence: unattended affected periods are counted as tardy and recorded without pay.` : 'No absence status is set for this day.' }}</p></div><div class="flex flex-wrap items-end gap-2"><label class="text-xs font-medium">Duration<select v-model="absenceDuration" :disabled="saving || absenceSaving || context.weekend || context.future" class="editor-input !mt-1 !h-8 !w-auto"><option value="whole_day">Whole day</option><option value="morning">Morning</option><option value="afternoon">Afternoon</option></select></label><label class="text-xs font-medium">Filed type<select v-model="absenceLeaveType" :disabled="saving || absenceSaving || context.weekend || context.future" class="editor-input !mt-1 !h-8 !w-auto"><option value="cto">CTO</option><option value="vacation">Vacation</option><option value="sick">Sick</option><option value="spl">SPL</option><option value="without_pay">Without pay</option></select></label><button type="button" @click="setAbsence('filed')" :disabled="saving || absenceSaving || context.weekend || context.future" class="editor-button">Mark filed</button><button type="button" @click="setAbsence('unfiled')" :disabled="saving || absenceSaving || context.weekend || context.future" class="editor-button">Mark unfiled</button><button v-if="context.absence" type="button" @click="setAbsence()" :disabled="saving || absenceSaving" class="text-rose-600 underline disabled:opacity-50">{{ absenceSaving ? 'Saving…' : 'Clear' }}</button></div></div>
          </section>
          <div v-if="error" role="alert" class="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ error }} <button v-if="!form && !saving" type="button" @click="loadLogs" class="underline">Retry</button></div>
          <div class="mt-4 flex items-center justify-between"><h3 class="text-sm font-semibold">{{ context.overnight ? 'Logs for this date and the next day' : 'Logs for this date' }}</h3><button type="button" @click="editLog()" :disabled="loading || saving" class="editor-button">Add log</button></div>
          <div class="mt-2 max-h-64 overflow-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="w-full text-left text-xs"><thead class="bg-slate-50 dark:bg-slate-800"><tr><th class="p-2">Date / time</th><th class="p-2">Type</th><th class="p-2">Source</th><th class="p-2 text-right">Action</th></tr></thead>
              <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <tr v-for="log in logs" :key="log.id" :class="String(log.CHECKTYPE).toUpperCase() === selectedType ? 'bg-sky-50/50 dark:bg-sky-900/10' : ''">
                  <td class="whitespace-nowrap p-2">{{ log.CHECKTIME }}</td><td class="p-2">{{ String(log.CHECKTYPE).toUpperCase() === 'I' ? 'IN' : String(log.CHECKTYPE).toUpperCase() === 'O' ? 'OUT' : log.CHECKTYPE }}</td><td class="p-2">{{ log._override_action === 'add' ? 'Added' : log._override_id ? 'Overridden' : 'Device' }}</td>
                  <td class="whitespace-nowrap p-2 text-right"><button type="button" @click="editLog(log)" :disabled="saving" class="editor-button">{{ log._override_id ? 'Edit' : 'Override' }}</button><button v-if="log._override_id" type="button" @click="removing = log; form = null" :disabled="saving" class="ml-2 text-rose-600 underline">Remove correction</button></td>
                </tr>
                <tr v-if="loading || !logs.length"><td colspan="4" class="p-5 text-center text-slate-500">{{ loading ? 'Loading logs…' : 'No logs found. Add a log below.' }}</td></tr>
              </tbody>
            </table>
          </div>
          <form v-if="form" @submit.prevent="save" class="mt-4 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <h3 class="text-sm font-semibold">{{ formTitle }}</h3>
            <fieldset :disabled="saving" class="mt-3 grid gap-3 sm:grid-cols-2">
              <label class="text-xs font-medium">Date and time<input ref="dateInput" v-model="form.dateTime" @input="minuteInput = ''" type="datetime-local" step="1" required class="editor-input" /></label>
              <label class="text-xs font-medium">Punch type<select v-model="form.type" class="editor-input"><option value="I">IN</option><option value="O">OUT</option></select></label>
              <label v-if="scheduledTime" class="text-xs font-medium">Set {{ selectedLabel.toLowerCase() }} minutes (optional)<input v-model="minuteInput" @input="applyMinutes" type="number" min="0" max="1440" step="1" placeholder="Minutes from scheduled time" class="editor-input" /><span class="mt-1 block font-normal text-slate-500">Fills the punch time from this period's schedule. The report recalculates using all logs.</span></label>
            </fieldset>
            <div class="mt-3 flex justify-end gap-2"><button type="button" @click="form = null" :disabled="saving" class="editor-button">Cancel edit</button><button type="submit" :disabled="saving" class="rounded-md bg-sky-600 px-3 py-2 text-xs font-medium text-white disabled:opacity-50">{{ saving ? 'Saving…' : form.mode === 'add' ? 'Save added log' : 'Save correction' }}</button></div>
          </form>
          <div v-if="removing" class="mt-4 rounded-lg border border-rose-200 p-3 text-sm"><p>Remove this correction? {{ removing._override_action === 'add' ? 'The added log will be removed.' : 'The original device log will be used again.' }}</p><div class="mt-3 flex justify-end gap-2"><button type="button" @click="removing = null" :disabled="saving" class="editor-button">Cancel</button><button type="button" @click="removeCorrection" :disabled="saving" class="rounded-md bg-rose-600 px-3 py-2 text-xs text-white disabled:opacity-50">{{ saving ? 'Removing…' : 'Confirm removal' }}</button></div></div>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<style scoped>
.editor-button { @apply rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:hover:bg-slate-800; }
.editor-input { @apply mt-1 block h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm dark:border-slate-600 dark:bg-slate-950 dark:[color-scheme:dark]; }
</style>
