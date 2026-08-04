<script setup>
import { computed } from 'vue'

const props = defineProps({
  patching: { type: Boolean, required: true },
  patchResults: { type: Array, required: true },
  updating: { type: Boolean, required: true },
  updateResults: { type: Array, required: true },
  daemonStatus: { type: Object, default: null },
  daemonLoading: { type: Boolean, required: true },
  daemonInstalling: { type: Boolean, required: true },
  daemonInstallResults: { type: Array, required: true },
  onRefreshDaemonStatus: { type: Function, required: true },
  onInstallDaemon: { type: Function, required: true },
  onRunMaintenancePatch: { type: Function, required: true },
  onRunSystemUpdate: { type: Function, required: true },
})

const daemonPlatformLabel = computed(() => {
  const os = String(props.daemonStatus?.os || '').toLowerCase()
  if (os.includes('win')) {
    return 'Windows'
  }
  if (os.includes('linux')) {
    return 'Ubuntu/Linux'
  }
  return 'Server'
})

const daemonConfigHint = computed(() => {
  return props.daemonStatus?.config_path || '/etc/supervisor/conf.d/attendance-auto-sync.conf'
})
</script>

<template>
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
        @click="props.onRunMaintenancePatch()"
        :disabled="props.patching"
        class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-70 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/30"
      >
        {{ props.patching ? 'Running Patch...' : 'Run Required Patch' }}
      </button>

      <div v-if="props.patchResults.length" class="mt-4 space-y-2">
        <div
          v-for="result in props.patchResults"
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
      <div class="flex items-start justify-between gap-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Attendance Daemon ({{ daemonPlatformLabel }})</h3>
        <button
          type="button"
          @click="props.onRefreshDaemonStatus()"
          :disabled="props.daemonLoading"
          class="inline-flex h-8 items-center justify-center rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
        >
          {{ props.daemonLoading ? 'Checking...' : 'Refresh' }}
        </button>
      </div>

      <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
        Checks and installs <span class="font-mono">{{ daemonConfigHint }}</span>.
      </p>

      <div class="mt-3 space-y-2 text-xs">
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
          <p class="font-semibold text-slate-700 dark:text-slate-300">Config File</p>
          <p class="mt-0.5" :class="props.daemonStatus?.config_exists ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
            {{ props.daemonStatus?.config_exists ? 'Present' : 'Missing' }}
          </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
          <p class="font-semibold text-slate-700 dark:text-slate-300">Service Status</p>
          <p class="mt-0.5" :class="props.daemonStatus?.service_running ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
            {{ props.daemonStatus?.service_running ? 'RUNNING' : 'NOT RUNNING' }}
          </p>
        </div>
      </div>

      <button
        type="button"
        @click="props.onInstallDaemon()"
        :disabled="props.daemonInstalling"
        class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-70 dark:border-indigo-900/40 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
      >
        {{ props.daemonInstalling ? 'Installing Daemon...' : 'Install / Repair Daemon Service' }}
      </button>

      <div v-if="props.daemonInstallResults.length" class="mt-4 space-y-2">
        <div
          v-for="(result, index) in props.daemonInstallResults"
          :key="index"
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
        @click="props.onRunSystemUpdate()"
        :disabled="props.updating"
        class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-70 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/30"
      >
        {{ props.updating ? 'Running Update...' : 'Run System Update' }}
      </button>

      <div v-if="props.updateResults.length" class="mt-4 space-y-2">
        <div
          v-for="result in props.updateResults"
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
</template>
