<template>
  <button type="button" :aria-label="`Change ${label}: ${selectedLabel}`" aria-haspopup="dialog" class="inline-flex min-h-6 max-w-full items-center gap-1 px-1.5 text-left text-[10px] font-semibold text-slate-900 transition hover:text-teal-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500/40 dark:text-slate-200 dark:hover:text-teal-300" @click="open">
    <span class="truncate" :class="label === 'Status' ? (Number(value) === 1 ? 'rounded-full bg-emerald-50 px-1.5 py-0.5 font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : 'rounded-full bg-red-50 px-1.5 py-0.5 font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-400') : ''">{{ selectedLabel }}</span>
    <svg class="h-3 w-3 shrink-0 text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="m16 3 5 5M4 20l4-1L21 6a2.1 2.1 0 0 0-3-3L5 16l-1 4Z" />
    </svg>
  </button>
  <Teleport to="body">
    <dialog ref="dialog" :aria-label="pendingOption ? `Confirm ${label} change` : `Select ${label}`" class="m-auto w-[calc(100%_-_2rem)] max-w-sm border border-slate-300 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60 backdrop:backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" @click="onBackdropClick" @close="reset">
      <div class="flex items-center justify-between gap-3 border-b border-white/10 bg-[linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] px-4 py-3 text-white">
        <div>
          <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-cyan-200">User details</p>
          <h2 class="text-sm font-semibold">{{ pendingOption ? 'Confirm change' : 'Select ' + label }}</h2>
        </div>
        <button ref="closeButton" type="button" autofocus aria-label="Close selection" class="flex h-7 w-7 shrink-0 items-center justify-center border border-white/15 text-slate-200 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300" @click="dialog.close()">
          <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" /></svg>
        </button>
      </div>
      <template v-if="!pendingOption">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
          <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4 4" /></svg>
            <input v-model="query" type="search" :aria-label="`Search ${label}`" :placeholder="`Search ${label.toLowerCase()}...`" class="h-8 w-full border border-slate-300 bg-white pl-8 pr-2 text-xs focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 dark:border-slate-600 dark:bg-slate-900" />
          </div>
        </div>
        <div class="max-h-64 overflow-y-auto p-2">
          <button v-for="option in filteredOptions" :key="String(option.value)" type="button" :aria-pressed="isSelected(option)" class="flex w-full items-center justify-between gap-3 border-l-2 px-3 py-2.5 text-left text-xs transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cyan-500" :class="isSelected(option) ? 'border-teal-600 bg-teal-50 font-semibold text-teal-800 dark:bg-teal-900/40 dark:text-teal-200' : 'border-transparent text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800'" @click="select(option)">
            <span>{{ option.label }}</span>
            <span v-if="isSelected(option)" class="flex items-center gap-1 text-[10px] text-teal-700 dark:text-teal-300">
              Current
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
            </span>
          </button>
          <p v-if="!filteredOptions.length" class="px-2 py-6 text-center text-xs text-slate-500">No matching options.</p>
        </div>
        <div class="border-t border-slate-200 bg-slate-50 px-4 py-2 text-[10px] text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">Select an option to review your change.</div>
      </template>
      <template v-else>
        <div class="space-y-3 p-4" aria-live="polite">
          <p class="text-xs text-slate-600 dark:text-slate-300">Change {{ label.toLowerCase() }} to the selected value?</p>
          <div class="border border-slate-200 text-xs dark:border-slate-700">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
              <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Current</p>
              <p class="text-slate-700 dark:text-slate-200">{{ selectedLabel }}</p>
            </div>
            <div class="bg-teal-50 px-3 py-2.5 dark:bg-teal-900/30">
              <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-teal-600 dark:text-teal-400">New {{ label }}</p>
              <p class="font-semibold text-teal-800 dark:text-teal-200">{{ pendingOption.label }}</p>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
          <button ref="backButton" type="button" class="h-8 border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-600 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200" @click="back">Back</button>
          <button type="button" class="h-8 border border-cyan-300/30 bg-[linear-gradient(135deg,_#0891b2_0%,_#0f766e_100%)] px-3 text-xs font-semibold text-white hover:bg-[linear-gradient(135deg,_#0e7490_0%,_#115e59_100%)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" @click="confirm">Confirm change</button>
        </div>
      </template>
    </dialog>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number, Boolean], default: '' },
  options: { type: Array, default: () => [] },
})
const emit = defineEmits(['change'])
const dialog = ref(null)
const closeButton = ref(null)
const query = ref('')
const pendingOption = ref(null)
const backButton = ref(null)
const reset = () => { query.value = ''; pendingOption.value = null }
const isSelected = (option) => String(option.value) === String(props.value ?? '')
const selectedLabel = computed(() => props.options.find((option) => String(option.value) === String(props.value ?? ''))?.label || 'Select')
const filteredOptions = computed(() => props.options.filter((option) => option.label.toLowerCase().includes(query.value.trim().toLowerCase())))
const open = () => {
  reset()
  dialog.value.showModal()
  closeButton.value.focus({ preventScroll: true })
}
const select = async (option) => {
  if (isSelected(option)) return
  pendingOption.value = option
  await nextTick()
  backButton.value?.focus({ preventScroll: true })
}
const back = async () => {
  pendingOption.value = null
  await nextTick()
  closeButton.value?.focus({ preventScroll: true })
}
const confirm = () => {
  if (!pendingOption.value) return
  const value = pendingOption.value.value
  dialog.value.close()
  emit('change', value)
}
const onBackdropClick = (event) => {
  if (event.target !== dialog.value) return
  const rect = dialog.value.getBoundingClientRect()
  if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.value.close()
}
</script>
