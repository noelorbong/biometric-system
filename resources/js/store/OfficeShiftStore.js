import { defineStore } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto'
import { useAuthStore } from './AuthStore'

export const useOfficeShiftStore = defineStore('office-shifts', {
  id: 'office-shifts',
  persist: {
    key: 'office-shift-store',
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
    officeShifts: [],
    loaded: false,
  }),
  getters: {},
  actions: {
    async loadOfficeShifts() {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/office-shifts').then(function (resp) {
        app.officeShifts = resp?.data?.office_shifts || []
        app.loaded = true
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async storeOfficeShift(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/office-shift/store', payload).then(function (resp) {
        if (resp?.data?.office_shift) {
          app.officeShifts.push(resp.data.office_shift)
        }
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async updateOfficeShift(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/office-shift/update', payload).then(function (resp) {
        const updatedShift = resp?.data?.office_shift
        if (updatedShift) {
          const index = app.officeShifts
            .map((x) => x.id)
            .indexOf(updatedShift.id)

          if (index > -1) {
            app.officeShifts.splice(index, 1, updatedShift)
          }
        }

        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async deleteOfficeShift(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/office-shift/delete', payload).then(function (resp) {
        const index = app.officeShifts
          .map((x) => x.id)
          .indexOf(payload.id)

        if (index > -1) {
          app.officeShifts.splice(index, 1)
        }

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
