<template>
  <section class="min-h-screen bg-slate-50 px-4 py-10 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="mx-auto max-w-4xl">
      <div class="mb-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <h1 class="text-2xl font-bold">Install Biometric System</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
          Use these steps to install the app on Android, desktop, and iOS.
        </p>
        <button
          v-if="canInstallNow"
          type="button"
          class="mt-4 inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black dark:bg-white dark:text-black dark:hover:bg-slate-200"
          @click="installApp"
        >
          Install App Now
        </button>
        <p v-else class="mt-3 text-xs text-slate-500 dark:text-slate-400">
          If the install button is not available yet, refresh once and open this site in Chrome, Edge, or Safari (iOS).
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
          <h2 class="text-lg font-semibold">Android (Chrome)</h2>
          <ol class="mt-3 list-decimal space-y-2 pl-4 text-sm text-slate-700 dark:text-slate-300">
            <li>Open the app URL in Chrome.</li>
            <li>Tap the Install App prompt, or open the browser menu.</li>
            <li>Tap Install app, then confirm.</li>
          </ol>
        </article>

        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
          <h2 class="text-lg font-semibold">Desktop (Chrome / Edge)</h2>
          <ol class="mt-3 list-decimal space-y-2 pl-4 text-sm text-slate-700 dark:text-slate-300">
            <li>Open the app URL in Chrome or Edge.</li>
            <li>Click the install icon in the address bar.</li>
            <li>Click Install to pin the app as a desktop application.</li>
          </ol>
        </article>

        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
          <h2 class="text-lg font-semibold">iOS (Safari)</h2>
          <ol class="mt-3 list-decimal space-y-2 pl-4 text-sm text-slate-700 dark:text-slate-300">
            <li>Open the app URL in Safari.</li>
            <li>Tap the Share button.</li>
            <li>Select Add to Home Screen, then tap Add.</li>
          </ol>
        </article>
      </div>

      <div class="mt-6 rounded-2xl bg-white p-5 text-sm text-slate-700 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-800">
        After installation, launch the app from your home screen, app drawer, or desktop to use standalone mode.
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'

const deferredPrompt = ref(null)

const syncDeferredPrompt = () => {
  deferredPrompt.value = window.__deferredInstallPrompt || null
}

const canInstallNow = computed(() => !!deferredPrompt.value)

const installApp = async () => {
  if (!deferredPrompt.value) {
    return
  }

  deferredPrompt.value.prompt()
  await deferredPrompt.value.userChoice
  deferredPrompt.value = null
}

onMounted(() => {
  syncDeferredPrompt()
  window.addEventListener('pwa-install-available', syncDeferredPrompt)
  window.addEventListener('pwa-installed', syncDeferredPrompt)
})

onUnmounted(() => {
  window.removeEventListener('pwa-install-available', syncDeferredPrompt)
  window.removeEventListener('pwa-installed', syncDeferredPrompt)
})
</script>
