<script setup>
import { ref, computed, watch } from 'vue'
import Modal from '@/components/common/Modal.vue'

const props = defineProps({
  user:        { type: Object,  required: true },
  machineId:   { type: Number,  required: true },
  machineName: { type: String,  default: 'Machine' },
  loading:     { type: Boolean, default: false },
  enrollmentActive: { type: Boolean, default: false },
  statusText:  { type: String,  default: '' },
  completedFingerIds: { type: Array, default: () => [] },
  activeFingerId: { type: Number, default: null },
  lastCompletedFingerId: { type: Number, default: null },
})

const emit = defineEmits(['close', 'confirm', 'cancel-enrollment'])

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
// Tracks which "last completed" finger the user has already seen/dismissed
// by picking a different finger, so the banner doesn't linger stale.
const acknowledgedFinishedFingerId = ref(null)
watch(() => props.lastCompletedFingerId, (fingerId) => {
  if (fingerId === null) acknowledgedFinishedFingerId.value = null
})

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
  if (props.loading || props.enrollmentActive) return
  selected.value = id
  acknowledgedFinishedFingerId.value = props.lastCompletedFingerId
}

function palmClass() {
  return 'mt-0 flex items-center justify-center'
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
  if (selected.value === null || props.loading || props.enrollmentActive) return
  emit('confirm', { fingerId: selected.value, duress: isDuress.value })
}

function handleCancelClick() {
  if (props.enrollmentActive) {
    emit('cancel-enrollment')
    return
  }
  emit('close')
}
</script>

<template>
  <Modal @close="handleCancelClick">
    <template #body>
      <div class="relative z-[101] mx-4 my-6 w-[95vw] max-w-3xl max-h-[90vh] overflow-y-auto rounded-none border border-slate-300 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">

        <!-- Header -->
        <div class="border-b border-slate-700 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] px-5 py-4 text-white">
          <h3 class="text-lg font-semibold text-white">
            Fingerprint Enrollment
          </h3>
          <p class="mt-1 text-sm text-slate-200">
            Enrolling <span class="font-medium text-white">{{ user?.name ?? 'User' }}</span>
            on <span class="font-medium text-white">{{ machineName }}</span>.
          </p>
          <p class="mt-0.5 text-xs text-slate-300">
            Select a finger, then press Start Enrollment. The device will prompt the user to scan 3 times.
          </p>
        </div>

        <div class="space-y-5 p-5">
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
                    class="absolute -top-2 -right-1 rounded bg-emerald-600 text-white text-[9px] leading-none px-1 py-1 font-bold"
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
                    class="absolute -top-2 -right-1 rounded bg-emerald-600 text-white text-[9px] leading-none px-1 py-1 font-bold"
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

          <div v-if="statusText" class="border border-slate-300 bg-slate-50 px-3 py-2 text-center text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
          {{ statusText }}
          </div>

          <div
          v-if="lastCompletedFingerLabel && acknowledgedFinishedFingerId !== lastCompletedFingerId"
          class="border-2 border-emerald-300 bg-emerald-50 px-4 py-3 text-center shadow-sm dark:border-emerald-700 dark:bg-emerald-900/20"
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
          <label class="flex w-fit cursor-pointer items-center gap-2 border border-slate-300 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <input
            v-model="isDuress"
            type="checkbox"
            class="w-4 h-4 rounded-none border-slate-300 text-cyan-700 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700"
          />
          <span class="text-sm text-slate-600 dark:text-slate-300">Duress Fingerprint</span>
          <span
            class="ml-1 bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
          >Silent alarm</span>
          </label>

          <!-- Actions -->
          <div class="flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
          <button
            type="button"
            class="h-9 border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-cyan-600 hover:bg-cyan-50 hover:text-cyan-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-cyan-950/30"
            @click="handleCancelClick"
          >
            {{ enrollmentActive ? 'Cancel Enrollment' : 'Close' }}
          </button>
          <button
            v-if="!loading && !enrollmentActive"
            type="button"
            :disabled="selected === null"
            :class="[
              'flex h-9 items-center gap-2 border px-4 text-sm font-semibold shadow-sm transition-colors',
              selected !== null
                ? 'border-cyan-300/30 bg-[linear-gradient(135deg,_#0891b2_0%,_#0f766e_100%)] text-white hover:bg-[linear-gradient(135deg,_#0e7490_0%,_#115e59_100%)]'
                : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-600'
            ]"
            @click="confirm"
          >
            Start Enrollment
          </button>
          </div>
        </div>

      </div>
    </template>
  </Modal>
</template>
