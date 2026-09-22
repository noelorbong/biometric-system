<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/common/Modal.vue'
import ModalDelete from '@/components/common/ModalDelete.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@/icons'
import { useHolidayStore } from '@/store/HolidayStore'

const holidayStore = useHolidayStore()
const { holidays } = storeToRefs(holidayStore)
const search = ref('')
const isModalOpen = ref(false)
const isDeleteModal = ref(false)
const isEdit = ref(false)
const selectedHoliday = ref(null)
const form = ref({ id: null, name: '', holiday_date: '', type: 'regular', duration: 'full_day', is_working_day: false, notes: '' })
const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true })

const filteredHolidays = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return holidays.value
  return holidays.value.filter((holiday) => [holiday.name, holiday.holiday_date, holiday.type, holiday.duration]
    .some((value) => String(value || '').toLowerCase().includes(term)))
})

const typeLabel = (type) => ({ regular: 'Regular', special_non_working: 'Special Non-working', special_working: 'Special Working' }[type] || type)
const durationLabel = (duration) => ({ full_day: 'Full Day', morning: 'Morning', afternoon: 'Afternoon' }[duration] || duration)
const toast = (title, icon = 'success') => Toast.fire({ title, icon })

const openCreate = () => {
  isEdit.value = false
  form.value = { id: null, name: '', holiday_date: '', type: 'regular', duration: 'full_day', is_working_day: false, notes: '' }
  isModalOpen.value = true
}
const openEdit = (holiday) => {
  isEdit.value = true
  form.value = { ...holiday, is_working_day: Boolean(holiday.is_working_day), notes: holiday.notes || '' }
  isModalOpen.value = true
}
const openDelete = (holiday) => { selectedHoliday.value = holiday; isDeleteModal.value = true }
const saveHoliday = async () => {
  const result = isEdit.value ? await holidayStore.updateHoliday(form.value) : await holidayStore.storeHoliday(form.value)
  if (!result.success) return toast(result.data?.response?.data?.message || 'Unable to save holiday', 'error')
  isModalOpen.value = false
  toast(isEdit.value ? 'Holiday updated' : 'Holiday created')
}
const deleteHoliday = async () => {
  if (!selectedHoliday.value) return
  const result = await holidayStore.deleteHoliday(selectedHoliday.value.id)
  if (!result.success) return toast(result.data?.response?.data?.message || 'Unable to delete holiday', 'error')
  isDeleteModal.value = false
  selectedHoliday.value = null
  toast('Holiday removed')
}

onMounted(async () => {
  const result = await holidayStore.loadHolidays()
  if (!result.success) toast('Unable to load holidays', 'error')
})
</script>

<template>
  <div class="space-y-3">
    <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-col gap-2 min-[800px]:flex-row min-[800px]:items-center min-[800px]:justify-between">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
          <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Holidays</h1>
          <span class="text-xs text-slate-500 dark:text-slate-400">{{ holidays.length }} configured</span>
        </div>
        <Button @click="openCreate" :className="'h-9 whitespace-nowrap border border-sky-200 bg-sky-50 px-2.5 text-xs text-sky-700 hover:bg-sky-100 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300'" size="sm" variant="primary" :startIcon="PlusIcon">Add Holiday</Button>
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <input v-model="search" type="search" placeholder="Search holiday..." class="h-9 w-full rounded-md border border-slate-300 bg-transparent px-3 text-xs text-slate-800 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500/20 dark:border-slate-700 dark:text-white/90" />
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="bg-slate-50 dark:bg-slate-900/60"><tr>
            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Date</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Holiday</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Type</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Duration</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Attendance</th>
            <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">Actions</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr v-for="holiday in filteredHolidays" :key="holiday.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <td class="whitespace-nowrap px-3 py-1.5 text-xs font-medium text-slate-800 dark:text-slate-100">{{ holiday.holiday_date }}</td>
              <td class="px-3 py-1.5 text-xs text-slate-700 dark:text-slate-200">{{ holiday.name }}</td>
              <td class="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300">{{ typeLabel(holiday.type) }}</td>
              <td class="px-3 py-1.5"><span class="rounded bg-sky-50 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">{{ durationLabel(holiday.duration) }}</span></td>
              <td class="px-3 py-1.5"><span class="rounded px-1.5 py-0.5 text-[10px] font-semibold" :class="holiday.is_working_day ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'">{{ holiday.is_working_day ? 'Working day' : 'Non-working' }}</span></td>
              <td class="px-3 py-1.5"><div class="flex justify-end gap-1.5"><button @click="openEdit(holiday)" type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-sky-200 text-sky-600 hover:bg-sky-50 dark:border-sky-800/60 dark:text-sky-300"><PencilIcon /></button><button @click="openDelete(holiday)" type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-800/60 dark:text-rose-300"><TrashIcon /></button></div></td>
            </tr>
            <tr v-if="!filteredHolidays.length"><td colspan="6" class="px-3 py-5 text-center text-xs text-slate-500">No holidays found.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <Modal v-if="isModalOpen" @close="isModalOpen = false"><template #body><div class="relative m-2 w-full max-w-lg rounded-xl bg-white p-4 dark:bg-gray-900"><h4 class="text-base font-semibold text-slate-900 dark:text-white">{{ isEdit ? 'Update Holiday' : 'Add Holiday' }}</h4><div class="mt-3 grid gap-3 sm:grid-cols-2"><label class="sm:col-span-2 text-xs font-medium text-slate-700 dark:text-slate-300">Name<input v-model="form.name" type="text" class="mt-1 h-9 w-full rounded-md border border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700" /></label><label class="text-xs font-medium text-slate-700 dark:text-slate-300">Date<input v-model="form.holiday_date" type="date" class="mt-1 h-9 w-full rounded-md border border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700" /></label><label class="text-xs font-medium text-slate-700 dark:text-slate-300">Type<select v-model="form.type" class="mt-1 h-9 w-full rounded-md border border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700"><option value="regular">Regular</option><option value="special_non_working">Special Non-working</option><option value="special_working">Special Working</option></select></label><label class="text-xs font-medium text-slate-700 dark:text-slate-300">Duration<select v-model="form.duration" class="mt-1 h-9 w-full rounded-md border border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700"><option value="full_day">Full Day</option><option value="morning">Morning</option><option value="afternoon">Afternoon</option></select></label><label class="flex items-end gap-2 pb-2 text-xs font-medium text-slate-700 dark:text-slate-300"><input v-model="form.is_working_day" type="checkbox" class="h-3.5 w-3.5" /> Working day</label><label class="sm:col-span-2 text-xs font-medium text-slate-700 dark:text-slate-300">Notes<textarea v-model="form.notes" rows="2" class="mt-1 w-full rounded-md border border-slate-300 bg-transparent px-2 py-1.5 text-xs dark:border-slate-700" /></label></div><div class="mt-4 flex justify-end gap-2"><button type="button" @click="isModalOpen = false" class="h-8 rounded-md border border-slate-300 px-2.5 text-xs dark:border-slate-700">Cancel</button><button type="button" @click="saveHoliday" class="h-8 rounded-md bg-sky-600 px-2.5 text-xs font-medium text-white hover:bg-sky-500">Save</button></div></div></template></Modal>
    <ModalDelete v-if="isDeleteModal" head="Holiday" :data="selectedHoliday" :text="selectedHoliday?.name || ''" @close="isDeleteModal = false" @delete="deleteHoliday" />
  </div>
</template>