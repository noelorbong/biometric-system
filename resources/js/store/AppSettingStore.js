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
  },
})
