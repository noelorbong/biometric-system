<script setup>
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import Button from '@/components/ui/Button.vue'
import { useAppSettingStore } from '@/store/AppSettingStore'

const appSettingStore = useAppSettingStore()
const {
  companySchoolName,
  machineAutoSyncStatusTimerEnabled,
  machineAutoSyncStatusTimerMs,
  machineRefreshTimerEnabled,
  machineRefreshTimerMs,
  machineWebAutoFallbackTimerEnabled,
  machineWebAutoFallbackTimerMs,
} = storeToRefs(appSettingStore)

const form = ref({
  company_school_name: '',
  machine_auto_sync_status_timer_enabled: true,
  machine_auto_sync_status_timer_ms: 5000,
  machine_refresh_timer_enabled: true,
  machine_refresh_timer_ms: 5000,
  machine_web_auto_fallback_timer_enabled: true,
  machine_web_auto_fallback_timer_ms: 1000,
})

const saving = ref(false)
const patching = ref(false)
const patchResults = ref([])
const updating = ref(false)
const updateResults = ref([])

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 1600,
  timerProgressBar: true,
})

const toastResult = (message, icon = 'success') => {
  Toast.fire({ icon, title: message })
}

const loadSettings = async () => {
  const resp = await appSettingStore.loadSettings()
  if (!resp.success) {
    toastResult('Unable to load settings', 'error')
    return
  }

  form.value.company_school_name = companySchoolName.value || 'Biometric System'
  form.value.machine_auto_sync_status_timer_enabled = Boolean(machineAutoSyncStatusTimerEnabled.value)
  form.value.machine_auto_sync_status_timer_ms = Number(machineAutoSyncStatusTimerMs.value || 5000)
  form.value.machine_refresh_timer_enabled = Boolean(machineRefreshTimerEnabled.value)
  form.value.machine_refresh_timer_ms = Number(machineRefreshTimerMs.value || 5000)
  form.value.machine_web_auto_fallback_timer_enabled = Boolean(machineWebAutoFallbackTimerEnabled.value)
  form.value.machine_web_auto_fallback_timer_ms = Number(machineWebAutoFallbackTimerMs.value || 1000)
}

const saveSettings = async () => {
  const clampMs = (value, fallback) => {
    const parsed = Number(value)
    if (!Number.isFinite(parsed)) {
      return fallback
    }
    return Math.min(300000, Math.max(250, Math.floor(parsed)))
  }

  saving.value = true
  const resp = await appSettingStore.updateSettings({
    company_school_name: form.value.company_school_name,
    machine_auto_sync_status_timer_enabled: Boolean(form.value.machine_auto_sync_status_timer_enabled),
    machine_auto_sync_status_timer_ms: clampMs(form.value.machine_auto_sync_status_timer_ms, 5000),
    machine_refresh_timer_enabled: Boolean(form.value.machine_refresh_timer_enabled),
    machine_refresh_timer_ms: clampMs(form.value.machine_refresh_timer_ms, 5000),
    machine_web_auto_fallback_timer_enabled: Boolean(form.value.machine_web_auto_fallback_timer_enabled),
    machine_web_auto_fallback_timer_ms: clampMs(form.value.machine_web_auto_fallback_timer_ms, 1000),
  })

  if (!resp.success) {
    saving.value = false
    toastResult(resp?.data?.response?.data?.message || 'Unable to save settings', 'error')
    return
  }

  form.value.company_school_name = companySchoolName.value || form.value.company_school_name
  form.value.machine_auto_sync_status_timer_enabled = Boolean(machineAutoSyncStatusTimerEnabled.value)
  form.value.machine_auto_sync_status_timer_ms = Number(machineAutoSyncStatusTimerMs.value || 5000)
  form.value.machine_refresh_timer_enabled = Boolean(machineRefreshTimerEnabled.value)
  form.value.machine_refresh_timer_ms = Number(machineRefreshTimerMs.value || 5000)
  form.value.machine_web_auto_fallback_timer_enabled = Boolean(machineWebAutoFallbackTimerEnabled.value)
  form.value.machine_web_auto_fallback_timer_ms = Number(machineWebAutoFallbackTimerMs.value || 1000)
  saving.value = false
  toastResult('Settings updated')
}

const runMaintenancePatch = async () => {
  const confirmation = await Swal.fire({
    title: 'Run required maintenance patch?',
    text: 'This will run: storage:link, config:clear, cache:clear, route:clear, view:clear, migrate --force.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Run Patch',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#0284c7',
  })

  if (!confirmation.isConfirmed) {
    return
  }

  patching.value = true
  patchResults.value = []

  const resp = await appSettingStore.runMaintenancePatch()
  if (!resp.success) {
    patching.value = false
    patchResults.value = []
    toastResult(resp?.data?.response?.data?.message || 'Unable to run maintenance patch', 'error')
    return
  }

  patchResults.value = Array.isArray(resp?.data?.commands) ? resp.data.commands : []
  patching.value = false

  if (resp?.data?.success) {
    toastResult('Maintenance patch completed')
    return
  }

  toastResult('Maintenance patch completed with issues', 'warning')
}

const runSystemUpdate = async () => {
  const confirmation = await Swal.fire({
    title: 'Run system update?',
    text: 'This will run: git pull origin main.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Run Update',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#0284c7',
  })

  if (!confirmation.isConfirmed) {
    return
  }

  updating.value = true
  updateResults.value = []

  const resp = await appSettingStore.runSystemUpdate()
  if (!resp.success) {
    updating.value = false
    updateResults.value = []
    toastResult(resp?.data?.response?.data?.message || 'Unable to run system update', 'error')
    return
  }

  updateResults.value = Array.isArray(resp?.data?.commands) ? resp.data.commands : []
  updating.value = false

  if (resp?.data?.success) {
    toastResult('System update completed')
    return
  }

  toastResult('System update completed with issues', 'warning')
}

onMounted(async () => {
  await loadSettings()
})
</script>

<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] p-5 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
      <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">System Control Deck</p>
          <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white lg:text-4xl">Settings</h1>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200/90">
            Configure company branding and machine page timer behavior used across reporting and auto-sync workflows.
          </p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:min-w-[460px]">
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Timers</p>
            <p class="mt-2 text-3xl font-semibold text-white">3</p>
            <p class="mt-1 text-xs text-slate-300">Configurable tasks</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Enabled</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ Number(form.machine_auto_sync_status_timer_enabled) + Number(form.machine_refresh_timer_enabled) + Number(form.machine_web_auto_fallback_timer_enabled) }}</p>
            <p class="mt-1 text-xs text-slate-300">Active timers</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Disabled</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ 3 - (Number(form.machine_auto_sync_status_timer_enabled) + Number(form.machine_refresh_timer_enabled) + Number(form.machine_web_auto_fallback_timer_enabled)) }}</p>
            <p class="mt-1 text-xs text-slate-300">Paused timers</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Save State</p>
            <p class="mt-2 text-lg font-semibold text-white">{{ saving ? 'Saving...' : 'Ready' }}</p>
            <p class="mt-1 text-xs text-slate-300">Settings persistence</p>
          </div>
        </div>
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
      <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03] lg:p-5">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">General</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Display name appears in generated printable reports.</p>

        <div class="mt-4">
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">Company / School Name</label>
          <input
            v-model.trim="form.company_school_name"
            type="text"
            placeholder="Enter company or school name"
            class="h-11 w-full rounded-lg border border-slate-300 bg-transparent px-4 py-2.5 text-sm text-slate-800 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:text-white/90"
          />
        </div>

        <div class="mt-6">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Machine Page Timers</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Set interval in milliseconds. Disable a timer to turn that background task off.</p>

          <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                  <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Timer</th>
                  <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Enabled</th>
                  <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Interval (ms)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td class="px-4 py-2.5 text-slate-700 dark:text-slate-200">Daemon Status Poll</td>
                  <td class="px-4 py-2.5">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                      <input v-model="form.machine_auto_sync_status_timer_enabled" type="checkbox" class="h-4 w-4" />
                      <span :class="form.machine_auto_sync_status_timer_enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'">
                        {{ form.machine_auto_sync_status_timer_enabled ? 'On' : 'Off' }}
                      </span>
                    </label>
                  </td>
                  <td class="px-4 py-2.5">
                    <input
                      v-model.number="form.machine_auto_sync_status_timer_ms"
                      type="number"
                      min="250"
                      max="300000"
                      :disabled="!form.machine_auto_sync_status_timer_enabled"
                      class="h-10 w-full rounded-md border border-slate-300 bg-transparent px-3 text-sm text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-white/90"
                    />
                  </td>
                </tr>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td class="px-4 py-2.5 text-slate-700 dark:text-slate-200">Machine List Refresh</td>
                  <td class="px-4 py-2.5">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                      <input v-model="form.machine_refresh_timer_enabled" type="checkbox" class="h-4 w-4" />
                      <span :class="form.machine_refresh_timer_enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'">
                        {{ form.machine_refresh_timer_enabled ? 'On' : 'Off' }}
                      </span>
                    </label>
                  </td>
                  <td class="px-4 py-2.5">
                    <input
                      v-model.number="form.machine_refresh_timer_ms"
                      type="number"
                      min="250"
                      max="300000"
                      :disabled="!form.machine_refresh_timer_enabled"
                      class="h-10 w-full rounded-md border border-slate-300 bg-transparent px-3 text-sm text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-white/90"
                    />
                  </td>
                </tr>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td class="px-4 py-2.5 text-slate-700 dark:text-slate-200">Web Auto Fallback Cycle</td>
                  <td class="px-4 py-2.5">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                      <input v-model="form.machine_web_auto_fallback_timer_enabled" type="checkbox" class="h-4 w-4" />
                      <span :class="form.machine_web_auto_fallback_timer_enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'">
                        {{ form.machine_web_auto_fallback_timer_enabled ? 'On' : 'Off' }}
                      </span>
                    </label>
                  </td>
                  <td class="px-4 py-2.5">
                    <input
                      v-model.number="form.machine_web_auto_fallback_timer_ms"
                      type="number"
                      min="250"
                      max="300000"
                      :disabled="!form.machine_web_auto_fallback_timer_enabled"
                      class="h-10 w-full rounded-md border border-slate-300 bg-transparent px-3 text-sm text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-white/90"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mt-4 flex justify-end">
          <Button @click="saveSettings" size="sm" variant="primary" :className="'h-11 bg-sky-500 hover:bg-sky-600 text-white'" :disabled="saving">
            {{ saving ? 'Saving...' : 'Save Settings' }}
          </Button>
        </div>
      </div>

      <aside class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Quick Notes</h3>
        <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
          <li>Timer values are clamped between 250ms and 300000ms on save.</li>
          <li>Disabling a timer prevents that machine-page background task from running.</li>
          <li>Changes apply globally and are used by machine monitoring screens.</li>
        </ul>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/40">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Required Patch Commands</h3>
          <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
            Super Admin can run required maintenance commands directly from this panel.
          </p>

          <ul class="mt-3 space-y-1.5 text-xs font-mono text-slate-600 dark:text-slate-300">
            <li>php artisan storage:link</li>
            <li>php artisan config:clear</li>
            <li>php artisan cache:clear</li>
            <li>php artisan route:clear</li>
            <li>php artisan view:clear</li>
            <li>php artisan migrate --force</li>
          </ul>

          <button
            type="button"
            @click="runMaintenancePatch"
            :disabled="patching"
            class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-70 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/30"
          >
            {{ patching ? 'Running Patch...' : 'Run Required Patch' }}
          </button>

          <div v-if="patchResults.length" class="mt-4 space-y-2">
            <div
              v-for="result in patchResults"
              :key="result.command"
              class="rounded-lg border px-3 py-2 text-xs"
              :class="result.success ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-300' : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300'"
            >
              <p class="font-semibold">{{ result.command }} <span class="ml-1">({{ result.exit_code }})</span></p>
              <p v-if="result.output" class="mt-1 whitespace-pre-line break-words text-[11px] opacity-90">{{ result.output }}</p>
            </div>
          </div>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/40">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">System Update</h3>
          <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
            Pull latest code from main branch.
          </p>

          <ul class="mt-3 space-y-1.5 text-xs font-mono text-slate-600 dark:text-slate-300">
            <li>git pull origin main</li>
          </ul>

          <button
            type="button"
            @click="runSystemUpdate"
            :disabled="updating"
            class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-70 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/30"
          >
            {{ updating ? 'Running Update...' : 'Run System Update' }}
          </button>

          <div v-if="updateResults.length" class="mt-4 space-y-2">
            <div
              v-for="result in updateResults"
              :key="result.command"
              class="rounded-lg border px-3 py-2 text-xs"
              :class="result.success ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-300' : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300'"
            >
              <p class="font-semibold">{{ result.command }} <span class="ml-1">({{ result.exit_code }})</span></p>
              <p v-if="result.output" class="mt-1 whitespace-pre-line break-words text-[11px] opacity-90">{{ result.output }}</p>
            </div>
          </div>
        </div>
      </aside>
    </section>
  </div>
</template>
