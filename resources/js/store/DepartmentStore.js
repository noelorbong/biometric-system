import { defineStore } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto'
import { useAuthStore } from './AuthStore'

export const useDepartmentStore = defineStore('departments', {
  id: 'departments',
  persist: {
    key: 'department-store',
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
    departments: [],
    loaded: false,
  }),
  getters: {},
  actions: {
    async loadDepartments() {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/departments').then(function (resp) {
        app.departments = resp?.data?.departments || []
        app.loaded = true
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async storeDepartment(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/department/store', payload).then(function (resp) {
        if (resp?.data?.department) {
          app.departments.push(resp.data.department)
        }
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async updateDepartment(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/department/update', payload).then(function (resp) {
        const updatedDepartment = resp?.data?.department
        if (updatedDepartment) {
          const index = app.departments
            .map((x) => x.id)
            .indexOf(updatedDepartment.id)

          if (index > -1) {
            app.departments.splice(index, 1, updatedDepartment)
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

    async deleteDepartment(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/department/delete', payload).then(function (resp) {
        const index = app.departments
          .map((x) => x.id)
          .indexOf(payload.id)

        if (index > -1) {
          app.departments.splice(index, 1)
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
