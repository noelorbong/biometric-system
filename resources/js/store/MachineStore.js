import { defineStore } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto'
import { useAuthStore } from './AuthStore'

export const useMachineStore = defineStore('machines', {
  id: 'machines',
  persist: {
    key: 'machine-store',
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
    machines: [],
    loaded: false,
  }),
  actions: {
    async loadMachines() {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/machines').then(function (resp) {
        app.machines = resp?.data?.machines || []
        app.loaded = true
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async storeMachine(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/machine/store', payload).then(function (resp) {
        if (resp?.data?.machine) {
          app.machines.push(resp.data.machine)
        }
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async updateMachine(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/machine/update', payload).then(function (resp) {
        const updatedMachine = resp?.data?.machine
        if (updatedMachine) {
          const index = app.machines.map((x) => x.ID).indexOf(updatedMachine.ID)
          if (index > -1) {
            app.machines.splice(index, 1, updatedMachine)
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

    async deleteMachine(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/machine/delete', payload).then(function (resp) {
        const index = app.machines.map((x) => x.ID).indexOf(payload.ID)
        if (index > -1) {
          app.machines.splice(index, 1)
        }

        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async connectMachine(payload) {
      const app = this
      const auth = useAuthStore()

      return await axios.post('/api/machine/connect', payload).then(function (resp) {
        const updatedMachine = resp?.data?.machine
        if (updatedMachine) {
          const index = app.machines.map((x) => x.ID).indexOf(updatedMachine.ID)
          if (index > -1) {
            app.machines.splice(index, 1, updatedMachine)
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

    async autoSyncStatus() {
      const auth = useAuthStore()

      return await axios.post('/api/machine/auto-sync-status').then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async syncAttendance(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/sync-attendance', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async downloadUsers(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/download-users', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async clearAttendance(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/clear-attendance', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async syncUserTemplates(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/sync-user-templates', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async pushUsers(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/push-users', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async pushUser(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/push-user', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async enrollFingerprint(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/enroll-fingerprint', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async enrollFace(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/enroll-face', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async enrollmentFaceStatus(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/enrollment-face-status', payload).then(function (resp) {
        return { success: true, data: resp.data }
      }).catch(function (resp) {
        if (resp.response?.data?.message == 'Unauthenticated.') {
          auth.clearAccount()
        }
        return { success: false, data: resp }
      })
    },

    async enrollmentTemplateStatus(payload) {
      const auth = useAuthStore()

      return await axios.post('/api/machine/enrollment-template-status', payload).then(function (resp) {
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
