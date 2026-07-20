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
          <h3 class="text-2xl font-semibold text-gray-800 dark:text-white">Import Templates From biotemplate.dat</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Upload a Granding biometric template export, review PIN and finger slots, then import templates for matching local users.
          </p>
        </div>

        <div class="mt-5 rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
          <div class="grid gap-3 lg:grid-cols-[1fr_220px_auto] lg:items-end">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">biotemplate.dat file</label>
              <input
                type="file"
                accept=".dat"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
                @change="onFileChange"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Machine marker</label>
              <input
                v-model="machineMarker"
                type="text"
                placeholder="Optional"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
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
              placeholder="Search PIN, user, finger, type, or row"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
            />
          </div>

          <p v-if="errorText" class="mt-2 text-sm text-red-600">{{ errorText }}</p>

          <div v-if="summary" class="mt-4 grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
            <div class="rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800">
              <p class="text-slate-500">Total</p>
              <p class="text-base font-semibold text-slate-900 dark:text-white">{{ summary.total_rows }}</p>
            </div>
            <div class="rounded-lg bg-emerald-100 px-3 py-2 dark:bg-emerald-900/40">
              <p class="text-emerald-700 dark:text-emerald-300">Valid</p>
              <p class="text-base font-semibold text-emerald-800 dark:text-emerald-200">{{ summary.valid_rows }}</p>
            </div>
            <div class="rounded-lg bg-amber-100 px-3 py-2 dark:bg-amber-900/40">
              <p class="text-amber-700 dark:text-amber-300">Existing</p>
              <p class="text-base font-semibold text-amber-800 dark:text-amber-200">{{ summary.existing_template_rows }}</p>
            </div>
            <div class="rounded-lg bg-sky-100 px-3 py-2 dark:bg-sky-900/40">
              <p class="text-sky-700 dark:text-sky-300">New</p>
              <p class="text-base font-semibold text-sky-800 dark:text-sky-200">{{ summary.new_template_rows }}</p>
            </div>
            <div class="rounded-lg bg-red-100 px-3 py-2 dark:bg-red-900/40">
              <p class="text-red-700 dark:text-red-300">Missing User</p>
              <p class="text-base font-semibold text-red-800 dark:text-red-200">{{ summary.missing_user_rows }}</p>
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
                <span>Replace existing templates</span>
              </label>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
              Selected: <strong>{{ selectedKeys.length }}</strong>
            </div>
          </div>

          <div class="max-h-[420px] overflow-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-left text-xs">
              <thead class="sticky top-0 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <tr>
                  <th class="px-3 py-2"></th>
                  <th class="px-3 py-2">
                    <button type="button" class="inline-flex items-center gap-1 font-semibold" @click="toggleSort('pin')">
                      PIN
                      <span class="text-[10px]">{{ sortIndicator('pin') }}</span>
                    </button>
                  </th>
                  <th class="px-3 py-2">
                    <button type="button" class="inline-flex items-center gap-1 font-semibold" @click="toggleSort('finger')">
                      Finger
                      <span class="text-[10px]">{{ sortIndicator('finger') }}</span>
                    </button>
                  </th>
                  <th class="px-3 py-2">User</th>
                  <th class="px-3 py-2">Type</th>
                  <th class="px-3 py-2">Bytes</th>
                  <th class="px-3 py-2">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in filteredRows"
                  :key="`template-row-${row.row}`"
                  :class="rowClass(row)"
                >
                  <td class="px-3 py-2 align-top">
                    <input
                      type="checkbox"
                      :disabled="!isRowSelectable(row)"
                      :checked="selectedMap[rowKey(row)] === true"
                      @change="toggleRow(row, $event)"
                    />
                  </td>
                  <td class="px-3 py-2 align-top font-medium text-slate-900 dark:text-white">{{ row.pin || '-' }}</td>
                  <td class="px-3 py-2 align-top font-medium text-slate-900 dark:text-white">{{ row.finger_id }}</td>
                  <td class="px-3 py-2 align-top">{{ row.user_name || '-' }}</td>
                  <td class="px-3 py-2 align-top">{{ row.type ?? '-' }}</td>
                  <td class="px-3 py-2 align-top">{{ row.template_bytes || '-' }}</td>
                  <td class="px-3 py-2 align-top">
                    <span v-if="!row.user_exists" class="rounded-full bg-red-100 px-2 py-1 text-[10px] font-semibold text-red-700">Missing user</span>
                    <span v-else-if="!row.valid_for_import" class="rounded-full bg-red-100 px-2 py-1 text-[10px] font-semibold text-red-700">Invalid</span>
                    <span v-else-if="row.has_existing_template" class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-700">Existing</span>
                    <span v-else class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold text-emerald-700">New</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Missing users are skipped. Import user.dat first if a PIN does not exist locally.
            </p>
            <button
              type="button"
              class="h-10 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="importing || selectedKeys.length === 0"
              @click="importSelected"
            >
              {{ importing ? 'Importing...' : `Import Selected (${selectedKeys.length})` }}
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

const emit = defineEmits(['close', 'imported'])
const userStore = useUserStore()

const selectedFile = ref(null)
const machineMarker = ref('')
const rows = ref([])
const summary = ref(null)
const loadingPreview = ref(false)
const importing = ref(false)
const errorText = ref('')
const replaceExisting = ref(false)
const selectedMap = ref({})
const searchQuery = ref('')
const sortField = ref('pin')
const sortDirection = ref('asc')

const normalizeText = (value) => String(value ?? '').toLowerCase()
const rowKey = (row) => `${Number(row.resolved_user_id || 0)}:${Number(row.finger_id ?? -1)}`

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

    if (sortField.value === 'finger') {
      comparison = compareValues(left.finger_id, right.finger_id)
    } else {
      comparison = compareValues(left.pin, right.pin)
      if (comparison === 0) {
        comparison = compareValues(left.finger_id, right.finger_id)
      }
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
      row.pin,
      row.resolved_user_id,
      row.user_name,
      row.finger_id,
      row.type,
      row.template_bytes,
      row.row,
    ]
      .map(normalizeText)
      .some((value) => value.includes(query))
  })
})

const selectedKeys = computed(() => {
  return Object.keys(selectedMap.value).filter((value) => selectedMap.value[value])
})

const isRowSelectable = (row) => {
  if (!row?.valid_for_import || !row?.user_exists) {
    return false
  }

  if (row.has_existing_template && !replaceExisting.value) {
    return false
  }

  return true
}

const selectableRows = computed(() => filteredRows.value.filter((row) => isRowSelectable(row)))

const allSelectableChecked = computed(() => {
  if (!selectableRows.value.length) {
    return false
  }

  return selectableRows.value.every((row) => selectedMap.value[rowKey(row)] === true)
})

const rowClass = (row) => {
  if (!row.user_exists || !row.valid_for_import) {
    return 'border-b border-gray-100 bg-red-50/50 dark:border-gray-800 dark:bg-red-900/10'
  }

  if (row.has_existing_template) {
    return 'border-b border-gray-100 bg-amber-50/70 dark:border-gray-800 dark:bg-amber-900/20'
  }

  return 'border-b border-gray-100 dark:border-gray-800'
}

const onFileChange = (event) => {
  selectedFile.value = event.target?.files?.[0] || null
  rows.value = []
  summary.value = null
  selectedMap.value = {}
  errorText.value = ''
}

const syncSelectionForReplaceOption = () => {
  const next = {}

  filteredRows.value.forEach((row) => {
    const key = rowKey(row)
    if (selectedMap.value[key] && isRowSelectable(row)) {
      next[key] = true
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
    return 'SORT'
  }

  return sortDirection.value === 'asc' ? 'ASC' : 'DESC'
}

const toggleSelectAll = (event) => {
  const checked = Boolean(event.target?.checked)

  if (!checked) {
    selectedMap.value = {}
    return
  }

  const next = {}
  selectableRows.value.forEach((row) => {
    next[rowKey(row)] = true
  })
  selectedMap.value = next
}

const toggleRow = (row, event) => {
  if (!isRowSelectable(row)) {
    return
  }

  const key = rowKey(row)
  const checked = Boolean(event.target?.checked)
  selectedMap.value = {
    ...selectedMap.value,
    [key]: checked,
  }

  if (!checked) {
    delete selectedMap.value[key]
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

  const result = await userStore.previewBiometricTemplateDatImport(selectedFile.value, machineMarker.value)

  if (result.success) {
    rows.value = result.data.rows || []
    summary.value = result.data.summary || null

    const next = {}
    rows.value.forEach((row) => {
      if (row.valid_for_import && row.user_exists && !row.has_existing_template) {
        next[rowKey(row)] = true
      }
    })
    selectedMap.value = next
  } else {
    errorText.value = result?.data?.response?.data?.message || 'Unable to decode biotemplate.dat file.'
  }

  loadingPreview.value = false
}

const importSelected = async () => {
  if (!selectedKeys.value.length) {
    return
  }

  importing.value = true
  errorText.value = ''

  const payload = {
    rows: rows.value,
    selected_keys: selectedKeys.value,
    replace_existing: replaceExisting.value,
    machine_marker: machineMarker.value,
  }

  const result = await userStore.importBiometricTemplateDat(payload)

  if (result.success) {
    emit('imported', result.data)
  } else {
    errorText.value = result?.data?.response?.data?.message || 'Import failed.'
  }

  importing.value = false
}
</script>
