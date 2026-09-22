import { defineStore } from 'pinia'
import { useAuthStore } from './AuthStore'

export const useHolidayStore = defineStore('holidays', {
  state: () => ({ holidays: [], loaded: false }),
  actions: {
    async request(url, payload = {}) {
      const auth = useAuthStore()
      try {
        const response = await axios.post(url, payload)
        return { success: true, data: response.data }
      } catch (error) {
        if (error.response?.data?.message === 'Unauthenticated.') auth.clearAccount()
        return { success: false, data: error }
      }
    },
    async loadHolidays() {
      const result = await this.request('/api/holidays')
      if (result.success) {
        this.holidays = result.data.holidays || []
        this.loaded = true
      }
      return result
    },
    async storeHoliday(payload) {
      const result = await this.request('/api/holiday/store', payload)
      if (result.success && result.data.holiday) this.holidays.push(result.data.holiday)
      return result
    },
    async updateHoliday(payload) {
      const result = await this.request('/api/holiday/update', payload)
      if (result.success && result.data.holiday) {
        const index = this.holidays.findIndex((holiday) => holiday.id === result.data.holiday.id)
        if (index >= 0) this.holidays.splice(index, 1, result.data.holiday)
      }
      return result
    },
    async deleteHoliday(id) {
      const result = await this.request('/api/holiday/delete', { id })
      if (result.success) this.holidays = this.holidays.filter((holiday) => holiday.id !== id)
      return result
    },
  },
})