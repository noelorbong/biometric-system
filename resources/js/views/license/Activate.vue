<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useLicenseStore } from '@/store/LicenseStore'

const router = useRouter()
const licenseStore = useLicenseStore()

const licenseKey = ref('')
const activating = ref(false)
const errorMsg = ref('')
const successMsg = ref('')

onMounted(async () => {
  if (!licenseStore.loaded) {
    await licenseStore.loadStatus()
  }

  // Already licensed — go home
  if (licenseStore.status === 'licensed') {
    router.replace({ name: 'Home' })
  }
})

const handleActivate = async () => {
  errorMsg.value = ''
  successMsg.value = ''

  if (!licenseKey.value.trim()) {
    errorMsg.value = 'Please enter your license key.'
    return
  }

  activating.value = true
  try {
    const result = await licenseStore.activate(licenseKey.value.trim())
    successMsg.value = result.message || 'License activated successfully.'

    setTimeout(() => {
      router.replace({ name: 'Home' })
    }, 1200)
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || 'Activation failed. Please try again.'
  } finally {
    activating.value = false
  }
}
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-[linear-gradient(180deg,_#f8fafc_0%,_#e2e8f0_100%)] dark:bg-[linear-gradient(180deg,_#020617_0%,_#0f172a_100%)]">
    <!-- Decorative blobs -->
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute -left-24 top-0 h-72 w-72 rounded-full bg-sky-300/20 blur-3xl"></div>
      <div class="absolute right-0 top-20 h-80 w-80 rounded-full bg-teal-300/15 blur-3xl"></div>
      <div class="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-cyan-200/15 blur-3xl"></div>
    </div>

    <div class="relative mx-auto flex min-h-screen w-full max-w-2xl flex-col items-center justify-center px-4 py-10 sm:px-6">
      <!-- Logo -->
      <div class="mb-8 flex flex-col items-center">
        <img :src="'/images/logo/banner_white_mode.png'" alt="Biometric System" class="h-12 object-contain dark:hidden" />
        <img :src="'/images/logo/banner_white_mode.png'" alt="Biometric System" class="hidden h-12 object-contain dark:block" />
      </div>

      <!-- Card -->
      <div class="w-full max-w-lg overflow-hidden rounded-[28px] border border-slate-200/80 bg-white/90 shadow-[0_24px_80px_-28px_rgba(15,23,42,0.30)] backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/70">
        <!-- Card Header -->
        <div class="border-b border-slate-200/80 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_35%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_48%,_#0f766e_100%)] px-7 py-7 text-white">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-200/80">
            License Required
          </p>
          <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white">Activate License</h1>
          <p class="mt-2 text-sm leading-6 text-slate-200/85">
            Enter your license key to unlock the Biometric System and gain full access.
          </p>
        </div>

        <!-- Card Body -->
        <div class="px-7 py-7 space-y-5">
          <!-- Error message -->
          <div v-if="errorMsg" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-300">
            {{ errorMsg }}
          </div>

          <!-- Success message -->
          <div v-if="successMsg" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ successMsg }}
          </div>

          <!-- License Key Input -->
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">License Key</label>
            <input
              v-model="licenseKey"
              type="text"
              placeholder="XXXX-XXXX-XXXX-XXXX"
              class="h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-800 placeholder-slate-400 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500 dark:focus:border-sky-400"
              :disabled="activating"
              @keydown.enter="handleActivate"
            />
            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
              Purchase a license at <span class="font-medium text-sky-600 dark:text-sky-400">bitsnbytes.com.ph</span> to unlock unlimited access.
            </p>
          </div>

          <!-- Activate Button -->
          <button
            @click="handleActivate"
            :disabled="activating"
            class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-sky-500 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-60"
          >
            <svg v-if="activating" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ activating ? 'Activating…' : 'Activate License' }}
          </button>
        </div>
      </div>

      <p class="mt-6 text-center text-xs text-slate-400 dark:text-slate-600">
        Biometric System &copy; {{ new Date().getFullYear() }}. Licensing powered by Keygen.
      </p>
    </div>
  </div>
</template>
