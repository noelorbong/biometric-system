<template>
  <div
    v-if="shouldShow"
    class="fixed bottom-4 right-4 z-[100] w-[calc(100%-2rem)] max-w-sm rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-800"
  >
    <div class="mb-2 flex items-start justify-between gap-2">
      <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Install Biometric System</h3>
      <button
        type="button"
        class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white"
        @click="dismissPrompt"
      >
        Close
      </button>
    </div>

    <p class="mb-3 text-xs text-gray-600 dark:text-gray-300">
      {{ promptDescription }}
    </p>

    <button
      v-if="canUseNativeInstall"
      type="button"
      class="mb-3 inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-black dark:bg-white dark:text-black dark:hover:bg-gray-200"
      @click="installApp"
    >
      Install App
    </button>

    <ol v-else class="list-decimal space-y-1 pl-4 text-xs text-gray-700 dark:text-gray-200">
      <li v-for="step in manualSteps" :key="step">{{ step }}</li>
    </ol>

    <div class="mt-3 text-right">
      <RouterLink
        to="/install"
        class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
      >
        View full install guide
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

const DISMISS_KEY = 'pwa-install-dismissed'

const deferredPrompt = ref(null)
const isIOS = ref(false)
const isStandalone = ref(false)
const isDismissed = ref(false)

const checkStandalone = () => {
  const byDisplayMode = window.matchMedia('(display-mode: standalone)').matches
  const byIOS = window.navigator.standalone === true
  isStandalone.value = byDisplayMode || byIOS
}

const detectPlatform = () => {
  const userAgent = window.navigator.userAgent.toLowerCase()
  isIOS.value = /iphone|ipad|ipod/.test(userAgent)
}

const syncDeferredPrompt = () => {
  deferredPrompt.value = window.__deferredInstallPrompt || null
}

const onAppInstalled = () => {
  deferredPrompt.value = null
  isStandalone.value = true
}

const canUseNativeInstall = computed(() => !!deferredPrompt.value)

const manualSteps = computed(() => {
  if (isIOS.value) {
    return [
      'Tap Share in Safari.',
      'Select Add to Home Screen.',
      'Tap Add to install the app.'
    ]
  }

  return [
    'Open this site in Chrome or Edge.',
    'Click the install icon in the address bar, or open browser menu.',
    'Choose Install app.'
  ]
})

const promptDescription = computed(() => {
  if (canUseNativeInstall.value) {
    return 'Install this app for quick launch, full-screen mode, and better offline support.'
  }

  if (isIOS.value) {
    return 'iOS does not show a direct install button. Use the Safari steps below.'
  }

  return 'If install is not shown, use the browser menu steps below.'
})

const shouldShow = computed(() => !isStandalone.value && !isDismissed.value)

const dismissPrompt = () => {
  isDismissed.value = true
  localStorage.setItem(DISMISS_KEY, '1')
}

const installApp = async () => {
  if (!deferredPrompt.value) {
    return
  }

  deferredPrompt.value.prompt()
  const result = await deferredPrompt.value.userChoice

  if (result.outcome === 'accepted') {
    deferredPrompt.value = null
  }
}

onMounted(() => {
  isDismissed.value = localStorage.getItem(DISMISS_KEY) === '1'
  detectPlatform()
  checkStandalone()
  syncDeferredPrompt()

  window.addEventListener('pwa-install-available', syncDeferredPrompt)
  window.addEventListener('pwa-installed', onAppInstalled)
})

onUnmounted(() => {
  window.removeEventListener('pwa-install-available', syncDeferredPrompt)
  window.removeEventListener('pwa-installed', onAppInstalled)
})
</script>
