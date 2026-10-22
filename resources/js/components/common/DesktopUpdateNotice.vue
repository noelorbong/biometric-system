<script>
let checkedThisLaunch = false
</script>
<script setup>
import { nextTick, onMounted, onBeforeUnmount, ref } from 'vue'
import axios from 'axios'
import { isDesktop } from '@/utils/printPreview'

const dialog = ref(null)
const state = ref({ status: 'idle' })
const error = ref('')
const busy = ref(false)
let timer
let disposed = false
let manual = false
let dismissed = false

async function show() {
  await nextTick()
  if (!disposed && !dialog.value.open) dialog.value.showModal()
}
function later() {
  dismissed = true
  dialog.value.close()
}
async function accept(data) {
  if (disposed) return
  state.value = data
  if (!dismissed && (manual || ['available', 'downloaded'].includes(data.status))) await show()
  clearTimeout(timer)
  if (['checking', 'downloading', 'installing'].includes(data.status)) timer = setTimeout(poll, 1000)
}
async function poll() {
  try {
    await accept((await axios.get('/api/desktop/updater/status')).data)
  } catch {
    if (disposed) return
    error.value = 'Unable to read update progress. Use Refresh status to reconnect.'
    if (manual && !dismissed) await show()
  }
}
async function action(name) {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    await accept((await axios.post(`/api/desktop/updater/${name}`)).data)
  } catch (exception) {
    error.value = exception.response?.data?.message || 'The update request failed. Please retry.'
    if (manual) await show()
  } finally { busy.value = false }
}
async function checkNow() {
  manual = true
  dismissed = false
  await show()
  await action('check')
}
async function download() {
  manual = true
  await action('download')
}
onMounted(async () => {
  if (!isDesktop()) return
  window.addEventListener('desktop-check-updates', checkNow)
  if (!checkedThisLaunch) {
    checkedThisLaunch = true
    await action('check')
  } else {
    await poll()
  }
})
onBeforeUnmount(() => {
  disposed = true
  clearTimeout(timer)
  window.removeEventListener('desktop-check-updates', checkNow)
})
</script>
<template>
  <dialog ref="dialog" @cancel="dismissed = true" aria-labelledby="update-title" class="m-auto w-[480px] max-w-[calc(100vw-2rem)] rounded-xl border border-sky-200 bg-white p-6 text-slate-900 shadow-xl backdrop:bg-black/50 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
    <h2 id="update-title" class="text-lg font-semibold">Software update</h2>
    <p v-if="state.current_version" class="mt-2 text-sm">Installed: v{{ state.current_version }}<span v-if="state.latest_version"> · Latest: v{{ state.latest_version }}</span></p>
    <div class="mt-4 text-sm" aria-live="polite">
      <p v-if="state.status === 'checking' || busy">Please wait…</p>
      <p v-else-if="state.status === 'current'">You are up to date.</p>
      <p v-else-if="state.status === 'available'">A new version is available. Save your work first: downloading will automatically close the app and start installation when complete.</p>
      <div v-else-if="state.status === 'downloading'">
        <p>Downloading… {{ Math.round(state.percent || 0) }}%</p>
        <progress :value="state.percent || 0" max="100" class="mt-2 w-full" aria-label="Download progress" />
        <p class="mt-2">The app will close and start installation automatically when the download finishes.</p>
      </div>
      <p v-else-if="state.status === 'downloaded'">Download complete. Preparing installation…</p>
      <p v-else-if="state.status === 'installing'">Closing the application and starting the installer…</p>
      <p v-else-if="state.status === 'error'" role="alert">The update could not finish. Check your connection and try again.</p>
    </div>
    <details v-if="state.error" class="mt-3 text-xs"><summary>Error details</summary><p class="mt-2 break-words">{{ state.error }}</p></details>
    <p v-if="error" role="alert" class="mt-3 text-sm text-red-600">{{ error }}</p>
    <div class="mt-5 flex flex-wrap justify-end gap-2">
      <button v-if="error" type="button" @click="poll" class="rounded border px-3 py-2 text-sm">Refresh status</button>
      <button type="button" autofocus @click="later" class="rounded border px-3 py-2 text-sm">{{ ['downloading', 'installing'].includes(state.status) ? 'Hide progress' : 'Later' }}</button>
      <button v-if="['error', 'idle', 'current'].includes(state.status)" :disabled="busy" @click="checkNow" class="rounded bg-sky-700 px-3 py-2 text-sm text-white disabled:opacity-50">Check again</button>
      <button v-if="state.status === 'available'" :disabled="busy" @click="download" class="rounded bg-sky-700 px-3 py-2 text-sm text-white disabled:opacity-50">Download &amp; install</button>
    </div>
  </dialog>
</template>
