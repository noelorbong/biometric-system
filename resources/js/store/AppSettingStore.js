import { defineStore } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto'
import { useAuthStore } from './AuthStore'

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
    biometricDtrSignatoryName: 'In-Charge',
    biometricDtrSignatorySignature: '',
    biometricDtrSignatoryUseDefault: true,
    biometricDtrSignatorySignatureEnabled: false,
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

      if (app.loaded && !force) {
        return {
          success: true,
          data: {
            settings: {
              company_school_name: app.companySchoolName,
              company_school_logo: app.companySchoolLogo,
              company_school_logo_print_enabled: app.companySchoolLogoPrintEnabled,
              biometric_dtr_signatory_name: app.biometricDtrSignatoryName,
              biometric_dtr_signatory_signature: app.biometricDtrSignatorySignature,
              biometric_dtr_signatory_use_default: app.biometricDtrSignatoryUseDefault,
              biometric_dtr_signatory_signature_enabled: app.biometricDtrSignatorySignatureEnabled,
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
        app.biometricDtrSignatoryName = resp?.data?.settings?.biometric_dtr_signatory_name || 'In-Charge'
        app.biometricDtrSignatorySignature = resp?.data?.settings?.biometric_dtr_signatory_signature || ''
        app.biometricDtrSignatoryUseDefault = Boolean(resp?.data?.settings?.biometric_dtr_signatory_use_default ?? true)
        app.biometricDtrSignatorySignatureEnabled = Boolean(resp?.data?.settings?.biometric_dtr_signatory_signature_enabled ?? false)
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
        app.biometricDtrSignatoryName = resp?.data?.settings?.biometric_dtr_signatory_name || app.biometricDtrSignatoryName
        app.biometricDtrSignatorySignature = resp?.data?.settings?.biometric_dtr_signatory_signature || app.biometricDtrSignatorySignature
        app.biometricDtrSignatoryUseDefault = Boolean(resp?.data?.settings?.biometric_dtr_signatory_use_default ?? app.biometricDtrSignatoryUseDefault)
        app.biometricDtrSignatorySignatureEnabled = Boolean(resp?.data?.settings?.biometric_dtr_signatory_signature_enabled ?? app.biometricDtrSignatorySignatureEnabled)
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
