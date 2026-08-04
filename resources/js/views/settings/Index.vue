<script setup>
import { onMounted, ref, computed } from 'vue'
import { storeToRefs } from 'pinia'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import Button from '@/components/ui/Button.vue'
import SettingsNav from '@/views/settings/SettingsNav.vue'
import SettingsSidebar from '@/views/settings/SettingsSidebar.vue'
import { useAppSettingStore } from '@/store/AppSettingStore'
import { useLicenseStore } from '@/store/LicenseStore'

const appSettingStore = useAppSettingStore()
const licenseStore = useLicenseStore()

const licenseStatus     = computed(() => licenseStore.status)
const licenseKey        = computed(() => licenseStore.licenseKey)
const licenseExpiry     = computed(() => licenseStore.licenseExpiry)
const licenseDaysLeft   = computed(() => licenseStore.licenseDaysLeft)
const trialDaysLeft     = computed(() => licenseStore.trialDaysLeft)
const trialExpiresAt    = computed(() => licenseStore.trialExpiresAt)
const {
  companySchoolName,
  companySchoolLogo,
  companySchoolLogoPrintEnabled,
  biometricDtrSignatoryName,
  biometricDtrSignatorySignature,
  biometricDtrSignatoryUseDefault,
  biometricDtrSignatorySignatureEnabled,
  machineAutoSyncStatusTimerEnabled,
  machineAutoSyncStatusTimerMs,
  machineRefreshTimerEnabled,
  machineRefreshTimerMs,
  machineWebAutoFallbackTimerEnabled,
  machineWebAutoFallbackTimerMs,
} = storeToRefs(appSettingStore)

const form = ref({
  company_school_name: '',
  company_school_logo: '',
  company_school_logo_print_enabled: false,
  biometric_dtr_signatory_name: '',
  biometric_dtr_signatory_signature: '',
  biometric_dtr_signatory_use_default: true,
  biometric_dtr_signatory_signature_enabled: false,
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
const deactivating = ref(false)
const replacing = ref(false)
const daemonStatus = ref(null)
const daemonLoading = ref(false)
const daemonInstalling = ref(false)
const daemonInstallResults = ref([])

const formatDate = (iso) => {
  if (!iso) return null
  return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
}

const maskedKey = computed(() => {
  const key = licenseKey.value
  if (!key) return null
  if (key.length <= 8) return '••••-' + key.slice(-4)
  return key.slice(0, 4) + '-••••-••••-' + key.slice(-4)
})

const handleReplaceLicense = async () => {
  const { value: newKey } = await Swal.fire({
    title: 'Replace License',
    text: 'Enter your new license key to replace the current one.',
    input: 'text',
    inputPlaceholder: 'XXXX-XXXX-XXXX-XXXX',
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Replace',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#0284c7',
    inputValidator: (value) => {
      if (!value || !value.trim()) {
        return 'License key cannot be empty'
      }
    },
  })

  if (!newKey) return

  replacing.value = true
  try {
    await licenseStore.activate(newKey.trim())
    toastResult('License replaced successfully', 'success')
  } catch (e) {
    toastResult(e?.response?.data?.message || 'Failed to replace license', 'error')
  } finally {
    replacing.value = false
  }
}

const handleDeactivateLicense = async () => {
  const confirmation = await Swal.fire({
    title: 'Remove License?',
    text: 'This will deactivate the current license key. You will need to re-enter it to regain access.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Remove',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#e11d48',
  })

  if (!confirmation.isConfirmed) return

  deactivating.value = true
  try {
    await licenseStore.deactivate()
    toastResult('License removed', 'success')
  } catch (e) {
    toastResult('Failed to remove license', 'error')
  } finally {
    deactivating.value = false
  }
}

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

const uploadLogo = async (event) => {
  const files = event?.target?.files
  if (!files || files.length <= 0) {
    return
  }

  const formData = new FormData()
  formData.append('file', files[0])

  try {
    const response = await axios.post('/api/media/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    form.value.company_school_logo = response?.data?.path || ''
    toastResult('Logo uploaded')
  } catch (error) {
    toastResult(error?.response?.data?.message || 'Unable to upload logo', 'error')
  } finally {
    event.target.value = ''
  }
}

const uploadSignature = async (event) => {
  const files = event?.target?.files
  if (!files || files.length <= 0) {
    return
  }

  const formData = new FormData()
  formData.append('file', files[0])

  try {
    const response = await axios.post('/api/media/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    form.value.biometric_dtr_signatory_signature = response?.data?.path || ''
    toastResult('Signature uploaded')
  } catch (error) {
    toastResult(error?.response?.data?.message || 'Unable to upload signature', 'error')
  } finally {
    event.target.value = ''
  }
}

const loadSettings = async () => {
  const resp = await appSettingStore.loadSettings()
  if (!resp.success) {
    toastResult('Unable to load settings', 'error')
    return
  }

  form.value.company_school_name = companySchoolName.value || 'Biometric System'
  form.value.company_school_logo = companySchoolLogo.value || ''
  form.value.company_school_logo_print_enabled = Boolean(companySchoolLogoPrintEnabled.value)
  form.value.biometric_dtr_signatory_name = biometricDtrSignatoryName.value ?? ''
  form.value.biometric_dtr_signatory_signature = biometricDtrSignatorySignature.value || ''
  form.value.biometric_dtr_signatory_use_default = Boolean(biometricDtrSignatoryUseDefault.value)
  form.value.biometric_dtr_signatory_signature_enabled = Boolean(biometricDtrSignatorySignatureEnabled.value)
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
    company_school_logo: form.value.company_school_logo,
    company_school_logo_print_enabled: Boolean(form.value.company_school_logo_print_enabled),
    biometric_dtr_signatory_name: form.value.biometric_dtr_signatory_name,
    biometric_dtr_signatory_signature: form.value.biometric_dtr_signatory_signature,
    biometric_dtr_signatory_use_default: Boolean(form.value.biometric_dtr_signatory_use_default),
    biometric_dtr_signatory_signature_enabled: Boolean(form.value.biometric_dtr_signatory_signature_enabled),
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
  form.value.company_school_logo = companySchoolLogo.value || form.value.company_school_logo
  form.value.company_school_logo_print_enabled = Boolean(companySchoolLogoPrintEnabled.value)
  form.value.biometric_dtr_signatory_name = biometricDtrSignatoryName.value ?? form.value.biometric_dtr_signatory_name
  form.value.biometric_dtr_signatory_signature = biometricDtrSignatorySignature.value || form.value.biometric_dtr_signatory_signature
  form.value.biometric_dtr_signatory_use_default = Boolean(biometricDtrSignatoryUseDefault.value)
  form.value.biometric_dtr_signatory_signature_enabled = Boolean(biometricDtrSignatorySignatureEnabled.value)
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

const loadAttendanceDaemonStatus = async () => {
  daemonLoading.value = true
  const resp = await appSettingStore.loadAttendanceDaemonStatus()
  daemonLoading.value = false

  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to load attendance daemon status', 'error')
    return
  }

  daemonStatus.value = resp.data || null
}

const installAttendanceDaemon = async () => {
  const confirmation = await Swal.fire({
    title: 'Install attendance daemon service?',
    text: 'This installs or repairs the attendance daemon service (Supervisor on Linux, Task Scheduler on Windows).',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Install Service',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#0284c7',
  })

  if (!confirmation.isConfirmed) {
    return
  }

  daemonInstalling.value = true
  daemonInstallResults.value = []

  const resp = await appSettingStore.installAttendanceDaemon({ sleep: 1 })
  daemonInstalling.value = false

  if (!resp.success) {
    daemonInstallResults.value = Array.isArray(resp?.data?.response?.data?.commands) ? resp.data.response.data.commands : []
    toastResult(resp?.data?.response?.data?.message || 'Unable to install daemon service', 'error')
    await loadAttendanceDaemonStatus()
    return
  }

  daemonInstallResults.value = Array.isArray(resp?.data?.commands) ? resp.data.commands : []
  toastResult(resp?.data?.message || 'Daemon service installed')
  await loadAttendanceDaemonStatus()
}

onMounted(async () => {
  await loadSettings()
  await loadAttendanceDaemonStatus()
})
</script>

<template>
  <div class="space-y-6">
    <SettingsNav />

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

    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-base font-semibold text-slate-800 dark:text-white">License</h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Current activation status for this installation.</p>
        </div>
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
          :class="{
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300': licenseStatus === 'licensed',
            'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300': licenseStatus === 'trial',
            'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300': licenseStatus === 'expired',
          }"
        >
          <span class="h-1.5 w-1.5 rounded-full"
            :class="{
              'bg-emerald-500': licenseStatus === 'licensed',
              'bg-sky-500': licenseStatus === 'trial',
              'bg-rose-500': licenseStatus === 'expired',
            }"
          ></span>
          {{ licenseStatus === 'licensed' ? 'Licensed' : licenseStatus === 'trial' ? 'Free Trial' : 'Expired' }}
        </span>
      </div>

      <div class="grid gap-4 px-5 py-5 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/40">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Plan Type</p>
          <p class="mt-1.5 text-sm font-semibold text-slate-800 dark:text-white">
            {{ licenseStatus === 'licensed' ? 'Paid License' : licenseStatus === 'trial' ? 'Free Trial (7 days)' : 'Trial Expired' }}
          </p>
        </div>

        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/40">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Expires</p>
          <p class="mt-1.5 text-sm font-semibold text-slate-800 dark:text-white">
            <template v-if="licenseStatus === 'licensed'">
              {{ licenseExpiry ? formatDate(licenseExpiry) : 'Never' }}
            </template>
            <template v-else-if="licenseStatus === 'trial'">
              {{ trialExpiresAt ? formatDate(trialExpiresAt) : '—' }}
            </template>
            <template v-else>—</template>
          </p>
        </div>

        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/40">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Days Remaining</p>
          <p class="mt-1.5 text-sm font-semibold"
            :class="{
              'text-emerald-600 dark:text-emerald-400': licenseStatus === 'licensed' && (licenseDaysLeft === null || licenseDaysLeft > 30),
              'text-amber-600 dark:text-amber-400': licenseStatus === 'licensed' && licenseDaysLeft !== null && licenseDaysLeft <= 30 && licenseDaysLeft > 7,
              'text-rose-600 dark:text-rose-400': (licenseStatus === 'licensed' && licenseDaysLeft !== null && licenseDaysLeft <= 7) || licenseStatus === 'expired',
              'text-sky-600 dark:text-sky-400': licenseStatus === 'trial',
            }"
          >
            <template v-if="licenseStatus === 'licensed'">
              {{ licenseDaysLeft === null ? 'Unlimited' : `${licenseDaysLeft} day${licenseDaysLeft !== 1 ? 's' : ''}` }}
            </template>
            <template v-else-if="licenseStatus === 'trial'">
              {{ trialDaysLeft }} day{{ trialDaysLeft !== 1 ? 's' : '' }}
            </template>
            <template v-else>0 days</template>
          </p>
        </div>
      </div>

      <div v-if="licenseStatus === 'licensed'" class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-xs text-slate-400 dark:text-slate-500">License Key</p>
          <p class="mt-0.5 font-mono text-sm font-semibold tracking-widest text-slate-700 dark:text-slate-200">{{ maskedKey }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="handleReplaceLicense"
            :disabled="replacing"
            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-4 text-xs font-semibold text-sky-600 transition hover:bg-sky-100 disabled:opacity-60 dark:border-sky-800/40 dark:bg-sky-950/20 dark:text-sky-400 dark:hover:bg-sky-950/40"
          >
            {{ replacing ? 'Replacing…' : 'Replace' }}
          </button>
          <button
            @click="handleDeactivateLicense"
            :disabled="deactivating"
            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-4 text-xs font-semibold text-rose-600 transition hover:bg-rose-100 disabled:opacity-60 dark:border-rose-800/40 dark:bg-rose-950/20 dark:text-rose-400 dark:hover:bg-rose-950/40"
          >
            {{ deactivating ? 'Removing…' : 'Remove' }}
          </button>
        </div>
      </div>

      <div v-else class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
        <p class="text-xs text-slate-500 dark:text-slate-400">
          <template v-if="licenseStatus === 'trial'">Upgrade to a paid license to continue after your trial ends.</template>
          <template v-else>Your trial has expired. Enter a license key to continue.</template>
          <router-link to="/license" class="ml-1 font-medium text-sky-600 hover:underline dark:text-sky-400">Activate License →</router-link>
        </p>
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
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

        <div class="mt-4">
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">Company / School Logo</label>
          <input
            type="file"
            accept="image/png,image/jpeg,image/jpg,image/webp"
            @change="uploadLogo"
            class="block w-full rounded-lg border border-slate-300 bg-transparent px-4 py-2.5 text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-sky-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-200 dark:border-slate-700 dark:text-slate-200 dark:file:bg-sky-900/30 dark:file:text-sky-300"
          />
          <div v-if="form.company_school_logo" class="mt-3 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40">
            <img :src="form.company_school_logo" alt="Company logo" class="h-14 w-14 rounded-lg bg-white object-contain p-1" />
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800 dark:text-white">Logo ready</p>
              <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ form.company_school_logo }}</p>
            </div>
          </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
          <label class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-300">
            <input v-model="form.company_school_logo_print_enabled" type="checkbox" class="h-4 w-4" />
            Show logo in printed reports
          </label>
          <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            When enabled, the uploaded logo will appear in printable attendance forms.
          </p>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Biometric DTR Signatory</h3>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Enter the default signatory name and optional e-signature used in printable biometric DTR forms.
              </p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
              <input v-model="form.biometric_dtr_signatory_use_default" type="checkbox" class="h-4 w-4" />
              Use as default in Biometric DTR
            </label>
          </div>

          <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_1fr]">
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">Signatory Name (optional)</label>
              <input
                v-model.trim="form.biometric_dtr_signatory_name"
                type="text"
                placeholder="Optional"
                :disabled="!form.biometric_dtr_signatory_use_default"
                class="h-11 w-full rounded-lg border border-slate-300 bg-transparent px-4 py-2.5 text-sm text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:text-white/90"
              />
              <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">This name prints above the signature line when the default is enabled.</p>
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">E-Signature</label>
              <input
                type="file"
                accept="image/png,image/jpeg,image/jpg,image/webp"
                @change="uploadSignature"
                :disabled="!form.biometric_dtr_signatory_use_default"
                class="block w-full rounded-lg border border-slate-300 bg-transparent px-4 py-2.5 text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-sky-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-200 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:file:bg-sky-900/30 dark:file:text-sky-300"
              />
              <div v-if="form.biometric_dtr_signatory_signature" class="mt-3 flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-950/30">
                <img :src="form.biometric_dtr_signatory_signature" alt="Signatory signature" class="h-12 max-w-[160px] object-contain" />
                <div class="min-w-0">
                  <p class="text-sm font-medium text-slate-800 dark:text-white">Signature ready</p>
                  <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ form.biometric_dtr_signatory_signature }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4 flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-950/30 dark:text-slate-400">
            <div>
              <p class="font-semibold text-slate-700 dark:text-slate-300">Print Preview</p>
              <p class="mt-1">
                {{ form.biometric_dtr_signatory_use_default ? (form.biometric_dtr_signatory_name || 'Blank') : 'Manual / default off' }}
              </p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
              <input v-model="form.biometric_dtr_signatory_signature_enabled" type="checkbox" class="h-4 w-4" />
              Use e-signature in Biometric DTR
            </label>
          </div>
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

      <SettingsSidebar
        :patching="patching"
        :patch-results="patchResults"
        :updating="updating"
        :update-results="updateResults"
        :daemon-status="daemonStatus"
        :daemon-loading="daemonLoading"
        :daemon-installing="daemonInstalling"
        :daemon-install-results="daemonInstallResults"
        :on-refresh-daemon-status="loadAttendanceDaemonStatus"
        :on-install-daemon="installAttendanceDaemon"
        :on-run-maintenance-patch="runMaintenancePatch"
        :on-run-system-update="runSystemUpdate"
      />
    </section>
  </div>
</template>
