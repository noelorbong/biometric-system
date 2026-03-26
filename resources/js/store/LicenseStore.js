import { defineStore } from 'pinia'
import axios from 'axios'

export const useLicenseStore = defineStore('license', {
  state: () => ({
    status: 'checking',   // 'checking' | 'licensed' | 'expired'
    licenseKey: null,
    licenseExpiry: null,
    licenseDaysLeft: null,
    license_loaded: false,
  }),

  getters: {
    isAllowed: (state) => state.status === 'licensed',
    isLicensed: (state) => state.status === 'licensed',
    isExpired: (state) => state.status === 'expired',
  },

  actions: {
    _applyResponse(data) {
      this.status          = data.status
      this.licenseKey      = data.license_key ?? null
      this.licenseExpiry   = data.license_expiry ?? null
      this.licenseDaysLeft = data.license_days_left ?? null
    },

    async loadStatus() {
      try {
        const resp = await axios.post('/api/license/status')
        this._applyResponse(resp.data)
        this.license_loaded = true
        return { success: true }
      } catch (e) {
        this.status = 'expired'
        return { success: false }
      }
    },

    async activate(key) {
      const resp = await axios.post('/api/license/activate', { key })
      this._applyResponse(resp.data)
      return { success: true, message: resp.data.message }
    },

    async deactivate() {
      await axios.post('/api/license/deactivate')
      this.status          = 'expired'
      this.licenseKey      = null
      this.licenseExpiry   = null
      this.licenseDaysLeft = null
    },
  },
})
