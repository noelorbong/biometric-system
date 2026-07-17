<template>
  <Modal @close="$emit('close')">
    <template #body>
      <div class="relative m-2 max-h-[90vh] w-full max-w-[1100px] overflow-y-auto rounded-3xl bg-white p-5 dark:bg-gray-900 lg:p-8">
        <button
          @click="$emit('close')"
          class="absolute right-5 top-5 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300"
        >
          x
        </button>

        <div class="pr-10">
          <h3 class="text-2xl font-semibold text-gray-800 dark:text-white">Import Users From user.dat</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Upload a user.dat file, review decoded users, select rows to import, and optionally replace existing IDs.
          </p>
        </div>

        <div class="mt-5 rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="flex-1">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">user.dat file</label>
              <input
                type="file"
                accept=".dat"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                @change="onFileChange"
              />
            </div>
            <button
              type="button"
              class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="!selectedFile || loadingPreview"
              @click="previewFile"
            >
              {{ loadingPreview ? 'Decoding...' : 'Decode & Preview' }}
            </button>
          </div>

          <div v-if="rows.length" class="mt-4">
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search ID, PIN, name, password, privilege, or card"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
            />
          </div>

          <p v-if="errorText" class="mt-2 text-sm text-red-600">{{ errorText }}</p>

          <div v-if="summary" class="mt-4 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
            <div class="rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800">
              <p class="text-slate-500">Total</p>
              <p class="text-base font-semibold text-slate-900 dark:text-white">{{ summary.total_rows }}</p>
            </div>
            <div class="rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800">
              <p class="text-slate-500">Valid</p>
              <p class="text-base font-semibold text-slate-900 dark:text-white">{{ summary.valid_rows }}</p>
            </div>
            <div class="rounded-lg bg-amber-100 px-3 py-2 dark:bg-amber-900/40">
              <p class="text-amber-700 dark:text-amber-300">Existing ID</p>
              <p class="text-base font-semibold text-amber-800 dark:text-amber-200">{{ summary.existing_id_rows }}</p>
            </div>
            <div class="rounded-lg bg-emerald-100 px-3 py-2 dark:bg-emerald-900/40">
              <p class="text-emerald-700 dark:text-emerald-300">New</p>
              <p class="text-base font-semibold text-emerald-800 dark:text-emerald-200">{{ summary.new_rows }}</p>
            </div>
          </div>
        </div>

        <div v-if="rows.length" class="mt-4 rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
          <div class="mb-3 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-3 text-sm">
              <label class="inline-flex items-center gap-2">
                <input
                  type="checkbox"
                  :checked="allSelectableChecked"
                  @change="toggleSelectAll($event)"
                />
                <span>Select All</span>
              </label>
              <label class="inline-flex items-center gap-2">
                <input
                  type="checkbox"
                  v-model="replaceExisting"
                  @change="syncSelectionForReplaceOption"
                />
                <span>Replace existing IDs</span>
              </label>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
              Selected: <strong>{{ selectedUserIds.length }}</strong>
            </div>
          </div>

          <div class="max-h-[420px] overflow-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-left text-xs">
              <thead class="sticky top-0 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <tr>
                  <th class="px-3 py-2"></th>
                  <th class="px-3 py-2">
                    <button type="button" class="inline-flex items-center gap-1 font-semibold" @click="toggleSort('id')">
                      ID
                      <span class="text-[10px]">{{ sortIndicator('id') }}</span>
                    </button>
                  </th>
                  <th class="px-3 py-2">
                    <button type="button" class="inline-flex items-center gap-1 font-semibold" @click="toggleSort('pin')">
                      PIN
                      <span class="text-[10px]">{{ sortIndicator('pin') }}</span>
                    </button>
                  </th>
                  <th class="px-3 py-2">
                    <button type="button" class="inline-flex items-center gap-1 font-semibold" @click="toggleSort('name')">
                      Name
                      <span class="text-[10px]">{{ sortIndicator('name') }}</span>
                    </button>
                  </th>
                  <th class="px-3 py-2">Password</th>
                  <th class="px-3 py-2">Privilege</th>
                  <th class="px-3 py-2">Card</th>
                  <th class="px-3 py-2">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in filteredRows"
                  :key="`dat-row-${row.row}`"
                  :class="rowClass(row)"
                >
                  <td class="px-3 py-2 align-top">
                    <input
                      type="checkbox"
                      :disabled="!isRowSelectable(row)"
                      :checked="selectedMap[row.resolved_user_id] === true"
                      @change="toggleRow(row, $event)"
                    />
                  </td>
                  <td class="px-3 py-2 align-top font-medium text-slate-900 dark:text-white">{{ row.resolved_user_id || '-' }}</td>
                  <td class="px-3 py-2 align-top font-medium text-slate-900 dark:text-white">{{ row.pin }}</td>
                  <td class="px-3 py-2 align-top">{{ row.name || '-' }}</td>
                  <td class="px-3 py-2 align-top">{{ row.password || '-' }}</td>
                  <td class="px-3 py-2 align-top">{{ row.privilege }}</td>
                  <td class="px-3 py-2 align-top">{{ row.card }}</td>
                  <td class="px-3 py-2 align-top">
                    <span v-if="!row.valid_for_import" class="rounded-full bg-red-100 px-2 py-1 text-[10px] font-semibold text-red-700">Invalid PIN</span>
                    <span v-else-if="row.has_existing_id" class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-700">Existing ID</span>
                    <span v-else class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold text-emerald-700">New</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Existing IDs are highlighted. Enable "Replace existing IDs" if you want to overwrite their records.
            </p>
            <button
              type="button"
              class="h-10 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="importing || selectedUserIds.length === 0"
              @click="importSelected"
            >
              {{ importing ? 'Importing...' : `Import Selected (${selectedUserIds.length})` }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { computed, ref } from 'vue'
import Modal from '@/components/common/Modal.vue'
import { useUserStore } from '@/store/UserStore'
import axios from 'axios'

const emit = defineEmits(['close', 'imported'])

const userStore = useUserStore()

const selectedFile = ref(null)
const rows = ref([])
const summary = ref(null)
const loadingPreview = ref(false)
const importing = ref(false)
const errorText = ref('')
const replaceExisting = ref(false)
const selectedMap = ref({})
const searchQuery = ref('')
const sortField = ref('id')
const sortDirection = ref('asc')

const normalizeText = (value) => String(value ?? '').toLowerCase()

const compareValues = (left, right) => {
  const leftNumber = Number(left)
  const rightNumber = Number(right)
  const bothNumeric = !Number.isNaN(leftNumber) && !Number.isNaN(rightNumber)

  if (bothNumeric) {
    return leftNumber - rightNumber
  }

  return String(left ?? '').localeCompare(String(right ?? ''), undefined, { numeric: true, sensitivity: 'base' })
}

const sortedRows = computed(() => {
  const nextRows = [...rows.value]

  nextRows.sort((left, right) => {
    let comparison = 0

    if (sortField.value === 'pin') {
      comparison = compareValues(left.pin, right.pin)
    } else if (sortField.value === 'name') {
      comparison = compareValues(left.name, right.name)
    } else {
      comparison = compareValues(left.resolved_user_id || 0, right.resolved_user_id || 0)
    }

    return sortDirection.value === 'asc' ? comparison : -comparison
  })

  return nextRows
})

const filteredRows = computed(() => {
  const query = normalizeText(searchQuery.value).trim()

  if (!query) {
    return sortedRows.value
  }

  return sortedRows.value.filter((row) => {
    return [
      row.resolved_user_id,
      row.pin,
      row.name,
      row.password,
      row.privilege,
      row.card,
      row.row,
    ]
      .map(normalizeText)
      .some((value) => value.includes(query))
  })
})

const selectedUserIds = computed(() => {
  return Object.keys(selectedMap.value)
    .map((value) => Number(value))
    .filter((value) => !Number.isNaN(value) && selectedMap.value[value])
})

const selectableRows = computed(() => filteredRows.value.filter((row) => isRowSelectable(row)))

const allSelectableChecked = computed(() => {
  if (!selectableRows.value.length) {
    return false
  }

  return selectableRows.value.every((row) => selectedMap.value[row.resolved_user_id] === true)
})

const onFileChange = (event) => {
  const file = event.target?.files?.[0] || null
  selectedFile.value = file
  rows.value = []
  summary.value = null
  selectedMap.value = {}
  errorText.value = ''
}

const isRowSelectable = (row) => {
  if (!row?.valid_for_import || !row?.resolved_user_id) {
    return false
  }

  if (row.has_existing_id && !replaceExisting.value) {
    return false
  }

  return true
}

const rowClass = (row) => {
  if (!row.valid_for_import) {
    return 'border-b border-gray-100 bg-red-50/50 dark:border-gray-800 dark:bg-red-900/10'
  }

  if (row.has_existing_id) {
    return 'border-b border-gray-100 bg-amber-50/70 dark:border-gray-800 dark:bg-amber-900/20'
  }

  return 'border-b border-gray-100 dark:border-gray-800'
}

const syncSelectionForReplaceOption = () => {
  const next = {}

  filteredRows.value.forEach((row) => {
    if (selectedMap.value[row.resolved_user_id] && isRowSelectable(row)) {
      next[row.resolved_user_id] = true
    }
  })

  selectedMap.value = next
}

const toggleSort = (field) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    return
  }

  sortField.value = field
  sortDirection.value = 'asc'
}

const sortIndicator = (field) => {
  if (sortField.value !== field) {
    return '↕'
  }

  return sortDirection.value === 'asc' ? '↑' : '↓'
}

const toggleSelectAll = (event) => {
  const checked = Boolean(event.target?.checked)

  if (!checked) {
    selectedMap.value = {}
    return
  }

  const next = {}
  selectableRows.value.forEach((row) => {
    next[row.resolved_user_id] = true
  })
  selectedMap.value = next
}

const toggleRow = (row, event) => {
  if (!isRowSelectable(row)) {
    return
  }

  const checked = Boolean(event.target?.checked)
  selectedMap.value = {
    ...selectedMap.value,
    [row.resolved_user_id]: checked,
  }

  if (!checked) {
    delete selectedMap.value[row.resolved_user_id]
    selectedMap.value = { ...selectedMap.value }
  }
}

const previewFile = async () => {
  if (!selectedFile.value) {
    return
  }

  loadingPreview.value = true
  errorText.value = ''
  selectedMap.value = {}

  let result
  if (typeof userStore.previewUserDatImport === 'function') {
    result = await userStore.previewUserDatImport(selectedFile.value)
  } else {
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    result = await axios.post('/api/user/import-dat/preview', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    }).then((resp) => ({ success: true, data: resp.data }))
      .catch((resp) => ({ success: false, data: resp }))
  }

  if (result.success) {
    rows.value = result.data.rows || []
    summary.value = result.data.summary || null

    const next = {}
    rows.value.forEach((row) => {
      if (row.valid_for_import && !row.has_existing_id) {
        next[row.resolved_user_id] = true
      }
    })
    selectedMap.value = next
  } else {
    errorText.value = result?.data?.response?.data?.message || 'Unable to decode user.dat file.'
  }

  loadingPreview.value = false
}

const importSelected = async () => {
  if (!selectedUserIds.value.length) {
    return
  }

  importing.value = true
  errorText.value = ''

  const payload = {
    rows: rows.value,
    selected_user_ids: selectedUserIds.value,
    replace_existing: replaceExisting.value,
  }

  let result
  if (typeof userStore.importUserDat === 'function') {
    result = await userStore.importUserDat(payload)
  } else {
    result = await axios.post('/api/user/import-dat', payload)
      .then((resp) => ({ success: true, data: resp.data }))
      .catch((resp) => ({ success: false, data: resp }))
  }

  if (result.success) {
    emit('imported', result.data)
  } else {
    errorText.value = result?.data?.response?.data?.message || 'Import failed.'
  }

  importing.value = false
}
</script>
