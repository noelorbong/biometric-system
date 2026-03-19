<script setup>
import { ref, computed } from 'vue'
import Modal from '@/components/common/Modal.vue'

const props = defineProps({
  user:        { type: Object,  required: true },
  machineId:   { type: Number,  required: true },
  machineName: { type: String,  default: 'Machine' },
  loading:     { type: Boolean, default: false },
  statusText:  { type: String,  default: '' },
  completedFingerIds: { type: Array, default: () => [] },
  activeFingerId: { type: Number, default: null },
  lastCompletedFingerId: { type: Number, default: null },
})

const emit = defineEmits(['close', 'confirm'])

// ── Finger definitions ────────────────────────────────────────────────────────
// Heights give realistic anatomical proportions (px).
//   Left hand displayed L→R: Pinky … Thumb
//   Right hand displayed L→R: Thumb … Pinky
const leftFingers = [
  { id: 0, label: 'Left Pinky',  short: 'P', h: 52 },
  { id: 1, label: 'Left Ring',   short: 'R', h: 68 },
  { id: 2, label: 'Left Middle', short: 'M', h: 80 },
  { id: 3, label: 'Left Index',  short: 'I', h: 74 },
  { id: 4, label: 'Left Thumb',  short: 'T', h: 60 },
]

const rightFingers = [
  { id: 5, label: 'Right Thumb',  short: 'T', h: 60 },
  { id: 6, label: 'Right Index',  short: 'I', h: 74 },
  { id: 7, label: 'Right Middle', short: 'M', h: 80 },
  { id: 8, label: 'Right Ring',   short: 'R', h: 68 },
  { id: 9, label: 'Right Pinky',  short: 'P', h: 52 },
]

const allFingers = [...leftFingers, ...rightFingers]
const unsupportedFingerIds = []

const selected  = ref(null)
const isDuress  = ref(false)

const selectedLabel = computed(() =>
  selected.value !== null
    ? allFingers.find(f => f.id === selected.value)?.label ?? ''
    : null
)

const lastCompletedFingerLabel = computed(() =>
  props.lastCompletedFingerId !== null
    ? allFingers.find(f => f.id === props.lastCompletedFingerId)?.label ?? `Finger ${props.lastCompletedFingerId}`
    : null
)

const completedFingerLabels = computed(() =>
  props.completedFingerIds
    .map((id) => allFingers.find(f => f.id === id)?.label ?? `Finger ${id}`)
)

function fingerClass(id) {
  const base = 'rounded-t-full transition-all duration-200 focus:outline-none flex items-start justify-center relative'
  if (unsupportedFingerIds.includes(id)) {
    return `${base} cursor-not-allowed opacity-50`
  }
  if (props.completedFingerIds.includes(id)) {
    return `${base} cursor-pointer ring-2 ring-emerald-300 shadow-lg shadow-emerald-200/70`
  }
  if (selected.value === id) {
    return `${base} cursor-pointer ring-2 ring-emerald-300`
  }
  return `${base} cursor-pointer`
}

function fingerStyle(id, heightPx) {
  if (props.completedFingerIds.includes(id)) {
    return {
      height: `${heightPx}px`,
      width: '38px',
      backgroundColor: '#10b981',
      borderColor: '#059669',
      borderWidth: '3px',
      borderStyle: 'solid',
      boxShadow: '0 0 0 3px rgba(16,185,129,0.25), 0 8px 18px rgba(5,150,105,0.35)',
    }
  }

  if (props.activeFingerId === id && props.loading) {
    return {
      height: `${heightPx}px`,
      width: '38px',
      backgroundColor: '#f59e0b',
      borderColor: '#d97706',
      borderWidth: '2px',
      borderStyle: 'solid',
      boxShadow: '0 0 0 2px rgba(245,158,11,0.25)',
    }
  }

  if (unsupportedFingerIds.includes(id)) {
    return {
      height: `${heightPx}px`,
      width: '38px',
      backgroundColor: '#e2e8f0',
      borderColor: '#cbd5e1',
      borderWidth: '2px',
      borderStyle: 'solid',
      boxShadow: 'inset 0 0 0 1px rgba(71,85,105,0.25)',
    }
  }

  if (selected.value === id) {
    return {
      height: `${heightPx}px`,
      width: '38px',
      backgroundColor: '#10b981',
      borderColor: '#059669',
      borderWidth: '2px',
      borderStyle: 'solid',
      boxShadow: '0 0 0 2px rgba(16,185,129,0.25)',
    }
  }

  return {
    height: `${heightPx}px`,
    width: '38px',
    backgroundColor: '#93c5fd',
    borderColor: '#2563eb',
    borderWidth: '2px',
    borderStyle: 'solid',
    boxShadow: 'inset 0 0 0 1px rgba(30,41,59,0.2)',
  }
}

function selectFinger(id) {
  if (unsupportedFingerIds.includes(id)) return
  if (props.loading) return
  selected.value = id
}

function palmClass() {
  return 'mt-0 rounded-b-xl flex items-center justify-center'
}

function palmStyle() {
  return {
    width: '202px',
    height: '44px',
    backgroundColor: '#cbd5e1',
    borderLeft: '2px solid #94a3b8',
    borderRight: '2px solid #94a3b8',
    borderBottom: '2px solid #94a3b8',
  }
}

function confirm() {
  if (selected.value === null || props.loading) return
  emit('confirm', { fingerId: selected.value, duress: isDuress.value })
}
</script>

<template>
  <Modal @close="$emit('close')">
    <template #body>
      <div class="relative z-[101] mx-4 my-6 w-[95vw] max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900 space-y-5">

        <!-- Header -->
        <div>
          <h3 class="text-lg font-semibold text-slate-800 dark:text-white">
            Fingerprint Enrollment
          </h3>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Enrolling <span class="font-medium text-slate-700 dark:text-slate-200">{{ user?.name ?? 'User' }}</span>
            on <span class="font-medium text-slate-700 dark:text-slate-200">{{ machineName }}</span>.
          </p>
          <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
            Select a finger, then press Start Enrollment. The device will prompt the user to scan 3 times.
          </p>
        </div>

        <!-- Hand diagram -->
        <div class="flex justify-center gap-8 select-none">

          <!-- Left hand -->
          <div class="flex flex-col items-center gap-1">
            <div class="flex items-end gap-[3px]">
              <div v-for="f in leftFingers" :key="f.id" class="flex flex-col items-center gap-[3px]">
                <div
                  role="button"
                  tabindex="0"
                  :class="fingerClass(f.id)"
                  :style="fingerStyle(f.id, f.h)"
                  :title="f.label"
                  :aria-disabled="unsupportedFingerIds.includes(f.id)"
                  @click="selectFinger(f.id)"
                  @keydown.enter.prevent="selectFinger(f.id)"
                  @keydown.space.prevent="selectFinger(f.id)"
                >
                  <span class="sr-only">{{ f.label }}</span>
                  <span class="mt-1 text-[10px] font-extrabold text-slate-900/80">{{ f.short }}</span>
                  <span
                    v-if="completedFingerIds.includes(f.id)"
                    class="absolute -top-2 -right-1 rounded-full bg-emerald-600 text-white text-[9px] leading-none px-1 py-1 font-bold"
                    :class="{ 'animate-pulse': lastCompletedFingerId === f.id }"
                  >
                    ✓
                  </span>
                </div>
                <span
                  class="text-[9px] font-semibold w-[38px] text-center"
                  :class="completedFingerIds.includes(f.id) ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-400 dark:text-slate-500'"
                >
                  {{ completedFingerIds.includes(f.id) ? 'OK' : f.short }}
                </span>
              </div>
            </div>
            <!-- Palm -->
            <div :class="palmClass()" :style="palmStyle()">
              <span class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-widest">LEFT</span>
            </div>
          </div>

          <!-- Right hand -->
          <div class="flex flex-col items-center gap-1">
            <div class="flex items-end gap-[3px]">
              <div v-for="f in rightFingers" :key="f.id" class="flex flex-col items-center gap-[3px]">
                <div
                  role="button"
                  tabindex="0"
                  :class="fingerClass(f.id)"
                  :style="fingerStyle(f.id, f.h)"
                  :title="f.label"
                  :aria-disabled="unsupportedFingerIds.includes(f.id)"
                  @click="selectFinger(f.id)"
                  @keydown.enter.prevent="selectFinger(f.id)"
                  @keydown.space.prevent="selectFinger(f.id)"
                >
                  <span class="sr-only">{{ f.label }}</span>
                  <span class="mt-1 text-[10px] font-extrabold text-slate-900/80">{{ f.short }}</span>
                  <span
                    v-if="completedFingerIds.includes(f.id)"
                    class="absolute -top-2 -right-1 rounded-full bg-emerald-600 text-white text-[9px] leading-none px-1 py-1 font-bold"
                    :class="{ 'animate-pulse': lastCompletedFingerId === f.id }"
                  >
                    ✓
                  </span>
                </div>
                <span
                  class="text-[9px] font-semibold w-[38px] text-center"
                  :class="completedFingerIds.includes(f.id) ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-400 dark:text-slate-500'"
                >
                  {{ completedFingerIds.includes(f.id) ? 'OK' : f.short }}
                </span>
              </div>
            </div>
            <!-- Palm -->
            <div :class="palmClass()" :style="palmStyle()">
              <span class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-widest">RIGHT</span>
            </div>
          </div>

        </div>

        <!-- Selection indicator -->
        <div class="h-6 flex items-center justify-center">
          <span v-if="selectedLabel" class="text-sm font-medium text-emerald-600 dark:text-emerald-400">
            Selected: {{ selectedLabel }}
          </span>
          <span v-else class="text-sm text-slate-400 dark:text-slate-500">
            No finger selected
          </span>
        </div>

        <div v-if="statusText" class="text-xs text-center rounded-lg border border-slate-200 bg-slate-50 text-slate-600 px-3 py-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
          {{ statusText }}
        </div>

        <div
          v-if="lastCompletedFingerLabel"
          class="rounded-xl border-2 border-emerald-300 bg-emerald-50 px-4 py-3 text-center shadow-sm dark:border-emerald-700 dark:bg-emerald-900/20"
        >
          <p class="text-sm font-extrabold text-emerald-700 dark:text-emerald-300 tracking-wide">
            Registration Finished
          </p>
          <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
            {{ lastCompletedFingerLabel }} enrolled and saved.
          </p>
        </div>

        <div class="text-xs text-center text-slate-500 dark:text-slate-400">
          <span v-if="completedFingerIds.length">
            Completed fingers: {{ completedFingerLabels.join(', ') }}
          </span>
          <span v-else>
            No completed fingers yet.
          </span>
        </div>

        <!-- <p class="text-xs text-amber-600 dark:text-amber-400 text-center">
          Note: Right Pinky is unavailable on this machine firmware.
        </p> -->

        <!-- Duress fingerprint -->
        <label class="flex items-center gap-2 cursor-pointer w-fit">
          <input
            v-model="isDuress"
            type="checkbox"
            class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700"
          />
          <span class="text-sm text-slate-600 dark:text-slate-300">Duress Fingerprint</span>
          <span
            class="ml-1 text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 px-1.5 py-0.5 rounded font-medium"
          >Silent alarm</span>
        </label>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-1">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium rounded-lg border border-slate-300 text-slate-700 bg-white hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors"
            @click="$emit('close')"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="selected === null || loading"
            :class="[
              'px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2',
              selected !== null && !loading
                ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                : 'bg-emerald-200 text-emerald-400 dark:bg-emerald-900/30 dark:text-emerald-600 cursor-not-allowed'
            ]"
            @click="confirm"
          >
            <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Start Enrollment
          </button>
        </div>

      </div>
    </template>
  </Modal>
</template>
