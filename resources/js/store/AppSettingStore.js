import { defineStore } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto'
import { useAuthStore } from './AuthStore'
import { isDesktop } from '../utils/printPreview'

const desktopMediaPath = (path) => isDesktop() && typeof path === 'string'
  ? path.replace(/^\/(?:storage\/)?uploads\//, '/desktop-uploads/')
  : path

export const useAppSettingStore = defineStore('appSettings', {
  id: 'appSettings',
  persist: {
    key: 'app-settings-store',
    storage: {
      getItem: (key) => {
        const raw = localStorage.getItem(key)
        return raw ? decrypt(raw) : null
      },
      setItem: (key, value) => {
        localStorage.setItem(key, encrypt(value))
      },
      removeItem: (key) => {
        localStorage.removeItem(key)
      },
    },
  },
  state: () => ({
    companySchoolName: 'Biometric System',
    companySchoolLogo: '',
    companySchoolLogoPrintEnabled: false,
    biometricDtrHeaderAddress: '',
    biometricDtrTemplate: 'default',
    biometricDtrTemplate2LogoPosition: 'left',
    biometricDtrTemplate2LogoLeft: 0,
    biometricDtrTemplate2LogoWidth: 15,
    biometricDtrTemplate2LogoHeight: 15,
    biometricDtrTemplate2LogoRight: 4,
    biometricDtrTemplate2LogoTop: 0,
    biometricDtrTemplate2LogoBottom: 3,
    biometricDtrTemplate2FontSize: 8,
    biometricDtrTemplate2NameFontSize: 10,
    biometricDtrTemplate2CompanyFontSize: 8,
    biometricDtrTemplate2RepublicFontSize: 8,
    biometricDtrTemplate2DepartmentFontSize: 8,
    biometricDtrTemplate2AddressFontSize: 8,
    biometricDtrTemplate2TitleFontSize: 13,
    biometricDtrTemplate2ApprovedFontSize: 10,
    biometricDtrTemplate2TimekeeperFontSize: 10,
    biometricDtrTemplate2EmployeeFontSize: 10,
    biometricDtrTemplate2MonthFontSize: 10,
    biometricDtrTemplate2TableHeaderFontSize: 7,
    biometricDtrTemplate2TableContentFontSize: 7,

    biometricDtrSignatoryName: '',
    biometricDtrTimekeeperName: '',
    biometricDtrSignatorySignature: '',
    biometricDtrTimekeeperSignature: '',
    biometricDtrSignatoryUseDefault: true,
    biometricDtrTimekeeperUseDefault: true,
    biometricDtrSignatorySignatureEnabled: false,
    biometricDtrTimekeeperSignatureEnabled: false,
    machineAutoSyncStatusTimerEnabled: true,
    machineAutoSyncStatusTimerMs: 5000,
    machineRefreshTimerEnabled: true,
    machineRefreshTimerMs: 5000,
    machineWebAutoFallbackTimerEnabled: true,
    machineWebAutoFallbackTimerMs: 1000,
    loaded: false,
  }),
  actions: {
    async loadSettings(force = false) {
      const app = this
      const auth = useAuthStore()

      app.companySchoolLogo = desktopMediaPath(app.companySchoolLogo)
      app.biometricDtrSignatorySignature = desktopMediaPath(app.biometricDtrSignatorySignature)
      app.biometricDtrTimekeeperSignature = desktopMediaPath(app.biometricDtrTimekeeperSignature)

      if (app.loaded && !force) {
        return {
          success: true,
          data: {
            settings: {
              company_school_name: app.companySchoolName,
              company_school_logo: app.companySchoolLogo,
              company_school_logo_print_enabled: app.companySchoolLogoPrintEnabled,
              biometric_dtr_header_address: app.biometricDtrHeaderAddress,
              biometric_dtr_template: app.biometricDtrTemplate,
              biometric_dtr_template2_logo_position: app.biometricDtrTemplate2LogoPosition,
              biometric_dtr_template2_logo_left: app.biometricDtrTemplate2LogoLeft,
              biometric_dtr_template2_logo_width: app.biometricDtrTemplate2LogoWidth,
              biometric_dtr_template2_logo_height: app.biometricDtrTemplate2LogoHeight,
              biometric_dtr_template2_logo_right: app.biometricDtrTemplate2LogoRight,
              biometric_dtr_template2_logo_top: app.biometricDtrTemplate2LogoTop,
              biometric_dtr_template2_logo_bottom: app.biometricDtrTemplate2LogoBottom,
              biometric_dtr_template2_font_size: app.biometricDtrTemplate2FontSize,
              biometric_dtr_template2_name_font_size: app.biometricDtrTemplate2NameFontSize,
              biometric_dtr_template2_company_font_size: app.biometricDtrTemplate2CompanyFontSize,
              biometric_dtr_template2_republic_font_size: app.biometricDtrTemplate2RepublicFontSize,
              biometric_dtr_template2_department_font_size: app.biometricDtrTemplate2DepartmentFontSize,
              biometric_dtr_template2_address_font_size: app.biometricDtrTemplate2AddressFontSize,
              biometric_dtr_template2_title_font_size: app.biometricDtrTemplate2TitleFontSize,
              biometric_dtr_template2_approved_font_size: app.biometricDtrTemplate2ApprovedFontSize,
              biometric_dtr_template2_timekeeper_font_size: app.biometricDtrTemplate2TimekeeperFontSize,
              biometric_dtr_template2_employee_font_size: app.biometricDtrTemplate2EmployeeFontSize,
              biometric_dtr_template2_month_font_size: app.biometricDtrTemplate2MonthFontSize,
              biometric_dtr_template2_table_header_font_size: app.biometricDtrTemplate2TableHeaderFontSize,
              biometric_dtr_template2_table_content_font_size: app.biometricDtrTemplate2TableContentFontSize,

              biometric_dtr_signatory_name: app.biometricDtrSignatoryName,
              biometric_dtr_timekeeper_name: app.biometricDtrTimekeeperName,
              biometric_dtr_signatory_signature: app.biometricDtrSignatorySignature,
              biometric_dtr_timekeeper_signature: app.biometricDtrTimekeeperSignature,
              biometric_dtr_signatory_use_default: app.biometricDtrSignatoryUseDefault,
              biometric_dtr_timekeeper_use_default: app.biometricDtrTimekeeperUseDefault,
              biometric_dtr_signatory_signature_enabled: app.biometricDtrSignatorySignatureEnabled,
              biometric_dtr_timekeeper_signature_enabled: app.biometricDtrTimekeeperSignatureEnabled,
              machine_auto_sync_status_timer_enabled: app.machineAutoSyncStatusTimerEnabled,
              machine_auto_sync_status_timer_ms: app.machineAutoSyncStatusTimerMs,
              machine_refresh_timer_enabled: app.machineRefreshTimerEnabled,
              machine_refresh_timer_ms: app.machineRefreshTimerMs,
              machine_web_auto_fallback_timer_enabled: app.machineWebAutoFallbackTimerEnabled,
              machine_web_auto_fallback_timer_ms: app.machineWebAutoFallbackTimerMs,
            },
          },
        }
      }

      return await axios.post('/api/settings').then(function (resp) {
        app.companySchoolName = resp?.data?.settings?.company_school_name || 'Biometric System'
        app.companySchoolLogo = resp?.data?.settings?.company_school_logo || ''
        app.companySchoolLogoPrintEnabled = Boolean(resp?.data?.settings?.company_school_logo_print_enabled ?? false)
        app.biometricDtrHeaderAddress = resp?.data?.settings?.biometric_dtr_header_address ?? ''
        app.biometricDtrTemplate = resp?.data?.settings?.biometric_dtr_template === 'template2' ? 'template2' : 'default'
        app.biometricDtrTemplate2LogoPosition = resp?.data?.settings?.biometric_dtr_template2_logo_position ?? 'left'
        app.biometricDtrTemplate2LogoLeft = resp?.data?.settings?.biometric_dtr_template2_logo_left ?? 0
        app.biometricDtrTemplate2LogoWidth = resp?.data?.settings?.biometric_dtr_template2_logo_width ?? 15
        app.biometricDtrTemplate2LogoHeight = resp?.data?.settings?.biometric_dtr_template2_logo_height ?? 15
        app.biometricDtrTemplate2LogoRight = resp?.data?.settings?.biometric_dtr_template2_logo_right ?? 4
        app.biometricDtrTemplate2LogoTop = resp?.data?.settings?.biometric_dtr_template2_logo_top ?? 0
        app.biometricDtrTemplate2LogoBottom = resp?.data?.settings?.biometric_dtr_template2_logo_bottom ?? 3
        app.biometricDtrTemplate2FontSize = resp?.data?.settings?.biometric_dtr_template2_font_size ?? 8
        app.biometricDtrTemplate2NameFontSize = resp?.data?.settings?.biometric_dtr_template2_name_font_size ?? 10
        app.biometricDtrTemplate2CompanyFontSize = resp?.data?.settings?.biometric_dtr_template2_company_font_size ?? 8
        app.biometricDtrTemplate2RepublicFontSize = resp?.data?.settings?.biometric_dtr_template2_republic_font_size ?? 8
        app.biometricDtrTemplate2DepartmentFontSize = resp?.data?.settings?.biometric_dtr_template2_department_font_size ?? 8
        app.biometricDtrTemplate2AddressFontSize = resp?.data?.settings?.biometric_dtr_template2_address_font_size ?? 8
        app.biometricDtrTemplate2TitleFontSize = resp?.data?.settings?.biometric_dtr_template2_title_font_size ?? 13
        app.biometricDtrTemplate2ApprovedFontSize = resp?.data?.settings?.biometric_dtr_template2_approved_font_size ?? 10
        app.biometricDtrTemplate2TimekeeperFontSize = resp?.data?.settings?.biometric_dtr_template2_timekeeper_font_size ?? 10
        app.biometricDtrTemplate2EmployeeFontSize = resp?.data?.settings?.biometric_dtr_template2_employee_font_size ?? 10
        app.biometricDtrTemplate2MonthFontSize = resp?.data?.settings?.biometric_dtr_template2_month_font_size ?? 10
        app.biometricDtrTemplate2TableHeaderFontSize = resp?.data?.settings?.biometric_dtr_template2_table_header_font_size ?? 7
        app.biometricDtrTemplate2TableContentFontSize = resp?.data?.settings?.biometric_dtr_template2_table_content_font_size ?? 7

        app.biometricDtrSignatoryName = resp?.data?.settings?.biometric_dtr_signatory_name ?? ''
        app.biometricDtrTimekeeperName = resp?.data?.settings?.biometric_dtr_timekeeper_name ?? ''
        app.biometricDtrSignatorySignature = resp?.data?.settings?.biometric_dtr_signatory_signature || ''
        app.biometricDtrTimekeeperSignature = resp?.data?.settings?.biometric_dtr_timekeeper_signature || ''
        app.biometricDtrSignatoryUseDefault = Boolean(resp?.data?.settings?.biometric_dtr_signatory_use_default ?? true)
        app.biometricDtrTimekeeperUseDefault = Boolean(resp?.data?.settings?.biometric_dtr_timekeeper_use_default ?? true)
        app.biometricDtrSignatorySignatureEnabled = Boolean(resp?.data?.settings?.biometric_dtr_signatory_signature_enabled ?? false)
        app.biometricDtrTimekeeperSignatureEnabled = Boolean(resp?.data?.settings?.biometric_dtr_timekeeper_signature_enabled ?? false)
        app.machineAutoSyncStatusTimerEnabled = Boolean(resp?.data?.settings?.machine_auto_sync_status_timer_enabled ?? true)
        app.machineAutoSyncStatusTimerMs = Number(resp?.data?.settings?.machine_auto_sync_status_timer_ms || 5000)
        app.machineRefreshTimerEnabled = Boolean(resp?.data?.settings?.machine_refresh_timer_enabled ?? true)
        app.machineRefreshTimerMs = Number(resp?.data?.settings?.machine_refresh_timer_ms || 5000)
        app.machineWebAutoFallbackTimerEnabled = Boolean(resp?.data?.settings?.machine_web_auto_fallback_timer_enabled ?? true)
        app.machineWebAutoFallbackTimerMs = Number(resp?.data?.settings?.machine_web_auto_fallback_timer_ms || 1000)
        app.loaded = true
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async updateSettings(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/settings/update', payload).then(function (resp) {
        app.companySchoolName = resp?.data?.settings?.company_school_name || app.companySchoolName
        app.companySchoolLogo = resp?.data?.settings?.company_school_logo || app.companySchoolLogo
        app.companySchoolLogoPrintEnabled = Boolean(resp?.data?.settings?.company_school_logo_print_enabled ?? app.companySchoolLogoPrintEnabled)
        app.biometricDtrHeaderAddress = resp?.data?.settings?.biometric_dtr_header_address ?? ''
        app.biometricDtrTemplate = resp?.data?.settings?.biometric_dtr_template === 'template2' ? 'template2' : 'default'
        app.biometricDtrTemplate2LogoPosition = resp?.data?.settings?.biometric_dtr_template2_logo_position ?? 'left'
        app.biometricDtrTemplate2LogoLeft = resp?.data?.settings?.biometric_dtr_template2_logo_left ?? 0
        app.biometricDtrTemplate2LogoWidth = resp?.data?.settings?.biometric_dtr_template2_logo_width ?? 15
        app.biometricDtrTemplate2LogoHeight = resp?.data?.settings?.biometric_dtr_template2_logo_height ?? 15
        app.biometricDtrTemplate2LogoRight = resp?.data?.settings?.biometric_dtr_template2_logo_right ?? 4
        app.biometricDtrTemplate2LogoTop = resp?.data?.settings?.biometric_dtr_template2_logo_top ?? 0
        app.biometricDtrTemplate2LogoBottom = resp?.data?.settings?.biometric_dtr_template2_logo_bottom ?? 3
        app.biometricDtrTemplate2FontSize = resp?.data?.settings?.biometric_dtr_template2_font_size ?? 8
        app.biometricDtrTemplate2NameFontSize = resp?.data?.settings?.biometric_dtr_template2_name_font_size ?? 10
        app.biometricDtrTemplate2CompanyFontSize = resp?.data?.settings?.biometric_dtr_template2_company_font_size ?? 8
        app.biometricDtrTemplate2RepublicFontSize = resp?.data?.settings?.biometric_dtr_template2_republic_font_size ?? 8
        app.biometricDtrTemplate2DepartmentFontSize = resp?.data?.settings?.biometric_dtr_template2_department_font_size ?? 8
        app.biometricDtrTemplate2AddressFontSize = resp?.data?.settings?.biometric_dtr_template2_address_font_size ?? 8
        app.biometricDtrTemplate2TitleFontSize = resp?.data?.settings?.biometric_dtr_template2_title_font_size ?? 13
        app.biometricDtrTemplate2ApprovedFontSize = resp?.data?.settings?.biometric_dtr_template2_approved_font_size ?? 10
        app.biometricDtrTemplate2TimekeeperFontSize = resp?.data?.settings?.biometric_dtr_template2_timekeeper_font_size ?? 10
        app.biometricDtrTemplate2EmployeeFontSize = resp?.data?.settings?.biometric_dtr_template2_employee_font_size ?? 10
        app.biometricDtrTemplate2MonthFontSize = resp?.data?.settings?.biometric_dtr_template2_month_font_size ?? 10
        app.biometricDtrTemplate2TableHeaderFontSize = resp?.data?.settings?.biometric_dtr_template2_table_header_font_size ?? 7
        app.biometricDtrTemplate2TableContentFontSize = resp?.data?.settings?.biometric_dtr_template2_table_content_font_size ?? 7

        app.biometricDtrSignatoryName = resp?.data?.settings?.biometric_dtr_signatory_name ?? app.biometricDtrSignatoryName
        app.biometricDtrTimekeeperName = resp?.data?.settings?.biometric_dtr_timekeeper_name ?? app.biometricDtrTimekeeperName
        app.biometricDtrSignatorySignature = resp?.data?.settings?.biometric_dtr_signatory_signature || app.biometricDtrSignatorySignature
        app.biometricDtrTimekeeperSignature = resp?.data?.settings?.biometric_dtr_timekeeper_signature || app.biometricDtrTimekeeperSignature
        app.biometricDtrSignatoryUseDefault = Boolean(resp?.data?.settings?.biometric_dtr_signatory_use_default ?? app.biometricDtrSignatoryUseDefault)
        app.biometricDtrTimekeeperUseDefault = Boolean(resp?.data?.settings?.biometric_dtr_timekeeper_use_default ?? app.biometricDtrTimekeeperUseDefault)
        app.biometricDtrSignatorySignatureEnabled = Boolean(resp?.data?.settings?.biometric_dtr_signatory_signature_enabled ?? app.biometricDtrSignatorySignatureEnabled)
        app.biometricDtrTimekeeperSignatureEnabled = Boolean(resp?.data?.settings?.biometric_dtr_timekeeper_signature_enabled ?? app.biometricDtrTimekeeperSignatureEnabled)
        app.machineAutoSyncStatusTimerEnabled = Boolean(resp?.data?.settings?.machine_auto_sync_status_timer_enabled ?? app.machineAutoSyncStatusTimerEnabled)
        app.machineAutoSyncStatusTimerMs = Number(resp?.data?.settings?.machine_auto_sync_status_timer_ms || app.machineAutoSyncStatusTimerMs)
        app.machineRefreshTimerEnabled = Boolean(resp?.data?.settings?.machine_refresh_timer_enabled ?? app.machineRefreshTimerEnabled)
        app.machineRefreshTimerMs = Number(resp?.data?.settings?.machine_refresh_timer_ms || app.machineRefreshTimerMs)
        app.machineWebAutoFallbackTimerEnabled = Boolean(resp?.data?.settings?.machine_web_auto_fallback_timer_enabled ?? app.machineWebAutoFallbackTimerEnabled)
        app.machineWebAutoFallbackTimerMs = Number(resp?.data?.settings?.machine_web_auto_fallback_timer_ms || app.machineWebAutoFallbackTimerMs)
        app.loaded = true
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async runMaintenancePatch() {
      const auth = useAuthStore()

      return await axios.post('/api/settings/maintenance-patch').then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async runSystemUpdate() {
      const auth = useAuthStore()

      return await axios.post('/api/settings/system-update').then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async loadAttendanceDaemonStatus() {
      const auth = useAuthStore()

      return await axios.post('/api/settings/attendance-daemon/status').then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async installAttendanceDaemon(payload = {}) {
      const auth = useAuthStore()

      return await axios.post('/api/settings/attendance-daemon/install', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async backupDatabase(passphrase) {
      const auth = useAuthStore()

      return await axios.post('/api/settings/database/backup', { passphrase }).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async exportDatabase(passphrase) {
      const auth = useAuthStore()

      return await axios.post('/api/settings/database/export', { passphrase }, {
        responseType: 'blob',
      }).then(function (resp) {
        const headerName = resp?.headers?.['content-disposition'] || ''
        const match = headerName.match(/filename="?([^\"]+)"?/i)
        const filename = match?.[1] || 'db-export.bkp'
        return { success: true, data: { blob: resp.data, filename } }
      }).catch(function (resp) {
        if (resp.response?.data instanceof Blob) {
          return { success: false, data: resp, isBlobError: true }
        }

        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async importDatabase(file, passphrase) {
      const auth = useAuthStore()
      const formData = new FormData()
      formData.append('backup_file', file)
      formData.append('passphrase', passphrase)

      return await axios.post('/api/settings/database/import', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async loadBackupAutoStatus() {
      const auth = useAuthStore()

      return await axios.post('/api/settings/database/auto-status').then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async updateAutoBackupConfig(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/settings/database/auto-update', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async runAutoBackupNow() {
      const auth = useAuthStore()

      return await axios.post('/api/settings/database/auto-run').then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async downloadBackupFile(filename) {
      const auth = useAuthStore()

      return await axios.post('/api/settings/database/download', { filename }, {
        responseType: 'blob',
      }).then(function (resp) {
        const headerName = resp?.headers?.['content-disposition'] || ''
        const match = headerName.match(/filename="?([^\"]+)"?/i)
        const resolvedFilename = match?.[1] || filename || 'backup.bkp'
        return { success: true, data: { blob: resp.data, filename: resolvedFilename } }
      }).catch(function (resp) {
        if (resp.response?.data instanceof Blob) {
          return { success: false, data: resp, isBlobError: true }
        }

        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async deleteBackupFile(filename) {
      const auth = useAuthStore()

      return await axios.post('/api/settings/database/delete', { filename }).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async restoreBackupFile(filename, passphrase) {
      const auth = useAuthStore()

      return await axios.post('/api/settings/database/restore', { filename, passphrase }).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },
  },
})
