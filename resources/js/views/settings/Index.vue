<script setup>
import DtrSettingsPreview from './DtrSettingsPreview.vue'
import DesktopUpdates from '@/views/settings/DesktopUpdates.vue'
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
  biometricDtrHeaderAddress,
  biometricDtrTemplate,
  companySchoolName,
  companySchoolLogo,
  companySchoolLogoPrintEnabled,
  biometricDtrSignatoryName,
  biometricDtrTimekeeperName,
  biometricDtrSignatorySignature,
  biometricDtrTimekeeperSignature,
  biometricDtrSignatoryUseDefault,
  biometricDtrTimekeeperUseDefault,
  biometricDtrSignatorySignatureEnabled,
  biometricDtrTimekeeperSignatureEnabled,
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
  biometric_dtr_header_address: '',
  biometric_dtr_template: 'default',
  biometric_dtr_template2_logo_position: 'left',
  biometric_dtr_template2_logo_left: 0,
  biometric_dtr_template2_logo_width: 15,
  biometric_dtr_template2_logo_height: 15,
  biometric_dtr_template2_logo_right: 4,
  biometric_dtr_template2_logo_top: 0,
  biometric_dtr_template2_logo_bottom: 3,
  biometric_dtr_template2_font_size: 8,
  biometric_dtr_template2_name_font_size: 10,
  biometric_dtr_template2_company_font_size: 8,
  biometric_dtr_template2_republic_font_size: 8,
  biometric_dtr_template2_department_font_size: 8,
  biometric_dtr_template2_address_font_size: 8,
  biometric_dtr_template2_title_font_size: 13,
  biometric_dtr_template2_approved_font_size: 10,
  biometric_dtr_template2_timekeeper_font_size: 10,
  biometric_dtr_template2_employee_font_size: 10,
  biometric_dtr_template2_month_font_size: 10,
  biometric_dtr_template2_table_header_font_size: 7,
  biometric_dtr_template2_table_content_font_size: 7,

  biometric_dtr_signatory_name: '',
  biometric_dtr_timekeeper_name: '',
  biometric_dtr_signatory_signature: '',
  biometric_dtr_timekeeper_signature: '',
  biometric_dtr_signatory_use_default: true,
  biometric_dtr_timekeeper_use_default: true,
  biometric_dtr_signatory_signature_enabled: false,
  biometric_dtr_timekeeper_signature_enabled: false,
  machine_auto_sync_status_timer_enabled: true,
  machine_auto_sync_status_timer_ms: 5000,
  machine_refresh_timer_enabled: true,
  machine_refresh_timer_ms: 5000,
  machine_web_auto_fallback_timer_enabled: true,
  machine_web_auto_fallback_timer_ms: 1000,
})

const uploadsInProgress = ref(0)
const uploadErrorMessage = (error) => {
  if (error?.response?.status === 413) return 'This image exceeds the upload size limit. Use an image smaller than 20 MB.'
  return error?.response?.data?.errors?.file?.[0] || error?.response?.data?.message || 'Image upload failed. Please try again.'
}
const logoLoadFailed = ref(false)
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
const activeSettingsTab = ref('general')

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
  uploadsInProgress.value += 1

  try {
    const response = await axios.post('/api/media/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    form.value.company_school_logo = response?.data?.path || ''
    form.value.company_school_logo_print_enabled = Boolean(form.value.company_school_logo)
    logoLoadFailed.value = false
    toastResult('Logo uploaded. Save settings to use it in reports.')
  } catch (error) {
    toastResult(uploadErrorMessage(error), 'error')
  } finally {
    uploadsInProgress.value -= 1
    event.target.value = ''
  }
}

const uploadSignature = async (event, field = 'biometric_dtr_signatory_signature') => {
  const files = event?.target?.files
  if (!files || files.length <= 0) {
    return
  }

  const formData = new FormData()
  formData.append('file', files[0])
  uploadsInProgress.value += 1

  try {
    const response = await axios.post('/api/media/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    form.value[field] = response?.data?.path || ''
    toastResult('Signature uploaded')
  } catch (error) {
    toastResult(uploadErrorMessage(error), 'error')
  } finally {
    uploadsInProgress.value -= 1
    event.target.value = ''
  }
}

const loadSettings = async () => {
  const resp = await appSettingStore.loadSettings(true)
  if (!resp.success) {
    toastResult('Unable to load settings', 'error')
    return
  }

  form.value.biometric_dtr_header_address = biometricDtrHeaderAddress.value || ''
  form.value.biometric_dtr_template = biometricDtrTemplate.value || 'default'
  form.value.biometric_dtr_template2_logo_position = appSettingStore.biometricDtrTemplate2LogoPosition === 'center' ? 'top' : (appSettingStore.biometricDtrTemplate2LogoPosition ?? 'left')
  form.value.biometric_dtr_template2_logo_left = appSettingStore.biometricDtrTemplate2LogoLeft ?? 0
  form.value.biometric_dtr_template2_logo_width = appSettingStore.biometricDtrTemplate2LogoWidth ?? 15
  form.value.biometric_dtr_template2_logo_height = appSettingStore.biometricDtrTemplate2LogoHeight ?? 15
  form.value.biometric_dtr_template2_logo_right = appSettingStore.biometricDtrTemplate2LogoRight ?? 4
  form.value.biometric_dtr_template2_logo_top = appSettingStore.biometricDtrTemplate2LogoTop ?? 0
  form.value.biometric_dtr_template2_logo_bottom = appSettingStore.biometricDtrTemplate2LogoBottom ?? 3
  form.value.biometric_dtr_template2_font_size = appSettingStore.biometricDtrTemplate2FontSize ?? 8
  form.value.biometric_dtr_template2_name_font_size = appSettingStore.biometricDtrTemplate2NameFontSize ?? 10
  form.value.biometric_dtr_template2_company_font_size = appSettingStore.biometricDtrTemplate2CompanyFontSize ?? 8
  form.value.biometric_dtr_template2_republic_font_size = appSettingStore.biometricDtrTemplate2RepublicFontSize ?? 8
  form.value.biometric_dtr_template2_department_font_size = appSettingStore.biometricDtrTemplate2DepartmentFontSize ?? 8
  form.value.biometric_dtr_template2_address_font_size = appSettingStore.biometricDtrTemplate2AddressFontSize ?? 8
  form.value.biometric_dtr_template2_title_font_size = appSettingStore.biometricDtrTemplate2TitleFontSize ?? 13
  form.value.biometric_dtr_template2_approved_font_size = appSettingStore.biometricDtrTemplate2ApprovedFontSize ?? 10
  form.value.biometric_dtr_template2_timekeeper_font_size = appSettingStore.biometricDtrTemplate2TimekeeperFontSize ?? 10
  form.value.biometric_dtr_template2_employee_font_size = appSettingStore.biometricDtrTemplate2EmployeeFontSize ?? 10
  form.value.biometric_dtr_template2_month_font_size = appSettingStore.biometricDtrTemplate2MonthFontSize ?? 10
  form.value.biometric_dtr_template2_table_header_font_size = appSettingStore.biometricDtrTemplate2TableHeaderFontSize ?? 7
  form.value.biometric_dtr_template2_table_content_font_size = appSettingStore.biometricDtrTemplate2TableContentFontSize ?? 7

  form.value.company_school_name = companySchoolName.value || 'Biometric System'
  form.value.company_school_logo = companySchoolLogo.value || ''
  form.value.company_school_logo_print_enabled = Boolean(companySchoolLogoPrintEnabled.value)
  form.value.biometric_dtr_signatory_name = biometricDtrSignatoryName.value ?? ''
  form.value.biometric_dtr_timekeeper_name = biometricDtrTimekeeperName.value ?? ''
  form.value.biometric_dtr_signatory_signature = biometricDtrSignatorySignature.value || ''
  form.value.biometric_dtr_timekeeper_signature = biometricDtrTimekeeperSignature.value || ''
  form.value.biometric_dtr_signatory_use_default = Boolean(biometricDtrSignatoryUseDefault.value)
  form.value.biometric_dtr_timekeeper_use_default = Boolean(biometricDtrTimekeeperUseDefault.value)
  form.value.biometric_dtr_signatory_signature_enabled = Boolean(biometricDtrSignatorySignatureEnabled.value)
  form.value.biometric_dtr_timekeeper_signature_enabled = Boolean(biometricDtrTimekeeperSignatureEnabled.value)
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
    biometric_dtr_header_address: form.value.biometric_dtr_header_address,
    biometric_dtr_template: form.value.biometric_dtr_template,
    biometric_dtr_template2_logo_position: form.value.biometric_dtr_template2_logo_position,
    biometric_dtr_template2_logo_left: form.value.biometric_dtr_template2_logo_left,
    biometric_dtr_template2_logo_width: form.value.biometric_dtr_template2_logo_width,
    biometric_dtr_template2_logo_height: form.value.biometric_dtr_template2_logo_height,
    biometric_dtr_template2_logo_right: form.value.biometric_dtr_template2_logo_right,
    biometric_dtr_template2_logo_top: form.value.biometric_dtr_template2_logo_top,
    biometric_dtr_template2_logo_bottom: form.value.biometric_dtr_template2_logo_bottom,
    biometric_dtr_template2_font_size: form.value.biometric_dtr_template2_font_size,
    biometric_dtr_template2_name_font_size: form.value.biometric_dtr_template2_name_font_size,
    biometric_dtr_template2_company_font_size: form.value.biometric_dtr_template2_company_font_size,
    biometric_dtr_template2_republic_font_size: form.value.biometric_dtr_template2_republic_font_size,
    biometric_dtr_template2_department_font_size: form.value.biometric_dtr_template2_department_font_size,
    biometric_dtr_template2_address_font_size: form.value.biometric_dtr_template2_address_font_size,
    biometric_dtr_template2_title_font_size: form.value.biometric_dtr_template2_title_font_size,
    biometric_dtr_template2_approved_font_size: form.value.biometric_dtr_template2_approved_font_size,
    biometric_dtr_template2_timekeeper_font_size: form.value.biometric_dtr_template2_timekeeper_font_size,
    biometric_dtr_template2_employee_font_size: form.value.biometric_dtr_template2_employee_font_size,
    biometric_dtr_template2_month_font_size: form.value.biometric_dtr_template2_month_font_size,
    biometric_dtr_template2_table_header_font_size: form.value.biometric_dtr_template2_table_header_font_size,
    biometric_dtr_template2_table_content_font_size: form.value.biometric_dtr_template2_table_content_font_size,

    biometric_dtr_signatory_name: form.value.biometric_dtr_signatory_name,
    biometric_dtr_timekeeper_name: form.value.biometric_dtr_timekeeper_name,
    biometric_dtr_signatory_signature: form.value.biometric_dtr_signatory_signature,
    biometric_dtr_timekeeper_signature: form.value.biometric_dtr_timekeeper_signature,
    biometric_dtr_signatory_use_default: Boolean(form.value.biometric_dtr_signatory_use_default),
    biometric_dtr_timekeeper_use_default: Boolean(form.value.biometric_dtr_timekeeper_use_default),
    biometric_dtr_signatory_signature_enabled: Boolean(form.value.biometric_dtr_signatory_signature_enabled),
    biometric_dtr_timekeeper_signature_enabled: Boolean(form.value.biometric_dtr_timekeeper_signature_enabled),
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

  form.value.biometric_dtr_header_address = biometricDtrHeaderAddress.value || ''
  form.value.biometric_dtr_template = biometricDtrTemplate.value || 'default'
  form.value.biometric_dtr_template2_logo_position = appSettingStore.biometricDtrTemplate2LogoPosition === 'center' ? 'top' : (appSettingStore.biometricDtrTemplate2LogoPosition ?? 'left')
  form.value.biometric_dtr_template2_logo_left = appSettingStore.biometricDtrTemplate2LogoLeft ?? 0
  form.value.biometric_dtr_template2_logo_width = appSettingStore.biometricDtrTemplate2LogoWidth ?? 15
  form.value.biometric_dtr_template2_logo_height = appSettingStore.biometricDtrTemplate2LogoHeight ?? 15
  form.value.biometric_dtr_template2_logo_right = appSettingStore.biometricDtrTemplate2LogoRight ?? 4
  form.value.biometric_dtr_template2_logo_top = appSettingStore.biometricDtrTemplate2LogoTop ?? 0
  form.value.biometric_dtr_template2_logo_bottom = appSettingStore.biometricDtrTemplate2LogoBottom ?? 3
  form.value.biometric_dtr_template2_font_size = appSettingStore.biometricDtrTemplate2FontSize ?? 8
  form.value.biometric_dtr_template2_name_font_size = appSettingStore.biometricDtrTemplate2NameFontSize ?? 10
  form.value.biometric_dtr_template2_company_font_size = appSettingStore.biometricDtrTemplate2CompanyFontSize ?? 8
  form.value.biometric_dtr_template2_republic_font_size = appSettingStore.biometricDtrTemplate2RepublicFontSize ?? 8
  form.value.biometric_dtr_template2_department_font_size = appSettingStore.biometricDtrTemplate2DepartmentFontSize ?? 8
  form.value.biometric_dtr_template2_address_font_size = appSettingStore.biometricDtrTemplate2AddressFontSize ?? 8
  form.value.biometric_dtr_template2_title_font_size = appSettingStore.biometricDtrTemplate2TitleFontSize ?? 13
  form.value.biometric_dtr_template2_approved_font_size = appSettingStore.biometricDtrTemplate2ApprovedFontSize ?? 10
  form.value.biometric_dtr_template2_timekeeper_font_size = appSettingStore.biometricDtrTemplate2TimekeeperFontSize ?? 10
  form.value.biometric_dtr_template2_employee_font_size = appSettingStore.biometricDtrTemplate2EmployeeFontSize ?? 10
  form.value.biometric_dtr_template2_month_font_size = appSettingStore.biometricDtrTemplate2MonthFontSize ?? 10
  form.value.biometric_dtr_template2_table_header_font_size = appSettingStore.biometricDtrTemplate2TableHeaderFontSize ?? 7
  form.value.biometric_dtr_template2_table_content_font_size = appSettingStore.biometricDtrTemplate2TableContentFontSize ?? 7

  form.value.company_school_name = companySchoolName.value || form.value.company_school_name
  form.value.company_school_logo = companySchoolLogo.value || form.value.company_school_logo
  form.value.company_school_logo_print_enabled = Boolean(companySchoolLogoPrintEnabled.value)
  form.value.biometric_dtr_signatory_name = biometricDtrSignatoryName.value ?? form.value.biometric_dtr_signatory_name
  form.value.biometric_dtr_timekeeper_name = biometricDtrTimekeeperName.value ?? form.value.biometric_dtr_timekeeper_name
  form.value.biometric_dtr_signatory_signature = biometricDtrSignatorySignature.value || form.value.biometric_dtr_signatory_signature
  form.value.biometric_dtr_timekeeper_signature = biometricDtrTimekeeperSignature.value || form.value.biometric_dtr_timekeeper_signature
  form.value.biometric_dtr_signatory_use_default = Boolean(biometricDtrSignatoryUseDefault.value)
  form.value.biometric_dtr_timekeeper_use_default = Boolean(biometricDtrTimekeeperUseDefault.value)
  form.value.biometric_dtr_signatory_signature_enabled = Boolean(biometricDtrSignatorySignatureEnabled.value)
  form.value.biometric_dtr_timekeeper_signature_enabled = Boolean(biometricDtrTimekeeperSignatureEnabled.value)
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
  <div class="space-y-3">
    <DesktopUpdates />


    <div class="admin-page-header dark -ml-4 -mt-4 md:-ml-6 md:-mt-6 sticky p-2 top-0 z-20 -mr-4 border pb-3 shadow-sm md:-mr-6">
      <SettingsNav class="mb-2" />
      <section class="px-2 pb-1 text-white">
      <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div class="max-w-3xl">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">Settings</p>
          <!-- <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white lg:text-4xl">Settings</h1> -->
          <!-- <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200/90">
            Configure company branding and machine page timer behavior used across reporting and auto-sync workflows.
          </p> -->
        </div>

        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4 lg:w-auto lg:min-w-[440px]">
          <div class="rounded-lg border border-white/10 bg-white/10 px-2.5 py-1.5 backdrop-blur-sm">
            <p class="text-[10px] uppercase tracking-[0.18em] text-slate-300">Timers</p>
            <div class="mt-0.5 flex items-baseline gap-1.5">
              <p class="text-lg font-semibold leading-none text-white">3</p>
              <p class="truncate text-[10px] text-slate-300">Configurable</p>
            </div>
          </div>
          <div class="rounded-lg border border-white/10 bg-white/10 px-2.5 py-1.5 backdrop-blur-sm">
            <p class="text-[10px] uppercase tracking-[0.18em] text-slate-300">Enabled</p>
            <div class="mt-0.5 flex items-baseline gap-1.5">
              <p class="text-lg font-semibold leading-none text-white">{{ Number(form.machine_auto_sync_status_timer_enabled) + Number(form.machine_refresh_timer_enabled) + Number(form.machine_web_auto_fallback_timer_enabled) }}</p>
              <p class="truncate text-[10px] text-slate-300">Active</p>
            </div>
          </div>
          <div class="rounded-lg border border-white/10 bg-white/10 px-2.5 py-1.5 backdrop-blur-sm">
            <p class="text-[10px] uppercase tracking-[0.18em] text-slate-300">Disabled</p>
            <div class="mt-0.5 flex items-baseline gap-1.5">
              <p class="text-lg font-semibold leading-none text-white">{{ 3 - (Number(form.machine_auto_sync_status_timer_enabled) + Number(form.machine_refresh_timer_enabled) + Number(form.machine_web_auto_fallback_timer_enabled)) }}</p>
              <p class="truncate text-[10px] text-slate-300">Paused</p>
            </div>
          </div>
          <div class="rounded-lg border border-white/10 bg-white/10 px-2.5 py-1.5 backdrop-blur-sm">
            <p class="text-[10px] uppercase tracking-[0.18em] text-slate-300">Save State</p>
            <div class="mt-0.5 flex items-baseline gap-1.5">
              <p class="truncate text-sm font-semibold leading-none text-white">{{ saving ? 'Saving...' : 'Ready' }}</p>
              <p class="truncate text-[10px] text-slate-300">Persistence</p>
            </div>
          </div>
        </div>
      </div>
    </section>
    </div>

    <div class="overflow-x-auto rounded-md border border-slate-200 bg-slate-50/70 p-1 dark:border-slate-700 dark:bg-slate-900/40">
      <div class="flex min-w-max gap-1" role="tablist" aria-label="General settings sections">
        <button
          type="button"
          role="tab"
          :aria-selected="activeSettingsTab === 'general'"
          @click="activeSettingsTab = 'general'"
          class="inline-flex h-8 items-center justify-center rounded px-2.5 text-xs font-semibold transition"
          :class="activeSettingsTab === 'general' ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-300' : 'bg-white text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
        >
          General
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeSettingsTab === 'license'"
          @click="activeSettingsTab = 'license'"
          class="inline-flex h-8 items-center justify-center rounded px-2.5 text-xs font-semibold transition"
          :class="activeSettingsTab === 'license' ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-white text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
        >
          License
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeSettingsTab === 'timers'"
          @click="activeSettingsTab = 'timers'"
          class="inline-flex h-8 items-center justify-center rounded px-2.5 text-xs font-semibold transition"
          :class="activeSettingsTab === 'timers' ? 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-white text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
        >
          Machine Page Timers
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeSettingsTab === 'notes'"
          @click="activeSettingsTab = 'notes'"
          class="inline-flex h-8 items-center justify-center rounded px-2.5 text-xs font-semibold transition"
          :class="activeSettingsTab === 'notes' ? 'bg-indigo-100 text-indigo-900 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-white text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
        >
          Quick Notes
        </button>
      </div>
    </div>

    <section v-if="activeSettingsTab === 'license'" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-col gap-2 border-b border-slate-100 px-3 py-2.5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-base font-semibold text-slate-800 dark:text-white">License</h2>
          <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Current activation status for this installation.</p>
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

      <div class="grid gap-2 px-3 py-3 sm:grid-cols-3">
        <div class="rounded-md border border-slate-100 bg-slate-50/70 p-2.5 dark:border-slate-700 dark:bg-slate-900/40">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Plan Type</p>
          <p class="mt-1.5 text-sm font-semibold text-slate-800 dark:text-white">
            {{ licenseStatus === 'licensed' ? 'Paid License' : licenseStatus === 'trial' ? 'Free Trial (7 days)' : 'Trial Expired' }}
          </p>
        </div>

        <div class="rounded-md border border-slate-100 bg-slate-50/70 p-2.5 dark:border-slate-700 dark:bg-slate-900/40">
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

        <div class="rounded-md border border-slate-100 bg-slate-50/70 p-2.5 dark:border-slate-700 dark:bg-slate-900/40">
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

      <div v-if="licenseStatus === 'licensed'" class="flex flex-col gap-2 border-t border-slate-100 px-3 py-2.5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-xs text-slate-400 dark:text-slate-500">License Key</p>
          <p class="mt-0.5 font-mono text-sm font-semibold tracking-widest text-slate-700 dark:text-slate-200">{{ maskedKey }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="handleReplaceLicense"
            :disabled="replacing"
            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-3 text-xs font-semibold text-sky-600 transition hover:bg-sky-100 disabled:opacity-60 dark:border-sky-800/40 dark:bg-sky-950/20 dark:text-sky-400 dark:hover:bg-sky-950/40"
          >
            {{ replacing ? 'Replacing…' : 'Replace' }}
          </button>
          <button
            @click="handleDeactivateLicense"
            :disabled="deactivating"
            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-600 transition hover:bg-rose-100 disabled:opacity-60 dark:border-rose-800/40 dark:bg-rose-950/20 dark:text-rose-400 dark:hover:bg-rose-950/40"
          >
            {{ deactivating ? 'Removing…' : 'Remove' }}
          </button>
        </div>
      </div>

      <div v-else class="border-t border-slate-100 px-3 py-2.5 dark:border-slate-800">
        <p class="text-xs text-slate-500 dark:text-slate-400">
          <template v-if="licenseStatus === 'trial'">Upgrade to a paid license to continue after your trial ends.</template>
          <template v-else>Your trial has expired. Enter a license key to continue.</template>
          <router-link to="/license" class="ml-1 font-medium text-sky-600 hover:underline dark:text-sky-400">Activate License →</router-link>
        </p>
      </div>
    </section>

    <section :class="activeSettingsTab === 'general' ? 'grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]' : 'grid gap-2.5'">
      <div v-if="activeSettingsTab === 'general' || activeSettingsTab === 'timers'" class="min-w-0 rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <template v-if="activeSettingsTab === 'general'">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">General</h2>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Display name appears in generated printable reports.</p>

        <div class="mt-2.5">
          <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Company / School Name</label>
          <input
            v-model.trim="form.company_school_name"
            type="text"
            placeholder="Enter company or school name"
            class="h-9 w-full rounded-md border border-slate-300 bg-transparent px-2.5 py-1.5 text-xs text-slate-800 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:text-white/90"
          />
        </div>

        <div class="mt-5 space-y-4 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
          <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">DTR print layout</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Choose your template and customize the printed header. Save settings to apply changes.</p>
            <p v-if="uploadsInProgress > 0" role="status" class="mt-2 text-xs text-sky-600 dark:text-sky-400">Uploading image, please wait...</p>
          </div>
          <div>
            <label for="biometric-dtr-template" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Print template</label>
            <select id="biometric-dtr-template" v-model="form.biometric_dtr_template" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
              <option value="default">Original template (default)</option>
              <option value="template2">Template 2 - two forms per A4 landscape sheet</option>
            </select>
          </div>
          <div class="space-y-3">
            <div>
              <label for="dtr-header-address" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Office address</label>
              <input id="dtr-header-address" v-model.trim="form.biometric_dtr_header_address" type="text" maxlength="255" placeholder="e.g. Pawing, Palo, Leyte" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white" />
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Template 2 uses each employee's department name automatically, followed by this address.</p>
          </div>
          <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
            <label for="dtr-logo" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Header logo</label>
            <input id="dtr-logo" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" @change="uploadLogo" class="block w-full rounded-md border border-slate-300 p-2 text-xs text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-sky-50 file:px-3 file:py-1.5 file:font-medium file:text-sky-700 dark:border-slate-600 dark:text-slate-300" />
            <div v-if="form.company_school_logo" class="mt-3 flex items-center gap-4 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40">
              <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white p-2">
                <img :key="form.company_school_logo" :src="form.company_school_logo" alt="Uploaded logo preview" class="max-h-full max-w-full object-contain" @load="logoLoadFailed = false" @error="logoLoadFailed = true" />
              </div>
              <div class="min-w-0">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Logo preview</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ form.company_school_logo_print_enabled ? 'Included in printed reports' : 'Saved logo; hidden in printed reports' }}</p>
              </div>
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
              <input v-model="form.company_school_logo_print_enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-sky-600" />
              Show logo in printed reports
            </label>
            <p v-if="!form.company_school_logo" class="mt-1 text-xs text-slate-500">Upload a logo to include it in the header.</p>
            <p v-else-if="!form.company_school_logo_print_enabled" class="mt-1 text-xs text-amber-600 dark:text-amber-400">Logo is hidden. Enable the checkbox to print it.</p>
            <p v-if="logoLoadFailed" role="alert" class="mt-2 text-xs text-red-600 dark:text-red-400">The saved logo could not be loaded. Upload the image again and save settings.</p>
          </div>

        </div>

        <div class="mt-2.5 rounded-md border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-700 dark:bg-slate-900/40">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Biometric DTR Signatory</h3>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Enter the default signatory name and optional e-signature used in printable biometric DTR forms.
              </p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
              <input v-model="form.biometric_dtr_signatory_use_default" type="checkbox" class="h-3.5 w-3.5" />
              Use as default in Biometric DTR
            </label>
          </div>

          <div class="mt-2.5 grid gap-2.5 lg:grid-cols-[1fr_1fr]">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Signatory Name (optional)</label>
              <input
                v-model.trim="form.biometric_dtr_signatory_name"
                type="text"
                placeholder="Optional"
                :disabled="!form.biometric_dtr_signatory_use_default"
                class="h-9 w-full rounded-md border border-slate-300 bg-transparent px-2.5 py-1.5 text-xs text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:text-white/90"
              />
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">This name prints above the signature line when the default is enabled.</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">E-Signature</label>
              <input
                type="file"
                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                @change="uploadSignature"
                :disabled="!form.biometric_dtr_signatory_use_default"
                class="block h-9 w-full rounded-md border border-slate-300 bg-transparent px-2 py-1 text-xs text-slate-700 file:mr-2 file:rounded file:border-0 file:bg-sky-100 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-sky-700 hover:file:bg-sky-200 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:file:bg-sky-900/30 dark:file:text-sky-300"
              />
              <div v-if="form.biometric_dtr_signatory_signature" class="mt-2 flex items-center gap-2 rounded-md border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-950/30">
                <img :src="form.biometric_dtr_signatory_signature" alt="Signatory signature" class="h-10 max-w-[140px] object-contain" />
                <div class="min-w-0">
                  <p class="text-sm font-medium text-slate-800 dark:text-white">Signature ready</p>
                  </div>
              </div>
            </div>
          </div>

          <div class="mt-2.5 flex items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-950/30 dark:text-slate-400">
            <div>
              <p class="font-semibold text-slate-700 dark:text-slate-300">Print Preview</p>
              <p class="mt-1">
                {{ form.biometric_dtr_signatory_use_default ? (form.biometric_dtr_signatory_name || 'Blank') : 'Manual / default off' }}
              </p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
              <input v-model="form.biometric_dtr_signatory_signature_enabled" type="checkbox" class="h-3.5 w-3.5" />
              Use e-signature in Biometric DTR
            </label>
          </div>
        </div>

        <div class="mt-2.5 rounded-md border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-700 dark:bg-slate-900/40">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Biometric DTR Time Keeper</h3>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Enter the default time keeper name and optional e-signature used in Template 2.
              </p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
              <input v-model="form.biometric_dtr_timekeeper_use_default" type="checkbox" class="h-3.5 w-3.5" />
              Use as default in Template 2
            </label>
          </div>

          <div class="mt-2.5 grid gap-2.5 lg:grid-cols-[1fr_1fr]">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Time Keeper Name (optional)</label>
              <input
                v-model.trim="form.biometric_dtr_timekeeper_name"
                type="text"
                placeholder="Optional"
                :disabled="!form.biometric_dtr_timekeeper_use_default"
                class="h-9 w-full rounded-md border border-slate-300 bg-transparent px-2.5 py-1.5 text-xs text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:text-white/90"
              />
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">This name prints above the signature line when the default is enabled.</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">E-Signature</label>
              <input
                type="file"
                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                @change="uploadSignature($event, 'biometric_dtr_timekeeper_signature')"
                :disabled="!form.biometric_dtr_timekeeper_use_default"
                class="block h-9 w-full rounded-md border border-slate-300 bg-transparent px-2 py-1 text-xs text-slate-700 file:mr-2 file:rounded file:border-0 file:bg-sky-100 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-sky-700 hover:file:bg-sky-200 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:file:bg-sky-900/30 dark:file:text-sky-300"
              />
              <div v-if="form.biometric_dtr_timekeeper_signature" class="mt-2 flex items-center gap-2 rounded-md border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-950/30">
                <img :src="form.biometric_dtr_timekeeper_signature" alt="Time keeper signature" class="h-10 max-w-[140px] object-contain" />
                <div class="min-w-0">
                  <p class="text-sm font-medium text-slate-800 dark:text-white">Signature ready</p>
                  </div>
              </div>
            </div>
          </div>

          <div class="mt-2.5 flex items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-950/30 dark:text-slate-400">
            <div>
              <p class="font-semibold text-slate-700 dark:text-slate-300">Print Preview</p>
              <p class="mt-1">
                {{ form.biometric_dtr_timekeeper_use_default ? (form.biometric_dtr_timekeeper_name || 'Blank') : 'Manual / default off' }}
              </p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
              <input v-model="form.biometric_dtr_timekeeper_signature_enabled" type="checkbox" class="h-3.5 w-3.5" />
              Use e-signature in Template 2
            </label>
          </div>
        </div>

        </template>

        <div v-if="activeSettingsTab === 'timers'">
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Machine Page Timers</h2>
          <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Set interval in milliseconds. Disable a timer to turn that background task off.</p>

          <div class="mt-2.5 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="min-w-full text-xs">
              <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                  <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Timer</th>
                  <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Enabled</th>
                  <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Interval (ms)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td class="px-3 py-2 text-slate-700 dark:text-slate-200">Daemon Status Poll</td>
                  <td class="px-3 py-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                      <input v-model="form.machine_auto_sync_status_timer_enabled" type="checkbox" class="h-3.5 w-3.5" />
                      <span :class="form.machine_auto_sync_status_timer_enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'">
                        {{ form.machine_auto_sync_status_timer_enabled ? 'On' : 'Off' }}
                      </span>
                    </label>
                  </td>
                  <td class="px-3 py-2">
                    <input
                      v-model.number="form.machine_auto_sync_status_timer_ms"
                      type="number"
                      min="250"
                      max="300000"
                      :disabled="!form.machine_auto_sync_status_timer_enabled"
                      class="h-8 w-full rounded border border-slate-300 bg-transparent px-2.5 text-xs text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-white/90"
                    />
                  </td>
                </tr>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td class="px-3 py-2 text-slate-700 dark:text-slate-200">Machine List Refresh</td>
                  <td class="px-3 py-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                      <input v-model="form.machine_refresh_timer_enabled" type="checkbox" class="h-3.5 w-3.5" />
                      <span :class="form.machine_refresh_timer_enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'">
                        {{ form.machine_refresh_timer_enabled ? 'On' : 'Off' }}
                      </span>
                    </label>
                  </td>
                  <td class="px-3 py-2">
                    <input
                      v-model.number="form.machine_refresh_timer_ms"
                      type="number"
                      min="250"
                      max="300000"
                      :disabled="!form.machine_refresh_timer_enabled"
                      class="h-8 w-full rounded border border-slate-300 bg-transparent px-2.5 text-xs text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-white/90"
                    />
                  </td>
                </tr>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td class="px-3 py-2 text-slate-700 dark:text-slate-200">Web Auto Fallback Cycle</td>
                  <td class="px-3 py-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                      <input v-model="form.machine_web_auto_fallback_timer_enabled" type="checkbox" class="h-3.5 w-3.5" />
                      <span :class="form.machine_web_auto_fallback_timer_enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500'">
                        {{ form.machine_web_auto_fallback_timer_enabled ? 'On' : 'Off' }}
                      </span>
                    </label>
                  </td>
                  <td class="px-3 py-2">
                    <input
                      v-model.number="form.machine_web_auto_fallback_timer_ms"
                      type="number"
                      min="250"
                      max="300000"
                      :disabled="!form.machine_web_auto_fallback_timer_enabled"
                      class="h-8 w-full rounded border border-slate-300 bg-transparent px-2.5 text-xs text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-white/90"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mt-2.5 flex justify-end">
          <Button @click="saveSettings" size="sm" variant="primary" :className="'h-9 bg-sky-500 hover:bg-sky-600 text-white'" :disabled="saving || uploadsInProgress > 0">
            {{ saving ? 'Saving...' : 'Save Settings' }}
          </Button>
        </div>
      </div>

      <DtrSettingsPreview v-if="activeSettingsTab === 'general'" :form="form" @update-field="(key, value) => form[key] = value" />

      <SettingsSidebar
        v-if="activeSettingsTab === 'notes'"
        class="xl:col-span-2"
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
