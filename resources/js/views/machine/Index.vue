<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/common/Modal.vue'
import ModalDelete from '@/components/common/ModalDelete.vue'
import { PlusIcon, PencilIcon, TrashIcon, PlugInIcon, RefreshIcon } from '@/icons'
import { useMachineStore } from '@/store/MachineStore'
import { useAppSettingStore } from '@/store/AppSettingStore'

const machineStore = useMachineStore()
const appSettingStore = useAppSettingStore()
const { machines } = storeToRefs(machineStore)
const search = ref('')
const isModalOpen = ref(false)
const isDeleteModal = ref(false)
const isEdit = ref(false)
const selectedMachine = ref(null)

const connectingIds = ref(new Set())
const syncingIds = ref(new Set())
const clearingLogIds = ref(new Set())
const pushingUsers = ref(false)
const pushingUserIds = ref(new Set())
const autoToggleIds = ref(new Set())
const autoSyncDaemonStatus = ref({
  running: false,
  sleep: 1,
  last_heartbeat: null,
})
const webAutoFallbackEnabled = ref(false)
let autoSyncStatusTimer = null
let machineRefreshTimer = null
let webAutoFallbackTimer = null
let webAutoFallbackRunning = false
const webLastRunAt = new Map()
const WEB_AUTO_FALLBACK_STORAGE_KEY = 'machine-web-auto-fallback-enabled'

const ensureIntervalMs = (value, fallback) => {
  const parsed = Number(value)
  if (!Number.isFinite(parsed)) {
    return fallback
  }

  return Math.min(300000, Math.max(250, Math.floor(parsed)))
}

const defaultForm = () => ({
  ID: null,
  MachineAlias: '',
  ConnectType: 'TCP/IP',
  IP: '10.210.18.83',
  SerialPort: '',
  Port: 4370,
  Baudrate: 115200,
  MachineNumber: 1,
  IsHost: false,
  Enabled: true,
  CommPassword: '',
  UILanguage: '',
  DateFormat: '',
  InOutRecordWarn: 0,
  Idle: 0,
  Voice: 0,
  managercount: 0,
  usercount: 0,
  fingercount: 0,
  SecretCount: 0,
  FirmwareVersion: '',
  ProductType: '',
  LockControl: '',
  Purpose: '',
  ProduceKind: '',
  sn: '',
  PhotoStamp: false,
  IsIfChangeConfigServer2: false,
  pushver: '',
  IsAndroid: false,
  AutoDownload: false,
  AutoDownloadInterval: 60,
  AutoDownloadUserFilter: 'existing',
})

const form = ref(defaultForm())

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 1500,
  timerProgressBar: true,
})

const filteredMachines = computed(() => {
  const term = search.value.toLowerCase().trim()
  if (!term) {
    return machines.value
  }

  return machines.value.filter((item) => {
    return [
      item.MachineAlias,
      item.ConnectType,
      item.IP,
      item.SerialPort,
      item.ProductType,
      item.FirmwareVersion,
      item.sn,
    ].some((value) => (value || '').toString().toLowerCase().includes(term))
  })
})

const machineStats = computed(() => {
  const total = machines.value.length
  const enabled = machines.value.filter((machine) => machine.Enabled).length
  const autoEnabled = machines.value.filter((machine) => machine.AutoDownload).length
  const activeAuto = machines.value.filter((machine) => getMachineAutoSyncState(machine).label === 'Active').length

  return {
    total,
    enabled,
    autoEnabled,
    activeAuto,
  }
})

const toastResult = (message, icon = 'success') => {
  Toast.fire({ icon, title: message })
}

const formatLastAutoSync = (value) => {
  if (!value) {
    return 'Never'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return 'Never'
  }

  return date.toLocaleString()
}

const getMachineAutoSyncState = (machine) => {
  if (!machine?.AutoDownload) {
    return {
      label: 'OFF',
      className: 'bg-gray-100 text-gray-600 dark:bg-gray-700/70 dark:text-gray-300',
    }
  }

  const intervalSeconds = Math.max(1, Number(machine.AutoDownloadInterval || 60))
  const lastValue = machine.AutoDownloadLastSyncedAt

  if (!lastValue) {
    return {
      label: 'Waiting',
      className: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    }
  }

  const last = new Date(lastValue)
  if (Number.isNaN(last.getTime())) {
    return {
      label: 'Waiting',
      className: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    }
  }

  const elapsedSeconds = (Date.now() - last.getTime()) / 1000
  const staleAfterSeconds = Math.max(5, intervalSeconds * 3)

  if (elapsedSeconds <= staleAfterSeconds) {
    return {
      label: 'Active',
      className: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    }
  }

  return {
    label: 'Stale',
    className: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  }
}

const loadMachines = async () => {
  const resp = await machineStore.loadMachines()
  if (!resp.success) {
    toastResult('Unable to load machines', 'error')
  }
}

const refreshMachinesSilently = async () => {
  await machineStore.loadMachines()
}

const loadAutoSyncStatus = async () => {
  const resp = await machineStore.autoSyncStatus()
  if (!resp.success) {
    return
  }

  autoSyncDaemonStatus.value = {
    running: Boolean(resp?.data?.running),
    sleep: Number(resp?.data?.sleep || 1),
    last_heartbeat: resp?.data?.last_heartbeat || null,
  }
}

const loadWebAutoFallbackSetting = () => {
  webAutoFallbackEnabled.value = localStorage.getItem(WEB_AUTO_FALLBACK_STORAGE_KEY) === '1'
}

const toggleWebAutoFallback = () => {
  webAutoFallbackEnabled.value = !webAutoFallbackEnabled.value
  localStorage.setItem(WEB_AUTO_FALLBACK_STORAGE_KEY, webAutoFallbackEnabled.value ? '1' : '0')
  toastResult(`Web auto fallback ${webAutoFallbackEnabled.value ? 'enabled' : 'disabled'}`)
}

const clearMachineTimers = () => {
  if (autoSyncStatusTimer) {
    clearInterval(autoSyncStatusTimer)
    autoSyncStatusTimer = null
  }

  if (machineRefreshTimer) {
    clearInterval(machineRefreshTimer)
    machineRefreshTimer = null
  }

  if (webAutoFallbackTimer) {
    clearInterval(webAutoFallbackTimer)
    webAutoFallbackTimer = null
  }
}

const applyMachineTimerSettings = () => {
  clearMachineTimers()

  if (appSettingStore.machineAutoSyncStatusTimerEnabled) {
    autoSyncStatusTimer = window.setInterval(
      loadAutoSyncStatus,
      ensureIntervalMs(appSettingStore.machineAutoSyncStatusTimerMs, 5000)
    )
  }

  if (appSettingStore.machineRefreshTimerEnabled) {
    machineRefreshTimer = window.setInterval(
      refreshMachinesSilently,
      ensureIntervalMs(appSettingStore.machineRefreshTimerMs, 5000)
    )
  }

  if (appSettingStore.machineWebAutoFallbackTimerEnabled) {
    webAutoFallbackTimer = window.setInterval(
      runWebAutoFallbackCycle,
      ensureIntervalMs(appSettingStore.machineWebAutoFallbackTimerMs, 1000)
    )
  }
}

const openMachineTimerSettings = async () => {
  const result = await Swal.fire({
    title: 'Machine Page Timer Settings',
    html: `
      <div class="space-y-3 text-left">
        <div class="rounded-md border border-gray-200 p-2">
          <label class="mb-1 inline-flex items-center gap-2 text-sm font-medium text-gray-700">
            <input id="timer-status-enabled" type="checkbox" ${appSettingStore.machineAutoSyncStatusTimerEnabled ? 'checked' : ''} />
            Daemon Status Poll
          </label>
          <input id="timer-status-ms" type="number" min="250" max="300000" class="swal2-input !m-0 !mt-1 !w-full" value="${ensureIntervalMs(appSettingStore.machineAutoSyncStatusTimerMs, 5000)}" />
          <p class="mt-1 text-xs text-gray-500">Interval in milliseconds</p>
        </div>
        <div class="rounded-md border border-gray-200 p-2">
          <label class="mb-1 inline-flex items-center gap-2 text-sm font-medium text-gray-700">
            <input id="timer-refresh-enabled" type="checkbox" ${appSettingStore.machineRefreshTimerEnabled ? 'checked' : ''} />
            Machine List Refresh
          </label>
          <input id="timer-refresh-ms" type="number" min="250" max="300000" class="swal2-input !m-0 !mt-1 !w-full" value="${ensureIntervalMs(appSettingStore.machineRefreshTimerMs, 5000)}" />
          <p class="mt-1 text-xs text-gray-500">Interval in milliseconds</p>
        </div>
        <div class="rounded-md border border-gray-200 p-2">
          <label class="mb-1 inline-flex items-center gap-2 text-sm font-medium text-gray-700">
            <input id="timer-fallback-enabled" type="checkbox" ${appSettingStore.machineWebAutoFallbackTimerEnabled ? 'checked' : ''} />
            Web Auto Fallback Cycle
          </label>
          <input id="timer-fallback-ms" type="number" min="250" max="300000" class="swal2-input !m-0 !mt-1 !w-full" value="${ensureIntervalMs(appSettingStore.machineWebAutoFallbackTimerMs, 1000)}" />
          <p class="mt-1 text-xs text-gray-500">Interval in milliseconds</p>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Save',
    preConfirm: () => {
      const statusEnabled = Boolean(document.getElementById('timer-status-enabled')?.checked)
      const refreshEnabled = Boolean(document.getElementById('timer-refresh-enabled')?.checked)
      const fallbackEnabled = Boolean(document.getElementById('timer-fallback-enabled')?.checked)

      const statusMs = ensureIntervalMs(document.getElementById('timer-status-ms')?.value, 5000)
      const refreshMs = ensureIntervalMs(document.getElementById('timer-refresh-ms')?.value, 5000)
      const fallbackMs = ensureIntervalMs(document.getElementById('timer-fallback-ms')?.value, 1000)

      return {
        statusEnabled,
        statusMs,
        refreshEnabled,
        refreshMs,
        fallbackEnabled,
        fallbackMs,
      }
    },
  })

  if (!result.isConfirmed) {
    return
  }

  const payload = {
    company_school_name: appSettingStore.companySchoolName || 'Biometric System',
    machine_auto_sync_status_timer_enabled: result.value.statusEnabled,
    machine_auto_sync_status_timer_ms: result.value.statusMs,
    machine_refresh_timer_enabled: result.value.refreshEnabled,
    machine_refresh_timer_ms: result.value.refreshMs,
    machine_web_auto_fallback_timer_enabled: result.value.fallbackEnabled,
    machine_web_auto_fallback_timer_ms: result.value.fallbackMs,
  }

  const resp = await appSettingStore.updateSettings(payload)
  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to save timer settings', 'error')
    return
  }

  applyMachineTimerSettings()
  toastResult('Timer settings updated')
}

const runWebAutoFallbackCycle = async () => {
  if (webAutoFallbackRunning || !webAutoFallbackEnabled.value || autoSyncDaemonStatus.value.running) {
    return
  }

  webAutoFallbackRunning = true

  try {
    const now = Date.now()

    for (const machine of machines.value) {
      if (!machine?.AutoDownload || !machine?.Enabled || !machine?.IP) {
        continue
      }

      const machineId = machine.ID
      const intervalMs = Math.max(1, Number(machine.AutoDownloadInterval || 60)) * 1000
      const lastRun = webLastRunAt.get(machineId) || 0

      if (now - lastRun < intervalMs) {
        continue
      }

      if (
        connectingIds.value.has(machineId) ||
        syncingIds.value.has(machineId) ||
        pushingUserIds.value.has(machineId)
      ) {
        continue
      }

      webLastRunAt.set(machineId, now)
      syncingIds.value = new Set([...syncingIds.value, machineId])

      try {
        await machineStore.syncAttendance({
          ID: machineId,
          download_scope: 'today',
          user_filter: machine.AutoDownloadUserFilter === 'all' ? 'all' : 'existing',
        })
      } finally {
        syncingIds.value = new Set([...syncingIds.value].filter((id) => id !== machineId))
      }
    }
  } finally {
    webAutoFallbackRunning = false
  }
}

const openCreate = () => {
  isEdit.value = false
  form.value = defaultForm()
  isModalOpen.value = true
}

const openEdit = (machine) => {
  isEdit.value = true
  form.value = {
    ...defaultForm(),
    ...machine,
    ID: machine.ID,
    IsHost: Boolean(machine.IsHost),
    Enabled: Boolean(machine.Enabled),
    PhotoStamp: Boolean(machine.PhotoStamp),
    IsIfChangeConfigServer2: Boolean(machine.IsIfChangeConfigServer2),
    IsAndroid: Boolean(machine.IsAndroid),
    AutoDownload: Boolean(machine.AutoDownload),
    AutoDownloadInterval: machine.AutoDownloadInterval === null || machine.AutoDownloadInterval === undefined
      ? 60
      : Number(machine.AutoDownloadInterval),
    AutoDownloadUserFilter: machine.AutoDownloadUserFilter === 'all' ? 'all' : 'existing',
  }
  isModalOpen.value = true
}

const openDelete = (machine) => {
  selectedMachine.value = machine
  isDeleteModal.value = true
}

const normalizePayload = () => ({
  ID: form.value.ID,
  MachineAlias: form.value.MachineAlias,
  ConnectType: form.value.ConnectType || null,
  IP: form.value.IP || null,
  SerialPort: form.value.SerialPort || null,
  Port: form.value.Port === '' || form.value.Port === null ? null : Number(form.value.Port),
  Baudrate: form.value.Baudrate === '' || form.value.Baudrate === null ? null : Number(form.value.Baudrate),
  MachineNumber: form.value.MachineNumber === '' || form.value.MachineNumber === null ? null : Number(form.value.MachineNumber),
  IsHost: Boolean(form.value.IsHost),
  Enabled: Boolean(form.value.Enabled),
  CommPassword: form.value.CommPassword || null,
  UILanguage: form.value.UILanguage || null,
  DateFormat: form.value.DateFormat || null,
  InOutRecordWarn: form.value.InOutRecordWarn === '' || form.value.InOutRecordWarn === null ? null : Number(form.value.InOutRecordWarn),
  Idle: form.value.Idle === '' || form.value.Idle === null ? null : Number(form.value.Idle),
  Voice: form.value.Voice === '' || form.value.Voice === null ? null : Number(form.value.Voice),
  managercount: form.value.managercount === '' || form.value.managercount === null ? 0 : Number(form.value.managercount),
  usercount: form.value.usercount === '' || form.value.usercount === null ? 0 : Number(form.value.usercount),
  fingercount: form.value.fingercount === '' || form.value.fingercount === null ? 0 : Number(form.value.fingercount),
  SecretCount: form.value.SecretCount === '' || form.value.SecretCount === null ? 0 : Number(form.value.SecretCount),
  FirmwareVersion: form.value.FirmwareVersion || null,
  ProductType: form.value.ProductType || null,
  LockControl: form.value.LockControl || null,
  Purpose: form.value.Purpose || null,
  ProduceKind: form.value.ProduceKind || null,
  sn: form.value.sn || null,
  PhotoStamp: Boolean(form.value.PhotoStamp),
  IsIfChangeConfigServer2: Boolean(form.value.IsIfChangeConfigServer2),
  pushver: form.value.pushver || null,
  IsAndroid: Boolean(form.value.IsAndroid),
  AutoDownload: Boolean(form.value.AutoDownload),
  AutoDownloadInterval: form.value.AutoDownloadInterval === '' || form.value.AutoDownloadInterval === null
    ? 60
    : Number(form.value.AutoDownloadInterval),
  AutoDownloadUserFilter: form.value.AutoDownloadUserFilter === 'all' ? 'all' : 'existing',
})

const normalizeMachinePayload = (machine) => ({
  ID: machine.ID,
  MachineAlias: machine.MachineAlias,
  ConnectType: machine.ConnectType || null,
  IP: machine.IP || null,
  SerialPort: machine.SerialPort || null,
  Port: machine.Port === '' || machine.Port === null ? null : Number(machine.Port),
  Baudrate: machine.Baudrate === '' || machine.Baudrate === null ? null : Number(machine.Baudrate),
  MachineNumber: machine.MachineNumber === '' || machine.MachineNumber === null ? null : Number(machine.MachineNumber),
  IsHost: Boolean(machine.IsHost),
  Enabled: Boolean(machine.Enabled),
  CommPassword: machine.CommPassword || null,
  UILanguage: machine.UILanguage || null,
  DateFormat: machine.DateFormat || null,
  InOutRecordWarn: machine.InOutRecordWarn === '' || machine.InOutRecordWarn === null ? null : Number(machine.InOutRecordWarn),
  Idle: machine.Idle === '' || machine.Idle === null ? null : Number(machine.Idle),
  Voice: machine.Voice === '' || machine.Voice === null ? null : Number(machine.Voice),
  managercount: machine.managercount === '' || machine.managercount === null ? 0 : Number(machine.managercount),
  usercount: machine.usercount === '' || machine.usercount === null ? 0 : Number(machine.usercount),
  fingercount: machine.fingercount === '' || machine.fingercount === null ? 0 : Number(machine.fingercount),
  SecretCount: machine.SecretCount === '' || machine.SecretCount === null ? 0 : Number(machine.SecretCount),
  FirmwareVersion: machine.FirmwareVersion || null,
  ProductType: machine.ProductType || null,
  LockControl: machine.LockControl || null,
  Purpose: machine.Purpose || null,
  ProduceKind: machine.ProduceKind || null,
  sn: machine.sn || null,
  PhotoStamp: Boolean(machine.PhotoStamp),
  IsIfChangeConfigServer2: Boolean(machine.IsIfChangeConfigServer2),
  pushver: machine.pushver || null,
  IsAndroid: Boolean(machine.IsAndroid),
  AutoDownload: Boolean(machine.AutoDownload),
  AutoDownloadInterval: machine.AutoDownloadInterval === '' || machine.AutoDownloadInterval === null || machine.AutoDownloadInterval === undefined
    ? 60
    : Number(machine.AutoDownloadInterval),
  AutoDownloadUserFilter: machine.AutoDownloadUserFilter === 'all' ? 'all' : 'existing',
})

const toggleAutoDownload = async (machine) => {
  if (!machine?.ID || !machine?.IP || !machine?.Enabled) {
    toastResult('Machine must be enabled and have an IP address', 'warning')
    return
  }

  const settingResp = await Swal.fire({
    title: `Auto Download Settings` ,
    html: `
      <div class="space-y-3 text-left">
        <p class="text-sm text-gray-600">Set background sync interval in seconds for <strong>${machine.MachineAlias || machine.IP}</strong>.</p>
        <div>
          <label for="auto-download-interval" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Interval (seconds)</label>
          <input id="auto-download-interval" type="number" min="1" max="86400" class="swal2-input !m-0 !w-full" value="${Number(machine.AutoDownloadInterval || 60)}" />
        </div>
        <div>
          <label for="auto-download-user-filter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">User Filter</label>
          <select id="auto-download-user-filter" class="swal2-input !m-0 !w-full">
            <option value="existing" ${machine.AutoDownloadUserFilter === 'all' ? '' : 'selected'}>Only Existing Local Users</option>
            <option value="all" ${machine.AutoDownloadUserFilter === 'all' ? 'selected' : ''}>All Logs (Include Unknown Users)</option>
          </select>
        </div>
      </div>
    `,
    showCancelButton: true,
    showDenyButton: Boolean(machine.AutoDownload),
    denyButtonText: 'Turn Off',
    confirmButtonText: machine.AutoDownload ? 'Save & Keep On' : 'Turn On',
    cancelButtonText: 'Cancel',
    preConfirm: () => {
      const value = Number(document.getElementById('auto-download-interval')?.value || 0)
      if (!Number.isFinite(value) || value < 1 || value > 86400) {
        Swal.showValidationMessage('Interval must be between 1 and 86400 seconds.')
        return false
      }

      return {
        autoDownload: true,
        interval: Math.floor(value),
        userFilter: document.getElementById('auto-download-user-filter')?.value === 'all' ? 'all' : 'existing',
      }
    },
  })

  if (settingResp.isDismissed) {
    return
  }

  const targetAutoDownload = settingResp.isDenied ? false : true
  const targetInterval = settingResp.value?.interval || Number(machine.AutoDownloadInterval || 60)
  const targetUserFilter = settingResp.value?.userFilter || (machine.AutoDownloadUserFilter === 'all' ? 'all' : 'existing')

  autoToggleIds.value = new Set([...autoToggleIds.value, machine.ID])

  const resp = await machineStore.updateMachine(normalizeMachinePayload({
    ...machine,
    AutoDownload: targetAutoDownload,
    AutoDownloadInterval: targetInterval,
    AutoDownloadUserFilter: targetUserFilter,
  }))

  autoToggleIds.value = new Set([...autoToggleIds.value].filter((id) => id !== machine.ID))

  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to update auto download', 'error')
    return
  }

  const latest = resp?.data?.machine || machine
  toastResult(
    latest.AutoDownload
      ? `Auto download enabled every ${latest.AutoDownloadInterval || targetInterval}s: ${latest.MachineAlias || latest.IP}`
      : `Auto download disabled: ${latest.MachineAlias || latest.IP}`
  )
}

const saveMachine = async () => {
  const payload = normalizePayload()
  const resp = isEdit.value
    ? await machineStore.updateMachine(payload)
    : await machineStore.storeMachine(payload)

  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to save machine', 'error')
    return
  }

  toastResult(isEdit.value ? 'Machine updated' : 'Machine created')
  isModalOpen.value = false
}

const deleteMachine = async () => {
  if (!selectedMachine.value) {
    return
  }

  const resp = await machineStore.deleteMachine({ ID: selectedMachine.value.ID })
  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to delete machine', 'error')
    return
  }

  toastResult('Machine removed')
  isDeleteModal.value = false
  selectedMachine.value = null
}

const connectMachine = async (machine) => {
  connectingIds.value = new Set([...connectingIds.value, machine.ID])

  const resp = await machineStore.connectMachine({ ID: machine.ID })

  connectingIds.value = new Set([...connectingIds.value].filter((id) => id !== machine.ID))

  if (!resp.success) {
    const msg = resp?.data?.response?.data?.message || 'Connection failed'
    await Swal.fire({
      icon: 'error',
      title: 'Connection Failed',
      text: msg,
      confirmButtonText: 'OK',
    })
    return
  }

  const info = resp.data?.info || {}
  const lines = [
    info.DeviceName   ? `Device: ${info.DeviceName}`     : null,
    info.SerialNumber ? `S/N: ${info.SerialNumber}`       : null,
    info.FirmVer      ? `Firmware: ${info.FirmVer}`       : null,
    info.Manufacturer ? `Manufacturer: ${info.Manufacturer}` : null,
    info.ProduceKind  ? `Kind: ${info.ProduceKind}`       : null,
  ].filter(Boolean).join('\n')

  await Swal.fire({
    icon: 'success',
    title: 'Connected',
    text: lines || 'Device responded successfully.',
    confirmButtonText: 'OK',
  })
}

const syncAttendance = async (machine) => {
  const downloadChoice = await Swal.fire({
    title: `Download Logs from ${machine.MachineAlias || machine.IP}`,
    html: `
      <div class="space-y-3 text-left">
        <p class="text-sm text-gray-600">Choose which attendance logs to download.</p>
        <div>
          <label for="download-scope" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Download Scope</label>
          <select id="download-scope" class="swal2-input !m-0 !w-full">
            <option value="today" selected>Today</option>
            <option value="date">Specific Date</option>
            <option value="all">All Logs</option>
          </select>
        </div>
        <div>
          <label for="user-filter" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">User Filter</label>
          <select id="user-filter" class="swal2-input !m-0 !w-full">
            <option value="existing" selected>Only Existing Local Users</option>
            <option value="all">All Logs (Include Unknown Users)</option>
          </select>
        </div>
        <div id="download-date-wrapper" class="hidden">
          <label for="download-date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Select Date</label>
          <input id="download-date" type="date" class="swal2-input !m-0 !w-full" />
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Download',
    focusConfirm: false,
    didOpen: () => {
      const scopeSelect = document.getElementById('download-scope')
      const dateWrapper = document.getElementById('download-date-wrapper')

      const syncDateVisibility = () => {
        const isDate = scopeSelect?.value === 'date'
        dateWrapper?.classList.toggle('hidden', !isDate)
      }

      scopeSelect?.addEventListener('change', syncDateVisibility)
      syncDateVisibility()
    },
    preConfirm: () => {
      const scope = document.getElementById('download-scope')?.value || 'today'
      const date = document.getElementById('download-date')?.value || null
      const userFilter = document.getElementById('user-filter')?.value || 'existing'

      if (scope === 'date' && !date) {
        Swal.showValidationMessage('Please select a date.')
        return false
      }

      return {
        download_scope: scope,
        download_date: scope === 'date' ? date : null,
        user_filter: userFilter,
      }
    },
  })

  if (!downloadChoice.isConfirmed) {
    return
  }

  syncingIds.value = new Set([...syncingIds.value, machine.ID])

  const resp = await machineStore.syncAttendance({
    ID: machine.ID,
    ...(downloadChoice.value || {}),
  })
  console.log('Sync response:', resp)

  syncingIds.value = new Set([...syncingIds.value].filter((id) => id !== machine.ID))

  if (!resp.success) {
    const msg = resp?.data?.response?.data?.message || 'Sync failed'
    await Swal.fire({
      icon: 'error',
      title: 'Download Failed',
      text: msg,
      confirmButtonText: 'OK',
    })
    return
  }

  const { total, imported, skipped, download_scope, download_date, user_filter } = resp.data
  const scopeLabel = download_scope === 'all'
    ? 'All Logs'
    : download_scope === 'date'
      ? `Date: ${download_date}`
      : 'Today'

  const userFilterLabel = user_filter === 'all'
    ? 'All Logs (Include Unknown Users)'
    : 'Only Existing Local Users'

  await Swal.fire({
    icon: 'success',
    title: 'Download Complete',
    html: `<p class="text-sm text-gray-600">Downloaded scope: <strong>${scopeLabel}</strong></p>
           <p class="text-sm text-gray-600">User filter: <strong>${userFilterLabel}</strong></p>
           <p class="text-sm text-gray-600">Total records from device: <strong>${total}</strong></p>
           <p class="text-sm text-gray-600">Imported: <strong class="text-green-600">${imported}</strong></p>
           <p class="text-sm text-gray-600">Skipped (duplicates / unmatched): <strong>${skipped}</strong></p>`,
    confirmButtonText: 'OK',
  })
}

const clearAttendanceLogs = async (machine) => {
  const confirm = await Swal.fire({
    icon: 'warning',
    title: `Clear Logs from ${machine.MachineAlias || machine.IP}`,
    html: '<p class="text-sm text-gray-600">This will delete attendance logs stored on the device. Local downloaded logs in this system will not be removed.</p>',
    showCancelButton: true,
    confirmButtonText: 'Clear Device Logs',
    confirmButtonColor: '#dc2626',
  })

  if (!confirm.isConfirmed) {
    return
  }

  clearingLogIds.value = new Set([...clearingLogIds.value, machine.ID])

  const resp = await machineStore.clearAttendance({ ID: machine.ID })

  clearingLogIds.value = new Set([...clearingLogIds.value].filter((id) => id !== machine.ID))

  if (!resp.success) {
    await Swal.fire({
      icon: 'error',
      title: 'Clear Logs Failed',
      text: resp?.data?.response?.data?.message || 'Unable to clear logs from device.',
      confirmButtonText: 'OK',
    })
    return
  }

  toastResult(resp?.data?.message || 'Device logs cleared')
}

const pushUsersToAllMachines = async () => {
  const confirm = await Swal.fire({
    icon: 'question',
    title: 'Push Users to All Devices',
    html: `<p class="text-sm text-gray-600">This will update <strong>all users</strong> on every enabled biometric machine.</p>`,
    showCancelButton: true,
    confirmButtonText: 'Push Now',
    cancelButtonText: 'Cancel',
  })

  if (!confirm.isConfirmed) return

  pushingUsers.value = true

  const resp = await machineStore.pushUsers({})

  pushingUsers.value = false

  if (!resp.success) {
    await Swal.fire({
      icon: 'error',
      title: 'Push Failed',
      text: resp?.data?.response?.data?.message || 'Failed to push users.',
      confirmButtonText: 'OK',
    })
    return
  }

  const {
    total_pushed,
    total_failed,
    total_templates_uploaded,
    total_templates_failed,
    machines: machineResults,
  } = resp.data
  const rows = (machineResults || []).map((m) => {
    const icon = m.success ? '✓' : '✗'
    const status = m.success
      ? `pushed ${m.pushed}, failed ${m.failed}, templates ${m.templates_uploaded || 0}${m.templates_failed ? ` (${m.templates_failed} failed)` : ''}`
      : `connection failed`
    return `<tr><td class="pr-2 text-left">${icon} ${m.machine}</td><td class="text-sm text-gray-500">${status}</td></tr>`
  }).join('')

  await Swal.fire({
    icon: total_failed === 0 && (total_templates_failed || 0) === 0 ? 'success' : 'warning',
    title: 'Push Complete',
    html: `<p class="text-sm text-gray-600 mb-2">Total pushed: <strong class="text-green-600">${total_pushed}</strong> &nbsp; Failed: <strong class="text-red-500">${total_failed}</strong></p>
           <p class="text-sm text-gray-600 mb-2">Templates uploaded: <strong class="text-green-600">${total_templates_uploaded || 0}</strong> &nbsp; Template failures: <strong class="text-red-500">${total_templates_failed || 0}</strong></p>
           <table class="w-full text-left text-sm">${rows}</table>`,
    confirmButtonText: 'OK',
  })
}

const pushUsersToDivice = async (machine) => {
  const confirm = await Swal.fire({
    icon: 'question',
    title: `Push Users to ${machine.MachineAlias || machine.IP}`,
    text: 'All local biometric users will be written to this device.',
    showCancelButton: true,
    confirmButtonText: 'Push Now',
  })

  if (!confirm.isConfirmed) return

  pushingUserIds.value = new Set([...pushingUserIds.value, machine.ID])

  const resp = await machineStore.pushUsers({ machine_id: machine.ID })

  pushingUserIds.value = new Set([...pushingUserIds.value].filter((id) => id !== machine.ID))

  if (!resp.success) {
    const msg = resp?.data?.response?.data?.message || 'Push failed'
    await Swal.fire({ icon: 'error', title: 'Push Failed', text: msg, confirmButtonText: 'OK' })
    return
  }

  const { total_pushed, total_failed, total_templates_uploaded, total_templates_failed } = resp.data
  await Swal.fire({
    icon: total_failed === 0 && (total_templates_failed || 0) === 0 ? 'success' : 'warning',
    title: 'Push Complete',
    html: `<p class="text-sm text-gray-600">Users pushed: <strong class="text-green-600">${total_pushed}</strong></p>
           <p class="text-sm text-gray-600">Failed: <strong class="text-red-500">${total_failed}</strong></p>
           <p class="text-sm text-gray-600">Templates uploaded: <strong class="text-green-600">${total_templates_uploaded || 0}</strong></p>
           <p class="text-sm text-gray-600">Template failures: <strong class="text-red-500">${total_templates_failed || 0}</strong></p>`,
    confirmButtonText: 'OK',
  })
}

onMounted(async () => {
  loadWebAutoFallbackSetting()
  await appSettingStore.loadSettings()
  await loadMachines()
  await loadAutoSyncStatus()
  applyMachineTimerSettings()
})

onUnmounted(() => {
  clearMachineTimers()
})
</script>

<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] p-5 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
      <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">Device Control Deck</p>
          <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white lg:text-4xl">Biometric Machines</h1>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200/90">
            Monitor machine health, run downloads, control background sync, and manage template operations from one screen.
          </p>

          <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
            <span
              class="inline-flex rounded-full px-3 py-1 font-medium ring-1 ring-inset"
              :class="autoSyncDaemonStatus.running
                ? 'bg-emerald-400/15 text-emerald-100 ring-emerald-300/30'
                : 'bg-rose-400/15 text-rose-100 ring-rose-300/30'"
            >
              {{ autoSyncDaemonStatus.running ? 'Daemon Running' : 'Daemon Stopped' }}
            </span>
            <span class="inline-flex rounded-full bg-white/10 px-3 py-1 font-medium text-slate-100 ring-1 ring-inset ring-white/10">
              Heartbeat: {{ formatLastAutoSync(autoSyncDaemonStatus.last_heartbeat) }}
            </span>
            <button
              type="button"
              @click="toggleWebAutoFallback"
              class="inline-flex min-h-11 items-center gap-3 rounded-2xl border px-4 py-2 text-left font-medium shadow-sm backdrop-blur-sm transition focus:outline-none focus:ring-2 focus:ring-white/30"
              :class="webAutoFallbackEnabled
                ? 'border-sky-300/30 bg-sky-400/20 text-sky-50 hover:bg-sky-400/30'
                : 'border-white/10 bg-slate-900/20 text-slate-100 hover:bg-white/15'"
              :title="'Enable browser fallback auto-download when daemon is stopped'"
            >
              <span
                class="flex h-8 w-8 items-center justify-center rounded-xl text-xs font-semibold"
                :class="webAutoFallbackEnabled
                  ? 'bg-sky-200/20 text-sky-50'
                  : 'bg-white/10 text-slate-200'"
              >
                {{ webAutoFallbackEnabled ? 'ON' : 'OFF' }}
              </span>
              <span class="flex flex-col leading-tight">
                <span class="text-sm font-semibold">Web Fallback</span>
                <span class="text-[11px] font-medium text-slate-200/80">{{ webAutoFallbackEnabled ? 'Browser backup sync active' : 'Use browser sync when daemon stops' }}</span>
              </span>
            </button>
            <button
              type="button"
              @click="openMachineTimerSettings"
              class="inline-flex min-h-11 items-center gap-3 rounded-2xl border border-white/10 bg-slate-900/20 px-4 py-2 text-left font-medium text-slate-100 shadow-sm backdrop-blur-sm transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/30"
              title="Configure machine page timer intervals"
            >
              <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10 text-sm font-semibold text-slate-100">
                ms
              </span>
              <span class="flex flex-col leading-tight">
                <span class="text-sm font-semibold">Timer Settings</span>
                <span class="text-[11px] font-medium text-slate-200/80">Adjust polling and fallback intervals</span>
              </span>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:min-w-[460px]">
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Total</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ machineStats.total }}</p>
            <p class="mt-1 text-xs text-slate-300">Registered devices</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Enabled</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ machineStats.enabled }}</p>
            <p class="mt-1 text-xs text-slate-300">Ready to connect</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Auto Sync</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ machineStats.autoEnabled }}</p>
            <p class="mt-1 text-xs text-slate-300">Machines with auto mode</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Active</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ machineStats.activeAuto }}</p>
            <p class="mt-1 text-xs text-slate-300">Auto sync healthy</p>
          </div>
        </div>
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
      <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03] lg:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Machine Directory</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Search by alias, IP, serial, firmware, or product type.</p>
          </div>
          <div class="text-sm text-slate-500 dark:text-slate-400">
            Showing <span class="font-semibold text-slate-900 dark:text-white">{{ filteredMachines.length }}</span> of {{ machineStats.total }} machines
          </div>
        </div>

        <div class="mt-4">
          <input
            v-model="search"
            type="text"
            placeholder="Search machine, firmware, IP, or serial number"
            class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-cyan-400 focus:bg-white dark:border-slate-700 dark:bg-slate-900/60 dark:text-white/90 dark:placeholder:text-slate-500"
          />
        </div>
      </div>

      <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03] lg:p-5">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Quick Actions</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Run global actions without leaving the page.</p>

        <div class="mt-4 grid gap-3">
          <button
            @click="pushUsersToAllMachines"
            type="button"
            :disabled="pushingUsers"
            class="flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-medium transition"
            :class="pushingUsers
              ? 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'
              : 'bg-cyan-600 text-white hover:bg-cyan-500'"
          >
            <span v-if="pushingUsers" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
            <RefreshIcon v-else class="h-4 w-4" />
            <span>{{ pushingUsers ? 'Pushing Users…' : 'Push Users to All Devices' }}</span>
          </button>

          <Button @click="openCreate" :className="'h-12 justify-center rounded-2xl whitespace-nowrap text-nowrap'" size="sm" variant="primary" :startIcon="PlusIcon">
            Add Machine
          </Button>
        </div>
      </div>
    </section>

    <section>
      <div v-if="filteredMachines.length" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <article
          v-for="machine in filteredMachines"
          :key="machine.ID"
          class="group overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-800 dark:bg-white/[0.03]"
        >
          <div class="border-b border-slate-200 bg-[linear-gradient(135deg,_rgba(14,165,233,0.08),_rgba(251,191,36,0.08))] p-5 dark:border-slate-800 dark:bg-[linear-gradient(135deg,_rgba(14,165,233,0.08),_rgba(34,197,94,0.05))]">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="truncate text-xl font-semibold text-slate-900 dark:text-white">{{ machine.MachineAlias || 'Unnamed Machine' }}</h3>
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                    :class="machine.Enabled
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                      : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                  >
                    {{ machine.Enabled ? 'Enabled' : 'Disabled' }}
                  </span>
                </div>

                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ machine.IP || 'No IP configured' }}<span v-if="machine.Port"> :{{ machine.Port }}</span></p>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                  <span class="rounded-full bg-white/80 px-2.5 py-1 font-medium text-slate-700 ring-1 ring-inset ring-slate-200 dark:bg-slate-800/90 dark:text-slate-200 dark:ring-slate-700">
                    {{ machine.ConnectType || 'Unknown Type' }}
                  </span>
                  <span
                    class="rounded-full px-2.5 py-1 font-medium"
                    :class="getMachineAutoSyncState(machine).className"
                  >
                    Auto {{ getMachineAutoSyncState(machine).label }}
                  </span>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <button
                  @click="openEdit(machine)"
                  type="button"
                  class="rounded-full bg-white/80 p-2 text-sky-700 ring-1 ring-inset ring-slate-200 transition hover:bg-white dark:bg-slate-800 dark:text-sky-300 dark:ring-slate-700"
                  title="Edit"
                >
                  <PencilIcon />
                </button>
                <button
                  @click="openDelete(machine)"
                  type="button"
                  class="rounded-full bg-white/80 p-2 text-rose-700 ring-1 ring-inset ring-slate-200 transition hover:bg-white dark:bg-slate-800 dark:text-rose-300 dark:ring-slate-700"
                  title="Delete"
                >
                  <TrashIcon />
                </button>
              </div>
            </div>
          </div>

          <div class="space-y-5 p-5">
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-900/60">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Machine No</p>
                <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ machine.MachineNumber ?? '-' }}</p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-900/60">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Serial</p>
                <p class="mt-1 truncate font-medium text-slate-800 dark:text-slate-100">{{ machine.sn || '-' }}</p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-900/60">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Product</p>
                <p class="mt-1 truncate font-medium text-slate-800 dark:text-slate-100">{{ machine.ProductType || '-' }}</p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-900/60">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Firmware</p>
                <p class="mt-1 truncate font-medium text-slate-800 dark:text-slate-100">{{ machine.FirmwareVersion || '-' }}</p>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Last Auto Sync</p>
                  <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">{{ formatLastAutoSync(machine.AutoDownloadLastSyncedAt) }}</p>
                </div>
                <div class="text-right">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Interval</p>
                  <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">{{ machine.AutoDownload ? `${machine.AutoDownloadInterval || 60}s` : 'Off' }}</p>
                </div>
              </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
              <button
                @click="connectMachine(machine)"
                type="button"
                :disabled="connectingIds.has(machine.ID) || syncingIds.has(machine.ID)"
                :title="connectingIds.has(machine.ID) ? 'Connecting…' : 'Test Connection'"
                class="col-span-2 flex h-11 items-center justify-center gap-2 rounded-2xl px-3 text-sm font-medium transition"
                :class="connectingIds.has(machine.ID)
                  ? 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800'
                  : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/30'"
              >
                <span v-if="connectingIds.has(machine.ID)" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-emerald-400 border-t-transparent"></span>
                <PlugInIcon v-else class="h-4 w-4" />
                <span>{{ connectingIds.has(machine.ID) ? 'Connecting…' : 'Connect' }}</span>
              </button>

              <button
                @click="syncAttendance(machine)"
                type="button"
                :disabled="syncingIds.has(machine.ID) || clearingLogIds.has(machine.ID) || connectingIds.has(machine.ID)"
                :title="syncingIds.has(machine.ID) ? 'Downloading…' : 'Download Attendance'"
                class="flex h-11 items-center justify-center gap-2 rounded-2xl px-3 text-sm font-medium transition"
                :class="syncingIds.has(machine.ID)
                  ? 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800'
                  : 'bg-sky-50 text-sky-700 hover:bg-sky-100 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/30'"
              >
                <span v-if="syncingIds.has(machine.ID)" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-sky-400 border-t-transparent"></span>
                <RefreshIcon v-else class="h-4 w-4" />
                <span>{{ syncingIds.has(machine.ID) ? 'Downloading…' : 'Download Logs' }}</span>
              </button>

              <button
                @click="clearAttendanceLogs(machine)"
                type="button"
                :disabled="clearingLogIds.has(machine.ID) || syncingIds.has(machine.ID) || connectingIds.has(machine.ID)"
                :title="clearingLogIds.has(machine.ID) ? 'Clearing logs…' : 'Clear Device Attendance Logs'"
                class="flex h-11 items-center justify-center gap-2 rounded-2xl px-3 text-sm font-medium transition"
                :class="clearingLogIds.has(machine.ID)
                  ? 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800'
                  : 'bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300 dark:hover:bg-rose-900/30'"
              >
                <span v-if="clearingLogIds.has(machine.ID)" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-rose-400 border-t-transparent"></span>
                <TrashIcon v-else class="h-4 w-4" />
                <span>{{ clearingLogIds.has(machine.ID) ? 'Clearing…' : 'Clear Logs' }}</span>
              </button>

              <button
                @click="toggleAutoDownload(machine)"
                type="button"
                :disabled="!machine.IP || !machine.Enabled || autoToggleIds.has(machine.ID)"
                :title="machine.AutoDownload ? 'Disable automatic background download' : 'Enable automatic background download'"
                class="flex h-11 items-center justify-center gap-2 rounded-2xl px-3 text-sm font-medium transition"
                :class="!machine.IP || !machine.Enabled || autoToggleIds.has(machine.ID)
                  ? 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800'
                  : machine.AutoDownload
                    ? 'bg-teal-50 text-teal-700 hover:bg-teal-100 dark:bg-teal-900/20 dark:text-teal-300 dark:hover:bg-teal-900/30'
                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800/80 dark:text-slate-200 dark:hover:bg-slate-700'"
              >
                <span>{{ autoToggleIds.has(machine.ID) ? 'Saving…' : machine.AutoDownload ? `Auto On (${machine.AutoDownloadInterval || 60}s)` : 'Auto Off' }}</span>
              </button>

              <button
                @click="pushUsersToDivice(machine)"
                type="button"
                :disabled="pushingUserIds.has(machine.ID) || syncingIds.has(machine.ID) || connectingIds.has(machine.ID)"
                :title="pushingUserIds.has(machine.ID) ? 'Pushing users…' : 'Push Users to Device'"
                class="flex h-11 items-center justify-center gap-2 rounded-2xl px-3 text-sm font-medium transition"
                :class="pushingUserIds.has(machine.ID)
                  ? 'cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800'
                  : 'bg-fuchsia-50 text-fuchsia-700 hover:bg-fuchsia-100 dark:bg-fuchsia-900/20 dark:text-fuchsia-300 dark:hover:bg-fuchsia-900/30'"
              >
                <span v-if="pushingUserIds.has(machine.ID)" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-fuchsia-400 border-t-transparent"></span>
                <RefreshIcon v-else class="h-4 w-4" />
                <span>{{ pushingUserIds.has(machine.ID) ? 'Pushing…' : 'Push Users' }}</span>
              </button>
            </div>
          </div>
        </article>
      </div>

      <div v-else class="rounded-[26px] border border-dashed border-slate-300 bg-white p-12 text-center shadow-sm dark:border-slate-700 dark:bg-white/[0.03]">
        <h3 class="text-xl font-semibold text-slate-900 dark:text-white">No machines found</h3>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Try changing the search term or add a new machine to start managing devices.</p>
        <div class="mt-5 flex justify-center">
          <Button @click="openCreate" :className="'h-11 rounded-2xl whitespace-nowrap text-nowrap'" size="sm" variant="primary" :startIcon="PlusIcon">
            Add Machine
          </Button>
        </div>
      </div>
    </section>

    <Modal v-if="isModalOpen" @close="isModalOpen = false">
      <template #body>
        <div class="no-scrollbar relative m-2 w-full max-w-[980px] max-h-[90vh] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-7">
          <h4 class="mb-4 text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ isEdit ? 'Update Machine' : 'Add Machine' }}
          </h4>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Machine Alias</label>
              <input v-model="form.MachineAlias" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Connect Type</label>
              <input v-model="form.ConnectType" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">IP Address</label>
              <input v-model="form.IP" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Serial Port</label>
              <input v-model="form.SerialPort" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Port</label>
              <input v-model.number="form.Port" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Baudrate</label>
              <input v-model.number="form.Baudrate" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Machine Number</label>
              <input v-model.number="form.MachineNumber" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Comm Password</label>
              <input v-model="form.CommPassword" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">UI Language</label>
              <input v-model="form.UILanguage" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Date Format</label>
              <input v-model="form.DateFormat" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">In/Out Record Warn</label>
              <input v-model.number="form.InOutRecordWarn" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Idle</label>
              <input v-model.number="form.Idle" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Voice</label>
              <input v-model.number="form.Voice" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Managers</label>
              <input v-model.number="form.managercount" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Users</label>
              <input v-model.number="form.usercount" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Finger Count</label>
              <input v-model.number="form.fingercount" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Secret Count</label>
              <input v-model.number="form.SecretCount" type="number" min="0" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Firmware Version</label>
              <input v-model="form.FirmwareVersion" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Product Type</label>
              <input v-model="form.ProductType" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Lock Control</label>
              <input v-model="form.LockControl" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Purpose</label>
              <input v-model="form.Purpose" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Produce Kind</label>
              <input v-model="form.ProduceKind" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Serial Number</label>
              <input v-model="form.sn" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Push Version</label>
              <input v-model="form.pushver" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Auto Download Interval (seconds)</label>
              <input v-model.number="form.AutoDownloadInterval" type="number" min="1" max="86400" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Auto Download User Filter</label>
              <select v-model="form.AutoDownloadUserFilter" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm">
                <option value="existing">Only Existing Local Users</option>
                <option value="all">All Logs (Include Unknown Users)</option>
              </select>
            </div>
          </div>

          <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input v-model="form.Enabled" type="checkbox" class="h-4 w-4" /> Enabled</label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input v-model="form.IsHost" type="checkbox" class="h-4 w-4" /> Is Host</label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input v-model="form.PhotoStamp" type="checkbox" class="h-4 w-4" /> Photo Stamp</label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input v-model="form.IsIfChangeConfigServer2" type="checkbox" class="h-4 w-4" /> Change Config Server 2</label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input v-model="form.IsAndroid" type="checkbox" class="h-4 w-4" /> Android Device</label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input v-model="form.AutoDownload" type="checkbox" class="h-4 w-4" /> Auto Download</label>
          </div>

          <div class="mt-5 flex items-center gap-3 lg:justify-end">
            <button @click="isModalOpen = false" type="button" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto">Close</button>
            <button @click="saveMachine" type="button" class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">Save</button>
          </div>
        </div>
      </template>
    </Modal>

    <ModalDelete
      v-if="isDeleteModal"
      head="Machine"
      :data="selectedMachine"
      :text="selectedMachine?.MachineAlias || selectedMachine?.sn || ''"
      @close="isDeleteModal = false"
      @delete="deleteMachine"
    />
  </div>
</template>
