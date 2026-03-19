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

import { useAuthStore } from '@/store/AuthStore'
const authStore = useAuthStore();
const { authenticated, user } = storeToRefs(authStore)

const loadingStore = useLoadingStore();
const { isLoading, text } = storeToRefs(loadingStore)

const l_college = ref()
const isUserAddModal = ref(false);


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

const selectedCollege = (event) => {
  l_college.value = event;
}
</script>
<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] p-5 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
      <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">Account Workspace</p>
          <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white lg:text-4xl">User Profile</h1>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200/90">
            Review your account details, contact records, and profile metadata in one place.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            @click="router.push({ name: 'UserView', params: { id: user?.id } })"
            class="inline-flex h-11 items-center gap-2 rounded-2xl border border-white/10 bg-white/10 px-4 text-sm font-medium text-slate-100 shadow-sm backdrop-blur-sm transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/30"
          >
            My Biometric
          </button>
          <button
            type="button"
            @click="isUserAddModal = true"
            class="inline-flex h-11 items-center gap-2 rounded-2xl border border-sky-300/30 bg-sky-400/20 px-4 text-sm font-medium text-sky-50 shadow-sm backdrop-blur-sm transition hover:bg-sky-400/30 focus:outline-none focus:ring-2 focus:ring-white/30"
          >
            <PencilIcon />
            Edit Profile
          </button>
          <span class="inline-flex h-11 items-center rounded-2xl bg-white/10 px-4 text-sm font-medium text-slate-100 ring-1 ring-inset ring-white/10">
            {{ user?.email || 'No email set' }}
          </span>
        </div>
      </div>
    </section>

    <section class="grid gap-4 md:grid-cols-3">
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Role</p>
        <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">{{ roleMap[user?.role] || '-' }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</p>
        <p class="mt-2 text-lg font-semibold" :class="user?.status ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
          {{ user?.status ? 'Active' : 'Inactive' }}
        </p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">User ID</p>
        <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">#{{ user?.id || '-' }}</p>
      </article>
    </section>

    <section class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">My Biometric</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Open your attendance view to choose month and year, inspect logs, and print your record.</p>
        </div>
        <button
          type="button"
          @click="router.push({ name: 'UserView', params: { id: user?.id } })"
          class="inline-flex h-11 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 px-4 text-sm font-medium text-sky-700 transition hover:bg-sky-100 dark:border-sky-800/60 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/30"
        >
          Open Biometric Page
        </button>
      </div>
    </section>

    <section class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Profile Information</h2>
        <button
          type="button"
          @click="isUserAddModal = true"
          class="inline-flex items-center gap-2 rounded-lg border border-sky-200 px-3 py-1.5 text-sm font-medium text-sky-700 transition hover:bg-sky-50 dark:border-sky-800/60 dark:text-sky-300 dark:hover:bg-sky-900/20"
        >
          <PencilIcon />
          Edit
        </button>
      </div>

      <ProfileCard :user="user" />
    </section>

    <ModalUser :authUser="authStore.user" :isEditUser="true" :user="user" v-if="isUserAddModal" @save="saveUser"
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
