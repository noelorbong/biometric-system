<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/common/Modal.vue'
import ModalDelete from '@/components/common/ModalDelete.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@/icons'
import { useDepartmentStore } from '@/store/DepartmentStore'

const departmentStore = useDepartmentStore()
const { departments } = storeToRefs(departmentStore)
const search = ref('')
const isModalOpen = ref(false)
const isDeleteModal = ref(false)
const isEdit = ref(false)
const selectedDepartment = ref(null)

const form = ref({
  id: null,
  department_name: '',
  dep_long: '',
  dep_short: '',
  status: true,
})

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 1500,
  timerProgressBar: true,
})

const filteredDepartments = computed(() => {
  const term = search.value.toLowerCase().trim()
  if (!term) {
    return departments.value
  }

  return departments.value.filter((item) => {
    return (
      (item.department_name || '').toLowerCase().includes(term) ||
      (item.dep_long || '').toLowerCase().includes(term) ||
      (item.dep_short || '').toLowerCase().includes(term)
    )
  })
})

const totalDepartments = computed(() => departments.value.length)
const activeDepartments = computed(() => departments.value.filter((item) => Boolean(item.status)).length)
const inactiveDepartments = computed(() => departments.value.filter((item) => !item.status).length)
const assignedUsersTotal = computed(() => {
  return departments.value.reduce((sum, item) => sum + Number(item.users_count || 0), 0)
})

const toastResult = (message, icon = 'success') => {
  Toast.fire({
    icon,
    title: message,
  })
}

const loadDepartments = async () => {
  const resp = await departmentStore.loadDepartments()
  if (!resp.success) {
    toastResult('Unable to load departments', 'error')
  }
}

const openCreate = () => {
  isEdit.value = false
  form.value = {
    id: null,
    department_name: '',
    dep_long: '',
    dep_short: '',
    status: true,
  }
  isModalOpen.value = true
}

const openEdit = (department) => {
  isEdit.value = true
  form.value = {
    id: department.id,
    department_name: department.department_name || '',
    dep_long: department.dep_long || '',
    dep_short: department.dep_short || '',
    status: Boolean(department.status),
  }
  isModalOpen.value = true
}

const openDelete = (department) => {
  selectedDepartment.value = department
  isDeleteModal.value = true
}

const saveDepartment = async () => {
  const payload = {
    id: form.value.id,
    department_name: form.value.department_name,
    dep_long: form.value.dep_long || null,
    dep_short: form.value.dep_short || null,
    status: Boolean(form.value.status),
  }

  const resp = isEdit.value
    ? await departmentStore.updateDepartment(payload)
    : await departmentStore.storeDepartment(payload)

  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to save department', 'error')
    return
  }

  toastResult(isEdit.value ? 'Department updated' : 'Department created')
  isModalOpen.value = false
}

const deleteDepartment = async () => {
  if (!selectedDepartment.value) {
    return
  }

  const resp = await departmentStore.deleteDepartment({ id: selectedDepartment.value.id })

  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to delete department', 'error')
    return
  }

  toastResult('Department removed')
  isDeleteModal.value = false
  selectedDepartment.value = null
}

onMounted(async () => {
  await loadDepartments()
})
</script>

<template>
  <div class="space-y-3">
    <div class="admin-page-header dark -ml-4 -mt-4 md:-ml-6 md:-mt-6 sticky px-2 py-1.5 top-0 z-20 -mr-4 border pb-2 shadow-sm md:-mr-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0 space-y-1">
          <h1 class="text-base font-semibold text-white">Departments</h1>
          <section class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-[10px]">
        <article class="flex items-baseline gap-1.5">
          <p class="font-medium text-slate-500 dark:text-slate-400">Total</p>
          <p class="font-semibold text-slate-900 dark:text-white">{{ totalDepartments }}</p>
        </article>
        <article class="flex items-baseline gap-1.5">
          <p class="font-medium text-slate-500 dark:text-slate-400">Active</p>
          <p class="font-semibold text-emerald-700 dark:text-emerald-300">{{ activeDepartments }}</p>
        </article>
        <article class="flex items-baseline gap-1.5">
          <p class="font-medium text-slate-500 dark:text-slate-400">Inactive</p>
          <p class="font-semibold text-slate-700 dark:text-slate-200">{{ inactiveDepartments }}</p>
        </article>
        <article class="flex items-baseline gap-1.5">
          <p class="font-medium text-slate-500 dark:text-slate-400">Assigned Users</p>
          <p class="font-semibold text-slate-900 dark:text-white">{{ assignedUsersTotal }}</p>
        </article>
      </section>
        </div>
        <div class="ml-auto flex min-w-0 flex-1 basis-[300px] items-stretch sm:max-w-[380px]">
          <div class="relative min-w-0 flex-1">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4 4" /></svg>
            <input v-model="search" type="search" aria-label="Search departments" placeholder="Search department..." class="h-7 w-full min-w-0 rounded-none border border-r-0 border-white/15 bg-slate-950/30 py-1 pl-8 pr-2 text-[11px] text-white outline-none placeholder:text-slate-400 focus:border-cyan-300/60 focus:ring-2 focus:ring-cyan-300/30" />
          </div>
          <button type="button" @click="openCreate" class="inline-flex h-7 shrink-0 items-center justify-center gap-1 whitespace-nowrap border border-cyan-300/30 bg-[linear-gradient(135deg,_#0891b2_0%,_#0f766e_100%)] px-2 text-[11px] font-semibold text-white shadow-sm hover:bg-[linear-gradient(135deg,_#0e7490_0%,_#115e59_100%)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/40">
            <PlusIcon class="h-3 w-3" /> New Department
          </button>
        </div>
      </div>
    </div>

    <section class="overflow-hidden border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="hidden max-w-full overflow-x-auto custom-scrollbar md:block">
        <table class="min-w-full">
          <thead class="bg-[linear-gradient(135deg,_#0f172a_0%,_#1e293b_50%,_#0891b2_100%)]">
            <tr>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-100">Department Name</th>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-100">Department Long</th>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-100">Short Name</th>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-100">Status</th>
              <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-100">Assigned Users</th>
              <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-100">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr v-for="department in filteredDepartments" :key="department.id" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <td class="px-3 py-1.5 text-xs font-medium text-slate-800 dark:text-slate-100">{{ department.department_name }}</td>
              <td class="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300">{{ department.dep_long || '-' }}</td>
              <td class="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300">{{ department.dep_short || '-' }}</td>
              <td class="px-3 py-1.5 text-xs">
                <span
                  class="inline-flex rounded px-2 py-0.5 text-[10px] font-semibold"
                  :class="department.status
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                    : 'bg-slate-100 text-slate-600 dark:bg-slate-700/70 dark:text-slate-300'"
                >
                  {{ department.status ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">{{ department.users_count || 0 }}</td>
              <td class="px-3 py-1.5">
                <div class="flex items-center justify-end gap-1.5">
                  <button @click="openEdit(department)" type="button" class="inline-flex h-7 w-7 items-center justify-center rounded border border-cyan-200 bg-white text-cyan-700 transition hover:bg-cyan-50 dark:border-cyan-800/60 dark:bg-slate-900 dark:text-cyan-300 dark:hover:bg-cyan-900/20"><PencilIcon /></button>
                  <button @click="openDelete(department)" type="button" class="inline-flex h-7 w-7 items-center justify-center rounded border border-rose-200 bg-white text-rose-600 transition hover:bg-rose-50 dark:border-rose-800/60 dark:bg-slate-900 dark:text-rose-300 dark:hover:bg-rose-900/20"><TrashIcon /></button>
                </div>
              </td>
            </tr>
            <tr v-if="!filteredDepartments.length">
              <td colspan="6" class="px-3 py-5 text-center text-xs text-slate-500 dark:text-slate-400">
                No departments found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class=" md:hidden">
        <article
          v-for="department in filteredDepartments"
          :key="`mobile-department-${department.id}`"
          class="rounded-lg border border-slate-200 bg-white p-2.5 shadow-sm dark:border-slate-700 dark:bg-slate-900/70"
        >
          <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
              <h3 class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ department.department_name }}</h3>
              <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ department.dep_long || '-' }} · {{ department.dep_short || '-' }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
              <button @click="openEdit(department)" type="button" aria-label="Edit department" class="inline-flex h-8 w-8 items-center justify-center rounded border border-cyan-200 bg-white text-cyan-700 transition hover:bg-cyan-50 dark:border-cyan-800/60 dark:bg-slate-900 dark:text-cyan-300 dark:hover:bg-cyan-900/20"><PencilIcon /></button>
              <button @click="openDelete(department)" type="button" aria-label="Delete department" class="inline-flex h-8 w-8 items-center justify-center rounded border border-rose-200 bg-white text-rose-600 transition hover:bg-rose-50 dark:border-rose-800/60 dark:bg-slate-900 dark:text-rose-300 dark:hover:bg-rose-900/20"><TrashIcon /></button>
            </div>
          </div>
          <div class="mt-2 flex items-center justify-between gap-2 text-[10px] font-semibold">
            <span
              class="rounded px-2 py-0.5"
              :class="department.status ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700/70 dark:text-slate-300'"
            >
              {{ department.status ? 'Active' : 'Inactive' }}
            </span>
            <span class="text-slate-500 dark:text-slate-400">{{ department.users_count || 0 }} assigned users</span>
          </div>
        </article>
        <div v-if="!filteredDepartments.length" class="px-3 py-5 text-center text-xs text-slate-500 dark:text-slate-400">
          No departments found.
        </div>
      </div>
    </section>

    <Modal v-if="isModalOpen" @close="isModalOpen = false">
      <template #body>
        <div class="no-scrollbar relative m-2 w-full max-w-[700px] max-h-[90vh] overflow-y-auto border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-800 dark:bg-gray-900 lg:p-6">
          <div class="-m-4 mb-4 bg-[linear-gradient(135deg,_#0f172a_0%,_#1e293b_45%,_#0891b2_100%)] px-4 py-4 text-white lg:-m-6 lg:mb-5 lg:px-6">
            <h4 class="text-xl font-semibold">
            {{ isEdit ? 'Update Department' : 'Add Department' }}
            </h4>
            <p class="mt-0.5 text-xs text-white/85">Maintain naming standards and activation state.</p>
          </div>

          <div class="space-y-3">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Department Name</label>
              <input v-model="form.department_name" type="text" class="h-11 w-full rounded border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/15 dark:border-slate-700 dark:bg-slate-950" />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Department Long</label>
              <input v-model="form.dep_long" type="text" class="h-11 w-full rounded border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/15 dark:border-slate-700 dark:bg-slate-950" />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Short Name</label>
              <input v-model="form.dep_short" type="text" class="h-11 w-full rounded border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/15 dark:border-slate-700 dark:bg-slate-950" />
            </div>

            <div class="border border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-800/40 dark:bg-emerald-400/10">
              <label class="inline-flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                <input v-model="form.status" type="checkbox" class="h-4 w-4" />
                Active
              </label>
            </div>
          </div>

          <div class="mt-5 flex items-center gap-3 lg:justify-end">
            <button @click="isModalOpen = false" type="button" class="flex w-full justify-center rounded border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">Close</button>
            <button @click="saveDepartment" type="button" class="flex w-full justify-center rounded bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-500 sm:w-auto">Save</button>
          </div>
        </div>
      </template>
    </Modal>

    <ModalDelete
      v-if="isDeleteModal"
      head="Department"
      :data="selectedDepartment"
      :text="selectedDepartment?.department_name || ''"
      @close="isDeleteModal = false"
      @delete="deleteDepartment"
    />
  </div>
</template>
