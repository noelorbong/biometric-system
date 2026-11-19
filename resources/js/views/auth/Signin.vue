<script setup >
import { ref, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/store/AuthStore'
import { useLoadingStore } from '@/store/LoadingStore'



const loadingStore = useLoadingStore();
const router = useRouter();
const authStore = useAuthStore();

const { isLoading, text } = storeToRefs(loadingStore)
const { authenticated, user } = storeToRefs(authStore)

const login = ref('')
const password = ref('')
const showPassword = ref(false)
const keepLoggedIn = ref(false)
const error =ref(false)
const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value
}


onMounted(async() => {
  // var result = await authStore.loadUser();
  // if(!result.success){ return;}
  if(authenticated.value){
    router.push({ name: 'Dashboard' })
  }
  
})

const handleSubmit = async() => {
  isLoading.value = true;
  text.value = "Signing In.."
  var login_account = {
    login: login.value.trim(),
    password: password.value,
    remember: keepLoggedIn.value,
  };
  var result = await authStore.login(login_account);
  if(result.success){
    var result = await authStore.loadUser();
    if(result.success){
      authenticated.value = true;
      user.value = result.data.user;
      router.push({ name: 'Dashboard' })
    }
  }else{
    error.value = true;
  }
  isLoading.value = false;
}
</script>
<template>
  <div class="relative flex min-h-screen items-center justify-center bg-slate-100 px-4 py-6 text-slate-800 dark:bg-slate-950 dark:text-slate-200">
    <loading :enforce-focus="true" :active="isLoading" :can-cancel="false" :is-full-page="true" :loader="loadingStore.loader" :color="loadingStore.loaderColor" />
    <div class="w-full max-w-[920px]">
      <router-link to="/" class="mb-3 inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-500 hover:text-teal-700 dark:text-slate-400 dark:hover:text-teal-300">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 6-6 6 6 6" /></svg>
        Back to home
      </router-link>
      <div class="grid overflow-hidden border border-slate-300 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900 md:grid-cols-2">
        <section class="min-w-0">
          <div class="border-b border-white/10 bg-[linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] px-6 py-5 text-white">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-teal-200">Biometric System</p>
            <h1 class="mt-2 text-xl font-semibold">Sign In</h1>
            <p class="mt-1 text-[11px] text-slate-300">Access your attendance workspace.</p>
          </div>
          <div class="px-6 py-5">
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Enter your username or email and password.</p>
            <p v-if="error" role="alert" class="mb-4 border border-rose-200 bg-rose-50 px-3 py-2 text-[11px] text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300">Incorrect account details. Please try again.</p>
            <form class="space-y-4" @submit.prevent="handleSubmit">
              <div>
                <label for="login" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Username or email <span class="text-rose-500">*</span></label>
                <input id="login" v-model="login" name="login" type="text" autocomplete="username" required placeholder="Username or email address" class="h-9 w-full min-w-0 border border-slate-300 bg-white px-3 text-xs text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-slate-600 dark:bg-slate-950/50 dark:text-white" @input="error = false" />
              </div>
              <div>
                <label for="password" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input id="password" v-model="password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required placeholder="Enter your password" class="h-9 w-full min-w-0 border border-slate-300 bg-white pl-3 pr-14 text-xs text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-slate-600 dark:bg-slate-950/50 dark:text-white" @input="error = false" />
                  <button type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" class="absolute inset-y-0 right-0 px-3 text-[11px] font-semibold text-teal-700 hover:text-teal-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-500 dark:text-teal-300" @click="togglePasswordVisibility">{{ showPassword ? 'Hide' : 'Show' }}</button>
                </div>
              </div>
              <label for="keepLoggedIn" class="inline-flex cursor-pointer items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <input id="keepLoggedIn" v-model="keepLoggedIn" type="checkbox" class="h-3.5 w-3.5 rounded-none border-slate-300 text-teal-700 focus:ring-teal-500" />
                Keep me logged in
              </label>
              <button type="submit" :disabled="isLoading" class="flex h-9 w-full items-center justify-center border border-cyan-300/30 bg-[linear-gradient(135deg,_#0891b2_0%,_#0f766e_100%)] px-3 text-xs font-semibold text-white transition hover:bg-[linear-gradient(135deg,_#0e7490_0%,_#115e59_100%)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">{{ isLoading ? 'Signing in...' : 'Sign In' }}</button>
            </form>
          </div>
        </section>
        <aside class="relative hidden min-w-0 flex-col justify-between gap-8 border-l border-white/10 bg-[linear-gradient(145deg,_#0f172a_0%,_#1e293b_50%,_#0f766e_100%)] p-7 text-white md:flex">
          <div>
            <img :src="'/images/logo/banner_white_mode.png'" alt="Biometric System" class="mb-7 w-full max-w-[280px]" />
            <p class="text-[10px] font-semibold uppercase tracking-widest text-teal-200">Attendance management</p>
            <h2 class="mt-3 text-2xl font-semibold leading-tight">Your attendance workspace,<br />in one place.</h2>
            <p class="mt-3 text-xs leading-relaxed text-slate-300">Review attendance, manage biometric devices, and prepare daily time records.</p>
          </div>
          <div class="space-y-3 border-t border-white/15 pt-4 text-[11px] text-slate-200">
            <p class="flex items-center gap-2"><span class="h-1 w-1 bg-teal-300"></span>Attendance records and reports</p>
            <p class="flex items-center gap-2"><span class="h-1 w-1 bg-teal-300"></span>Biometric device management</p>
            <p class="flex items-center gap-2"><span class="h-1 w-1 bg-teal-300"></span>Schedules and printable time records</p>
          </div>
        </aside>
      </div>
    </div>
  </div>
</template>
