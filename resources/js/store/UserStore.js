import { defineStore } from 'pinia'
import { encrypt, decrypt } from '../utils/crypto';
import { useAuthStore } from './AuthStore'

export const useUserStore = defineStore('users',{
  id: 'users',
   persist: {
        key: 'hhgki',
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
    users: [],
    officeShifts: [],
    departments: [],
    colleges: [],
    user: {},
    loaded: false,
  }),
  getters: {},
  actions: {
    async loadUsers() {
      let app = this;
      const auth = useAuthStore();
      // if (app.loaded) { return; }
      return await axios.post('/api/users').then(function (resp) {
        app.users = resp.data.users;
        app.officeShifts = resp.data.office_shifts || [];
        app.departments = resp.data.departments || [];
        app.colleges = resp.data.colleges || [];
        app.loaded = true;
        return { success: true, data: resp.data };
      }).catch(function (resp) {
        console.log(resp.response);
        if (resp.response) {
          if (resp.response.data.message == 'Unauthenticated.') {
            auth.clearAccount()
          }
        }
        return { success: false, data: resp };
      })
    },
    async storeUser(user) {
      let app = this;
      const auth = useAuthStore();
      return await axios.post('/api/user/store', user).then(function (resp) {
        if(resp.data.message !='Email Exist'){
        app.users.push(resp.data.user)
        }
      
        return { success: true, data: resp.data };
      }).catch(function (resp) {
        console.log(resp.response);
        if (resp.response) {
          if (resp.response.data.message == 'Unauthenticated.') {
            auth.clearAccount()
          }
        }
        return { success: false, data: resp };
      })
    },
    async updateUser(user) {
      let app = this;
      const auth = useAuthStore();
      return await axios.post('/api/user/update', user).then(function (resp) {

        if(resp.data.message !='Email Exist'){
          const updatedUser = resp?.data?.user;
          const targetId = Number(updatedUser?.id ?? user.id);
          const index = app.users.findIndex((item) => Number(item.id) === targetId);

          if (index > -1 && updatedUser) {
            app.users.splice(index, 1, updatedUser)
          }

          if(updatedUser?.id == auth.user.id){
            auth.user = updatedUser;
          }
        }
        return { success: true, data: resp.data };
      }).catch(function (resp) {
        //console.log(resp.response);
        if (resp.response) {
          if (resp.response.data.message == 'Unauthenticated.') {
            auth.clearAccount()
          }
        }
        return { success: false, data: resp };
      })
    },
    async deleteUser(user) {
      let app = this;
      const auth = useAuthStore();
      return await axios.post('/api/user/delete', user).then(function (resp) {

        const targetId = Number(user.id)
        var index = app.users.findIndex((item) => Number(item.id) === targetId)

        if (index > -1) {
          app.users.splice(index, 1);
        }

        return { success: true, data: resp.data };

      }).catch(function (resp) {
      //  console.log(resp);
        if (resp.response) {
          if (resp.response.data.message == 'Unauthenticated.') {
            auth.clearAccount()
          }
        }
        return { success: false, data: resp };
      })
    },
    async updateUserOfficeShift(payload) {
      let app = this;
      const auth = useAuthStore();
      return await axios.post('/api/user/office-shift/update', payload).then(function (resp) {
        const updatedUser = resp.data.user;
        const index = app.users
          .map((x) => x.id)
          .indexOf(updatedUser.id);

        if (index > -1) {
          app.users.splice(index, 1, updatedUser);
        }

        if (updatedUser.id == auth.user.id) {
          auth.user = updatedUser;
        }

        return { success: true, data: resp.data };
      }).catch(function (resp) {
        if (resp.response) {
          if (resp.response.data.message == 'Unauthenticated.') {
            auth.clearAccount()
          }
        }
        return { success: false, data: resp };
      })
    },
    async updateUserAffiliation(payload) {
      let app = this;
      const auth = useAuthStore();
      return await axios.post('/api/user/affiliation/update', payload).then(function (resp) {
        const updatedUser = resp.data.user;
        const index = app.users
          .map((x) => x.id)
          .indexOf(updatedUser.id);

        if (index > -1) {
          app.users.splice(index, 1, updatedUser);
        }

        if (updatedUser.id == auth.user.id) {
          auth.user = updatedUser;
        }

        return { success: true, data: resp.data };
      }).catch(function (resp) {
        if (resp.response) {
          if (resp.response.data.message == 'Unauthenticated.') {
            auth.clearAccount()
          }
        }
        return { success: false, data: resp };
      })
    }
  }
})
