<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/common/Modal.vue'
import ModalDelete from '@/components/common/ModalDelete.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@/icons'
import { useCollegeStore } from '@/store/CollegeStore'

const collegeStore = useCollegeStore()
const { colleges } = storeToRefs(collegeStore)
const search = ref('')
const isModalOpen = ref(false)
const isDeleteModal = ref(false)
const isEdit = ref(false)
const selectedCollege = ref(null)

const form = ref({
  id: null,
  company_id: 1,
  college_short: '',
  college_long: '',
  college_head: '',
  status: true,
})

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 1500,
  timerProgressBar: true,
})

const filteredColleges = computed(() => {
  const term = search.value.toLowerCase().trim()
  if (!term) {
    return colleges.value
  }

  return colleges.value.filter((item) => {
    return (
      String(item.company_id || '').toLowerCase().includes(term) ||
      (item.college_short || '').toLowerCase().includes(term) ||
      (item.college_long || '').toLowerCase().includes(term) ||
      (item.college_head || '').toLowerCase().includes(term)
    )
  })
})

const totalColleges = computed(() => colleges.value.length)
const activeColleges = computed(() => colleges.value.filter((item) => Boolean(item.status)).length)
const inactiveColleges = computed(() => colleges.value.filter((item) => !item.status).length)
const withHeads = computed(() => colleges.value.filter((item) => Boolean(item.college_head)).length)

const toastResult = (message, icon = 'success') => {
  Toast.fire({
    icon,
    title: message,
  })
}

const loadColleges = async () => {
  const resp = await collegeStore.loadColleges()
  if (!resp.success) {
    toastResult('Unable to load colleges', 'error')
  }
}

const openCreate = () => {
  isEdit.value = false
  form.value = {
    id: null,
    company_id: 1,
    college_short: '',
    college_long: '',
    college_head: '',
    status: true,
  }
  isModalOpen.value = true
}

const openEdit = (college) => {
  isEdit.value = true
  form.value = {
    id: college.id,
    company_id: college.company_id ?? 1,
    college_short: college.college_short || '',
    college_long: college.college_long || '',
    college_head: college.college_head || '',
    status: Boolean(college.status),
  }
  isModalOpen.value = true
}

const openDelete = (college) => {
  selectedCollege.value = college
  isDeleteModal.value = true
}

const saveCollege = async () => {
  const payload = {
    id: form.value.id,
    company_id: Number(form.value.company_id || 1),
    college_short: form.value.college_short || null,
    college_long: form.value.college_long,
    college_head: form.value.college_head || null,
    status: Boolean(form.value.status),
  }

  const resp = isEdit.value
    ? await collegeStore.updateCollege(payload)
    : await collegeStore.storeCollege(payload)

  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to save college', 'error')
    return
  }

  toastResult(isEdit.value ? 'College updated' : 'College created')
  isModalOpen.value = false
}

const deleteCollege = async () => {
  if (!selectedCollege.value) {
    return
  }

  const resp = await collegeStore.deleteCollege({ id: selectedCollege.value.id })

  if (!resp.success) {
    toastResult(resp?.data?.response?.data?.message || 'Unable to delete college', 'error')
    return
  }

  toastResult('College removed')
  isDeleteModal.value = false
  selectedCollege.value = null
}

onMounted(async () => {
  await loadColleges()
})
</script>

<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] p-6 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-3xl font-semibold leading-tight">Colleges</h1>
          <p class="mt-1 text-sm text-slate-200/90">Manage college codes, names, heads, and activation state.</p>
          <div class="mt-3 inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-100 ring-1 ring-inset ring-white/10">
            College Directory
          </div>
        </div>
        <Button @click="openCreate" :className="'h-11 whitespace-nowrap text-nowrap border border-sky-300/30 bg-sky-400/20 text-sky-50 hover:bg-sky-400/30'" size="sm" variant="primary" :startIcon="PlusIcon">
          Add College
        </Button>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Colleges</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ totalColleges }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Active</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ activeColleges }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Inactive</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ inactiveColleges }}</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">With Head</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ withHeads }}</p>
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
            placeholder="Search college..."
            class="h-11 w-full rounded-lg border border-slate-300 bg-transparent pl-9 pr-4 text-sm text-slate-800 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:text-white/90"
          />
        </div>
        <Button @click="openCreate" :className="'h-11 whitespace-nowrap text-nowrap border border-sky-200 bg-sky-50 px-4 text-sky-700 hover:bg-sky-100 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300 dark:hover:bg-sky-900/30'" size="sm" variant="primary" :startIcon="PlusIcon">
          New College
        </Button>
      </div>
    </section>

    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="max-w-full overflow-x-auto custom-scrollbar">
        <table class="min-w-full">
          <thead class="bg-slate-50 dark:bg-slate-900/60">
            <tr>
              <th class="px-5 py-3 text-left text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Short</th>
              <th class="px-5 py-3 text-left text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Long</th>
              <th class="px-5 py-3 text-left text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">College Head</th>
              <th class="px-5 py-3 text-left text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Status</th>
              <th class="px-5 py-3 text-right text-theme-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr v-for="college in filteredColleges" :key="college.id" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-200">{{ college.college_short || '-' }}</td>
              <td class="px-5 py-3 text-sm font-medium text-slate-800 dark:text-slate-100">{{ college.college_long || '-' }}</td>
              <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-200">{{ college.college_head || '-' }}</td>
              <td class="px-5 py-3 text-sm">
                <span
                  class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="college.status
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                    : 'bg-slate-100 text-slate-600 dark:bg-slate-700/70 dark:text-slate-300'"
                >
                  {{ college.status ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-2">
                  <button @click="openEdit(college)" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-sky-200 text-sky-600 transition hover:bg-sky-50 dark:border-sky-800/60 dark:text-sky-300 dark:hover:bg-sky-900/20"><PencilIcon /></button>
                  <button @click="openDelete(college)" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 text-rose-600 transition hover:bg-rose-50 dark:border-rose-800/60 dark:text-rose-300 dark:hover:bg-rose-900/20"><TrashIcon /></button>
                </div>
              </td>
            </tr>
            <tr v-if="!filteredColleges.length">
              <td colspan="5" class="px-5 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                No colleges found.
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
            {{ isEdit ? 'Update College' : 'Add College' }}
            </h4>
            <p class="mt-0.5 text-xs text-white/85">Maintain college details and assignment metadata.</p>
          </div>

          <div class="space-y-3">
            <!-- <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Company ID</label>
              <input v-model.number="form.company_id" type="number" min="1" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
            </div> -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">College Short</label>
              <input v-model="form.college_short" type="text" class="h-11 w-full rounded-lg border border-slate-300 bg-transparent px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">College Long</label>
              <input v-model="form.college_long" type="text" class="h-11 w-full rounded-lg border border-slate-300 bg-transparent px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">College Head</label>
              <input v-model="form.college_head" type="text" class="h-11 w-full rounded-lg border border-slate-300 bg-transparent px-4 py-2.5 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20" />
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-800/40 dark:bg-emerald-400/10">
              <label class="inline-flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                <input v-model="form.status" type="checkbox" class="h-4 w-4" />
                Active
              </label>
            </div>
          </div>

          <div class="mt-5 flex items-center gap-3 lg:justify-end">
            <button @click="isModalOpen = false" type="button" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto">Close</button>
            <button @click="saveCollege" type="button" class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">Save</button>
          </div>
        </div>
      </template>
    </Modal>

    <ModalDelete
      v-if="isDeleteModal"
      head="College"
      :data="selectedCollege"
      :text="selectedCollege?.college_long || selectedCollege?.college_short || ''"
      @close="isDeleteModal = false"
      @delete="deleteCollege"
    />
  </div>
</template>
