import { defineStore } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto'
import { useAuthStore } from './AuthStore'

export const useCollegeStore = defineStore('colleges', {
  id: 'colleges',
  persist: {
    key: 'college-store',
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
    colleges: [],
    loaded: false,
  }),
  getters: {},
  actions: {
    async loadColleges() {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/colleges').then(function (resp) {
        app.colleges = resp?.data?.colleges || []
        app.loaded = true
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async storeCollege(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/college/store', payload).then(function (resp) {
        if (resp?.data?.college) {
          app.colleges.push(resp.data.college)
        }
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async updateCollege(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/college/update', payload).then(function (resp) {
        const updatedCollege = resp?.data?.college
        if (updatedCollege) {
          const index = app.colleges
            .map((x) => x.id)
            .indexOf(updatedCollege.id)

          if (index > -1) {
            app.colleges.splice(index, 1, updatedCollege)
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

    async deleteCollege(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/college/delete', payload).then(function (resp) {
        const index = app.colleges
          .map((x) => x.id)
          .indexOf(payload.id)

        if (index > -1) {
          app.colleges.splice(index, 1)
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
