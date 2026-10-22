<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import { isDesktop } from '@/utils/printPreview'
const desktop = isDesktop()
const version = ref('')
onMounted(async () => {
  if (!desktop) return
  try { version.value = (await axios.get('/api/desktop/version')).data.version } catch { /* Retry via update dialog. */ }
})
const check = () => window.dispatchEvent(new Event('desktop-check-updates'))
</script>
<template>
  <section v-if="desktop" class="rounded-xl border border-slate-200 bg-white p-4 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="font-semibold">Biometric System <span v-if="version">v{{ version }}</span><span v-else>— version unavailable</span></p>
      <button type="button" @click="check" class="rounded-md bg-sky-700 px-3 py-2 text-white">Check for updates</button>
    </div>
    <p class="mt-2 text-xs">Choose Download &amp; install to update. Installation starts automatically after the download completes.</p>
  </section>
</template>
