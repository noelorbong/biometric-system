<script setup>
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { PencilIcon } from '@/icons'
import Swal from "sweetalert2";
import { useLoadingStore } from '@/store/LoadingStore'
import ProfileCard from './components/ProfileCard.vue'
import ModalUser from './components/Modal.vue'

import { useUserStore } from '@/store/UserStore'

const userStore = useUserStore();
const router = useRouter()
const { users } = storeToRefs(userStore)

import { useAuthStore } from '@/store/AuthStore'
const authStore = useAuthStore();
const { authenticated, user } = storeToRefs(authStore)

const loadingStore = useLoadingStore();
const { isLoading, text } = storeToRefs(loadingStore)

const l_college = ref()
const isUserAddModal = ref(false);
const l_user = ref({});

onMounted(async() => {
  // await userStore.profileUser(user.value);
})

const Toast = Swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 1500,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.onmouseenter = Swal.stopTimer;
    toast.onmouseleave = Swal.resumeTimer;
  }
});

const toastResult = (message) => {
  Toast.fire({
    icon: "success",
    title: message
  });
}

const roleMap = {
  0: 'User',
  1: 'Super Admin',
  2: 'Region Admin',
  3: 'SUC Admin',
  4: 'Campus Admin',
  5: 'College Admin',
  6: 'Employee',
}

const saveUser = async (event) => {
  isUserAddModal.value = false;
  isLoading.value = true;
  text.value = 'Updating User..';
  var result = await userStore.updateUser(event);
  if (result.success) {
    toastResult('Updated Successfully');
  }

  isLoading.value = false;
}

const editUser = (event) => {

  isUserAddModal.value = true;
  l_user.value = {
    id: event.id,
    name: event.name,
    email: event.email,
    role: event.role,
    status: Number(event.status),
    office_shift_id: event.office_shift_id ?? null,
    department_id: event.department_id ?? null,
    college_id: event.college_id ?? null,
    password: '',
    first_name: event.profile?.first_name || '',
    middle_name: event.profile?.middle_name || '',
    last_name: event.profile?.last_name || '',
    name_extension: event.profile?.name_extension || '',
    dob: event.profile?.dob || '',
    gender: event.profile?.gender || '',
    image: event.profile?.image || '',
    thumbnail: event.profile?.thumbnail || '',
    contact_type: event.primary_contact?.type || '',
    contact_value: event.primary_contact?.value || '',
    contacts: event.contacts?.length ? JSON.parse(JSON.stringify(event.contacts)) : [{ id: null, type: 'mobile', value: '', is_primary: true }],
    address_label: event.primary_address?.label || '',
    address1: event.primary_address?.address1 || '',
    address2: event.primary_address?.address2 || '',
    barangay: event.primary_address?.barangay || '',
    municipality: event.primary_address?.municipality || '',
    province: event.primary_address?.province || '',
    zipcode: event.primary_address?.zipcode || '',
    addresses: event.addresses?.length ? JSON.parse(JSON.stringify(event.addresses)) : [{ id: null, label: 'home', address1: '', address2: '', barangay: '', municipality: '', province: '', zipcode: '', is_primary: true }],
  };
}

const selectedCollege = (event) => {
  l_college.value = event;
}
</script>
<template>
  <div class="space-y-3">
    <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-col gap-2 min-[800px]:flex-row min-[800px]:items-center min-[800px]:justify-between">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
          <h1 class="text-lg font-semibold text-slate-900 dark:text-white">User Profile</h1>
          <span class="text-xs text-slate-500 dark:text-slate-400">{{ user?.email || 'No email set' }}</span>
          <span class="rounded bg-sky-50 px-1.5 py-0.5 text-[11px] font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">{{ roleMap[user?.role] || '-' }}</span>
          <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold" :class="user?.status ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'">
            {{ user?.status ? 'Active' : 'Inactive' }}
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2 min-[800px]:justify-end">
          <button
            type="button"
            @click="router.push({ name: 'UserView', params: { id: user?.id } })"
            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 text-xs font-medium text-sky-700 transition hover:bg-sky-100 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-sky-800/60 dark:bg-sky-900/20 dark:text-sky-300"
          >
            My Biometric
          </button>
          <button
            type="button"
            @click="editUser(user)"
            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 text-xs font-medium text-sky-700 transition hover:bg-sky-100 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-sky-800/60 dark:bg-sky-900/20 dark:text-sky-300"
          >
            <PencilIcon />
            Edit Profile
          </button>
        </div>
      </div>
    </section>

    <section class="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-1 text-xs">
      <article class="flex items-baseline gap-1.5">
        <p class="font-medium text-slate-500 dark:text-slate-400">Role</p>
        <p class="font-semibold text-slate-900 dark:text-white">{{ roleMap[user?.role] || '-' }}</p>
      </article>
      <article class="flex items-baseline gap-1.5">
        <p class="font-medium text-slate-500 dark:text-slate-400">Status</p>
        <p class="font-semibold" :class="user?.status ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
          {{ user?.status ? 'Active' : 'Inactive' }}
        </p>
      </article>
      <article class="flex items-baseline gap-1.5">
        <p class="font-medium text-slate-500 dark:text-slate-400">User ID</p>
        <p class="font-semibold text-slate-900 dark:text-white">#{{ user?.id || '-' }}</p>
      </article>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-col gap-2 min-[800px]:flex-row min-[800px]:items-center min-[800px]:justify-between">
        <div>
          <h2 class="text-sm font-semibold text-slate-900 dark:text-white">My Biometric</h2>
          <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Attendance, logs, and printable record.</p>
        </div>
        <button
          type="button"
          @click="router.push({ name: 'UserView', params: { id: user?.id } })"
          class="inline-flex h-8 items-center justify-center rounded-md border border-sky-200 bg-sky-50 px-2.5 text-xs font-medium text-sky-700 transition hover:bg-sky-100 dark:border-sky-800/60 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/30"
        >
          Open Biometric Page
        </button>
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="mb-2 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Profile Information</h2>
        <button
          type="button"
           @click="editUser(user)"
          class="inline-flex h-8 items-center gap-1.5 rounded-md border border-sky-200 px-2.5 text-xs font-medium text-sky-700 transition hover:bg-sky-50 dark:border-sky-800/60 dark:text-sky-300 dark:hover:bg-sky-900/20"
        >
          <PencilIcon />
          Edit
        </button>
      </div>

      <ProfileCard :user="user" />
    </section>

    <ModalUser :authUser="authStore.user" :isEditUser="true" :user="l_user" :users="users" v-if="isUserAddModal" @save="saveUser"
      @close="isUserAddModal = false" :edit_type="2" :campuses="[]" :colleges="[]"/>
  </div>
</template>


<style lang="css" scoped>
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}

.slide-fade-leave-active {
  transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateX(20px);
  opacity: 0;
}
</style>
