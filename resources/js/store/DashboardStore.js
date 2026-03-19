import { defineStore } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto';
import { useAuthStore } from './AuthStore'

export const useDashboardStore = defineStore('dashboards',{
  id: 'dashboards',
   persist: {
        key: 'gsdfhr',
        storage: {
            getItem: (key) => {
                const raw = localStorage.getItem(key);
                return raw ? decrypt(raw) : null;
            },
            setItem: (key, value) => {
                localStorage.setItem(key, encrypt(value));
            },
            removeItem: (key) => {
                localStorage.removeItem(key);
            },
        },
    },
  state: () => ({
    dashboard: null,
    loaded: false,
    loading: false,
    error: null,
  }),
  getters: {},
  actions: {
    async loadDashboards(data = {}) {
      const app = this;
      const auth = useAuthStore();
      app.loading = true;
      app.error = null;
      return await axios.post('/api/dashboard/data', data).then(function (resp) {
        app.dashboard = resp.data;
        app.loaded = true;
        app.loading = false;
        return { success: true, data: resp.data };
      }).catch(function (resp) {
        app.loading = false;
        app.error = resp.response?.data?.message || 'Unable to load dashboard data';
        if (resp.response) {
          if (resp.response.data.message == 'Unauthenticated.') {
            auth.clearAccount()
          }
        }
        return { success: false, data: resp };
      })
    },
  }
})
