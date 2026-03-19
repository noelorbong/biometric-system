<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/common/Modal.vue'
import ModalDelete from '@/components/common/ModalDelete.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@/icons'
import { useOfficeShiftStore } from '@/store/OfficeShiftStore'

const officeShiftStore = useOfficeShiftStore()
const { officeShifts } = storeToRefs(officeShiftStore)
const search = ref('')
const isModalOpen = ref(false)
const isDeleteModal = ref(false)
const isEdit = ref(false)
const selectedShift = ref(null)

const form = ref({
  id: null,
  name: '',
  is_flexible: false,
  schedules: [{ time_in: '', time_out: '', is_next_day: false }],
})

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 1500,
  timerProgressBar: true,
})

const filteredOfficeShifts = computed(() => {
  const term = search.value.toLowerCase().trim()
  if (!term) {
    return officeShifts.value
  }

  return officeShifts.value.filter((item) => {
    return (
      (item.name || '').toLowerCase().includes(term) ||
      (item.schedule || '').toLowerCase().includes(term)
    )
  })
})

const totalShifts = computed(() => officeShifts.value.length)
const fixedShifts = computed(() => officeShifts.value.filter((item) => !item.is_flexible).length)
const flexibleShifts = computed(() => officeShifts.value.filter((item) => item.is_flexible).length)
const assignedUsersTotal = computed(() => {
  return officeShifts.value.reduce((sum, item) => sum + Number(item.users_count || 0), 0)
})

const toastResult = (message, icon = 'success') => {
  Toast.fire({
    icon,
    title: message,
  })
}

const loadOfficeShifts = async () => {
  const resp = await officeShiftStore.loadOfficeShifts()
  if (!resp.success) {
    toastResult('Unable to load office shifts', 'error')
  }
}

const openCreate = () => {
  isEdit.value = false
  form.value = {
    id: null,
    name: '',
    is_flexible: false,
    schedules: [{ time_in: '', time_out: '', is_next_day: false }],
  }
  isModalOpen.value = true
}

const openEdit = (shift) => {
  isEdit.value = true
  form.value = {
    id: shift.id,
    name: shift.name || '',
    is_flexible: Boolean(shift.is_flexible),
    schedules: shift.schedules?.length
      ? shift.schedules.map((row) => ({
          time_in: (row.time_in || '').slice(0, 5),
          time_out: (row.time_out || '').slice(0, 5),
          is_next_day: Boolean(row.is_next_day),
        }))
      : [{ time_in: '', time_out: '', is_next_day: false }],
  }
  isModalOpen.value = true
}

const openDelete = (shift) => {
  selectedShift.value = shift
  isDeleteModal.value = true
}

const saveShift = async () => {
  const schedules = (form.value.schedules || []).filter((row) => row.time_in && row.time_out)

  const payload = {
    id: form.value.id,
    name: form.value.name,
    is_flexible: form.value.is_flexible,
    schedules: form.value.is_flexible ? [] : schedules,
  }

  const resp = isEdit.value
    ? await officeShiftStore.updateOfficeShift(payload)
    : await officeShiftStore.storeOfficeShift(payload)

  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to save office shift', 'error')
    return
  }

  toastResult(isEdit.value ? 'Office shift updated' : 'Office shift created')
  isModalOpen.value = false
}

const addScheduleRow = () => {
  form.value.schedules.push({ time_in: '', time_out: '', is_next_day: false })
}

const removeScheduleRow = (index) => {
  if (form.value.schedules.length === 1) {
    form.value.schedules = [{ time_in: '', time_out: '', is_next_day: false }]
    return
  }

  form.value.schedules.splice(index, 1)
}

const deleteShift = async () => {
  if (!selectedShift.value) {
    return
  }

  const resp = await officeShiftStore.deleteOfficeShift({ id: selectedShift.value.id })

  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to delete office shift', 'error')
    return
  }

  toastResult('Office shift removed')
  isDeleteModal.value = false
  selectedShift.value = null
}

onMounted(async () => {
  await loadOfficeShifts()
})

const formatScheduleChip = (row) => {
  const toLabel = (timeValue) => {
    if (!timeValue) {
      return '--:--'
    }

    const [h, m] = String(timeValue).split(':')
    const hour = Number(h)
    const minute = Number(m)
    if (Number.isNaN(hour) || Number.isNaN(minute)) {
      return String(timeValue)
    }

    const suffix = hour >= 12 ? 'PM' : 'AM'
    const normalizedHour = hour % 12 === 0 ? 12 : hour % 12
    return `${normalizedHour}:${String(minute).padStart(2, '0')}${suffix}`
  }

  const timeIn = toLabel(row?.time_in)
  const timeOut = toLabel(row?.time_out)
  return row?.is_next_day ? `${timeIn} - ${timeOut} (+1)` : `${timeIn} - ${timeOut}`
}
</script>

<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] p-6 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
      <div
        class="pointer-events-none absolute inset-0 opacity-10"
        style="background-image: radial-gradient(circle, rgba(255,255,255,0.5) 1px, transparent 1px); background-size: 24px 24px;"
      ></div>
      <div class="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-3xl font-semibold leading-tight">Office Shift</h1>
          <p class="mt-1 text-sm text-slate-200/90">Manage shift templates, schedules, and user assignments.</p>
          <div class="mt-3 inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-100 ring-1 ring-inset ring-white/10">
            Shift Operations Dashboard
          </div>
        </div>
        <Button @click="openCreate" :className="'h-11 whitespace-nowrap text-nowrap border border-sky-300/30 bg-sky-400/20 text-sky-50 hover:bg-sky-400/30'" size="sm" variant="primary" :startIcon="PlusIcon">
          Add Office Shift
        </Button>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Shifts</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ totalShifts }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Fixed Shifts</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ fixedShifts }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Flexible Shifts</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ flexibleShifts }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Assigned Users</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ assignedUsersTotal }}</p>
      </article>
    </section>

    <section class="rounded-[24px] border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-col gap-3 md:flex-row md:items-center">
        <div class="relative flex-1">
          <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 105.3 5.3a7.5 7.5 0 0011.35 11.35z" />
          </svg>
          <input
            v-model="search"
            type="text"
            placeholder="Search office shift..."
            class="h-11 w-full rounded-lg border border-slate-300 bg-transparent pl-9 pr-4 text-sm text-slate-800 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:text-white/90"
          />
        </div>
        <Button @click="openCreate" :className="'h-11 whitespace-nowrap text-nowrap border border-sky-200 bg-sky-50 px-4 text-sky-700 hover:bg-sky-100 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/30'" size="sm" variant="primary" :startIcon="PlusIcon">
          New Shift
        </Button>
      </div>
    </section>

    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="max-w-full overflow-x-auto custom-scrollbar">
        <table class="min-w-full">
          <thead class="bg-slate-50 dark:bg-slate-900/60">
            <tr>
              <th class="px-5 py-3 text-left text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Name</th>
              <th class="px-5 py-3 text-left text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Schedule</th>
              <th class="px-5 py-3 text-left text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Type</th>
              <th class="px-5 py-3 text-left text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Assigned Users</th>
              <th class="px-5 py-3 text-right text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr v-for="shift in filteredOfficeShifts" :key="shift.id" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <td class="px-5 py-3 text-sm font-medium text-slate-800 dark:text-slate-100">{{ shift.name }}</td>
              <td class="px-5 py-3 text-sm text-slate-600 dark:text-slate-300">
                <div v-if="shift.schedules?.length" class="flex flex-wrap gap-1.5">
                  <span
                    v-for="row in shift.schedules"
                    :key="`shift-${shift.id}-row-${row.sequence}`"
                    class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 dark:border-sky-900/50 dark:bg-sky-900/20 dark:text-sky-300"
                  >
                    {{ formatScheduleChip(row) }}
                  </span>
                </div>
                <span v-else>{{ shift.schedule || 'Flexible Time' }}</span>
              </td>
              <td class="px-5 py-3 text-sm text-slate-600 dark:text-slate-300">
                <span
                  :class="shift.is_flexible
                    ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-400/10 dark:text-amber-300'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-400/10 dark:text-emerald-300'"
                  class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                >
                  {{ shift.is_flexible ? 'Flexible' : 'Fixed' }}
                </span>
              </td>
              <td class="px-5 py-3 text-sm font-medium text-slate-600 dark:text-slate-300">{{ shift.users_count || 0 }}</td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-2">
                  <button @click="openEdit(shift)" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-sky-200 text-sky-600 transition hover:bg-sky-50 dark:border-sky-800/60 dark:text-sky-300 dark:hover:bg-sky-900/20"><PencilIcon /></button>
                  <button @click="openDelete(shift)" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 text-rose-600 transition hover:bg-rose-50 dark:border-rose-800/60 dark:text-rose-300 dark:hover:bg-rose-900/20"><TrashIcon /></button>
                </div>
              </td>
            </tr>
            <tr v-if="!filteredOfficeShifts.length">
              <td colspan="5" class="px-5 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                No office shifts found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Modal v-if="isModalOpen" @close="isModalOpen = false">
      <template #body>
        <div class="no-scrollbar relative w-full max-w-[700px] max-h-[90vh] overflow-y-auto m-2 rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-7">
          <div class="mb-4 rounded-2xl bg-[linear-gradient(135deg,_#0f172a_0%,_#1e293b_45%,_#0f766e_100%)] px-4 py-3 text-white">
            <h4 class="text-xl font-semibold">
              {{ isEdit ? 'Update Office Shift' : 'Add Office Shift' }}
            </h4>
            <p class="mt-0.5 text-xs text-white/85">Configure schedule rows and shift behavior.</p>
          </div>

          <div class="space-y-3">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name</label>
              <input v-model="form.name" type="text" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
            </div>

            <div>
              <div class="mb-1.5 flex items-center justify-between">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Schedule Rows</label>
                <button type="button" @click="addScheduleRow" :disabled="form.is_flexible" class="rounded-md border border-brand-300 bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-600 disabled:opacity-50 dark:bg-brand-500/10">
                  Add Row
                </button>
              </div>

              <div class="space-y-2">
                <div v-for="(row, idx) in form.schedules" :key="`schedule-${idx}`" class="grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 sm:grid-cols-12 dark:border-gray-700 dark:bg-gray-900/40">
                  <div class="sm:col-span-4">
                    <label class="mb-1 block text-xs text-gray-600 dark:text-gray-300">Time In</label>
                    <input v-model="row.time_in" :disabled="form.is_flexible" type="time" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm disabled:opacity-50 dark:bg-gray-950" />
                  </div>
                  <div class="sm:col-span-4">
                    <label class="mb-1 block text-xs text-gray-600 dark:text-gray-300">Time Out</label>
                    <input v-model="row.time_out" :disabled="form.is_flexible" type="time" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm disabled:opacity-50 dark:bg-gray-950" />
                  </div>
                  <div class="sm:col-span-3 flex items-end">
                    <label class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                      <input v-model="row.is_next_day" :disabled="form.is_flexible" type="checkbox" class="h-4 w-4" />
                      Next Day
                    </label>
                  </div>
                  <div class="sm:col-span-1 flex items-end justify-end">
                    <button type="button" @click="removeScheduleRow(idx)" :disabled="form.is_flexible" class="rounded-md border border-red-300 bg-red-50 px-2 py-1 text-xs font-semibold text-red-600 disabled:opacity-50 dark:bg-red-500/10">
                      Remove
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-800/40 dark:bg-amber-400/10">
              <label class="inline-flex items-center gap-2 text-sm font-medium text-amber-700 dark:text-amber-300">
                <input v-model="form.is_flexible" type="checkbox" class="h-4 w-4" />
                Flexible Time
              </label>
              <p class="mt-1 text-xs text-amber-700/80 dark:text-amber-300/80">When enabled, schedule rows are ignored and users can clock in/out at any time.</p>
            </div>
          </div>

          <div class="mt-5 flex items-center gap-3 lg:justify-end">
            <button @click="isModalOpen = false" type="button" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto">Close</button>
            <button @click="saveShift" type="button" class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">Save</button>
          </div>
        </div>
      </template>
    </Modal>

    <ModalDelete
      v-if="isDeleteModal"
      head="Office Shift"
      :data="selectedShift"
      :text="selectedShift?.name || ''"
      @close="isDeleteModal = false"
      @delete="deleteShift"
    />
  </div>
</template>
