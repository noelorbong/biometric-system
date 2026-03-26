import axios from 'axios'
import { defineStore, getActivePinia } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto'
import { useLoadingStore } from './LoadingStore'

export const useAuthStore = defineStore('auth', {
  id: 'auth',
  persist: {
    key: 'gadfs',
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
    authenticated: false,
    user: {},
  }),
  getters: {
    getAuthenticated: (state) => state.authenticated,
    getUser: (state) => state.user,
  },
  actions: {
    async checkAuth() {
      try {
        const resp = await axios.post('/api/user', { withCredentials: true })
        this.user = resp.data.user
        this.authenticated = true
        return { success: true, data: resp.data }
      } catch (resp) {
        console.error('Failed to check auth:', resp)
        if (resp.response?.data?.message === 'Unauthenticated.') {
          this.user = {}
          this.authenticated = false
          getActivePinia()._s.forEach((store) => store.$reset())
        }
        return { success: false, data: resp }
      }
    },

    async loadUser() {
      try {
        const resp = await axios.post('/api/user', { withCredentials: true })
        this.user = resp.data.user
        this.authenticated = true
        return { success: true, data: resp.data }
      } catch (resp) {
        console.error('Failed to load user:', resp)
        this.clearAccount()
        return { success: false, data: resp }
      }
    },

    async login(loginAccount) {
      await axios.get('/sanctum/csrf-cookie')
      try {
        const resp = await axios.post('/login', loginAccount)
        return { success: true, data: resp.data }
      } catch (resp) {
        console.log('Failed to login:', resp.response)
        return { success: false, data: resp }
      }
    },

    clearAccount() {
      this.user = {}
      this.authenticated = false
      getActivePinia()._s.forEach((store) => store.$reset())

      const currentRouteName = this.$router?.currentRoute?.value?.name
      if (currentRouteName !== 'Signin') {
        this.$router?.replace({ name: 'Signin' })
      }
    },

    async logout() {
      try {
        await axios.post('/logout')
        this.clearAccount()
        return { success: true, data: {} }
      } catch (resp) {
        return { success: false, data: resp }
      }
    },

    async all() {
      const loading = useLoadingStore()
      loading.isLoading = true
      loading.text = 'Loading resources..'

      try {
        const resp = await axios.post('/api/loaddata')
        return { success: true, data: resp.data }
      } catch (resp) {
        return { success: false, data: resp }
      } finally {
        loading.isLoading = false
      }
    },
  },
})