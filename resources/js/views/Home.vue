<template>
  <div class="relative p-0 bg-white z-1 dark:bg-gray-900 sm:p-0">
    <div class="relative flex flex-col justify-center w-full h-screen lg:flex-row dark:bg-gray-900">

      <div class="relative items-center w-full h-full bg-brand-950 dark:bg-white/5 grid">
        <div class="w-full max-w-md pl-6 pt-10 mx-auto">
          <router-link to="/main/dashboard" v-if="authenticated"
            class="inline-flex items-center text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
            <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"
              fill="none">
              <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke="" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
            Back to dashboard
          </router-link>
          <router-link to="/signin" v-else
            class="inline-flex items-center text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
            <i class="fa fa-sign-in" aria-hidden="true"></i>
            Sign In
          </router-link>
        </div>
        <div class="flex items-center justify-center z-1">
          <common-grid-shape />
          <div class="flex flex-col items-center max-w-xl">
            <router-link to="/" class="block mb-4">
              <img class="w-[100%]" :src="'/images/logo/banner_white_mode.png'" alt="Logo" />
            </router-link>
         
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/store/AuthStore'

import CommonGridShape from '@/components/common/CommonGridShape.vue'

const router = useRouter();
const authStore = useAuthStore();

const { authenticated, user } = storeToRefs(authStore)

onMounted(async ()=>{
  await authStore.checkAuth();
})

</script>
