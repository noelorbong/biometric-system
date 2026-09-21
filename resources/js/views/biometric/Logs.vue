<script setup>
import { onMounted, reactive, ref } from 'vue'

const logs = ref([])
const loading = ref(false)
const error = ref('')
const page = ref(1)
const nextCursor = ref(null)
const previousCursor = ref(null)
const filters = reactive({ date_from: '', date_to: '', checktype: '', sensorid: '', sn: '' })
const sort = reactive({ by: 'id', direction: 'desc' })
const perPage = ref(25)

const loadLogs = async (cursor = null, requestedPage = 1) => {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.post('/api/biometric/logs', {
      ...filters,
      cursor,
      sort_by: sort.by,
      sort_direction: sort.direction,
      per_page: perPage.value,
    })
    logs.value = data.logs.data
    page.value = requestedPage
    nextCursor.value = data.logs.next_cursor
    previousCursor.value = data.logs.prev_cursor
  } catch (response) {
    error.value = response.response?.data?.message || 'Unable to load biometric logs.'
  } finally {
    loading.value = false
  }
}

const changeSort = (column) => {
  if (sort.by === column) sort.direction = sort.direction === 'asc' ? 'desc' : 'asc'
  else {
    sort.by = column
    sort.direction = 'asc'
  }
  loadLogs(null, 1)
}

const sortMark = (column) => sort.by === column ? (sort.direction === 'asc' ? '↑' : '↓') : '↕'
const resetFilters = () => {
  Object.assign(filters, { date_from: '', date_to: '', checktype: '', sensorid: '', sn: '' })
  loadLogs(null, 1)
}

onMounted(() => loadLogs(null, 1))
</script>

<template>
  <div class="space-y-3">
    <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Biometric Logs</h1>
        <span class="text-xs text-slate-500 dark:text-slate-400">Raw check-in and check-out records</span>
        <span class="rounded bg-sky-50 px-1.5 py-0.5 text-[11px] font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">Server pagination</span>
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <form class="grid gap-2 sm:grid-cols-2 min-[800px]:grid-cols-5" @submit.prevent="loadLogs(null, 1)">
        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">From date
          <input v-model="filters.date_from" type="date" class="mt-1 h-8 w-full rounded-md border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700" />
        </label>
        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">To date
          <input v-model="filters.date_to" type="date" class="mt-1 h-8 w-full rounded-md border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700" />
        </label>
        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Check type
          <select v-model="filters.checktype" class="mt-1 h-8 w-full rounded-md border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700"><option value="">All types</option><option value="I">Check in (I)</option><option value="O">Check out (O)</option></select>
        </label>
        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sensor ID
          <input v-model.trim="filters.sensorid" type="text" placeholder="All sensors" class="mt-1 h-8 w-full rounded-md border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700" />
        </label>
        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Serial number
          <input v-model.trim="filters.sn" type="text" placeholder="All serial numbers" class="mt-1 h-8 w-full rounded-md border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700" />
        </label>
        <div class="flex gap-2 sm:col-span-2 min-[800px]:col-span-5 min-[800px]:justify-end">
          <button type="button" class="h-8 rounded-md border border-slate-300 px-2.5 text-xs font-medium text-slate-600 dark:border-slate-700 dark:text-slate-300" @click="resetFilters">Reset</button>
          <button type="submit" class="h-8 rounded-md bg-sky-600 px-3 text-xs font-semibold text-white hover:bg-sky-500">Apply filters</button>
        </div>
      </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <p v-if="error" class="m-3 rounded-md bg-rose-50 p-2 text-xs text-rose-700">{{ error }}</p>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-xs">
          <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-900/70 dark:text-slate-400"><tr>
            <th class="px-3 py-2"><button class="font-semibold" @click="changeSort('id')">ID {{ sortMark('id') }}</button></th>
            <th class="px-3 py-2 font-semibold">User</th>
            <th class="px-3 py-2"><button class="font-semibold" @click="changeSort('CHECKTIME')">Check time {{ sortMark('CHECKTIME') }}</button></th>
            <th class="px-3 py-2 font-semibold">Check type</th><th class="px-3 py-2 font-semibold">Sensor ID</th><th class="px-3 py-2 font-semibold">SN</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-if="loading"><td colspan="6" class="px-3 py-8 text-center text-slate-500">Loading biometric logs...</td></tr>
            <tr v-else-if="!logs.length"><td colspan="6" class="px-3 py-8 text-center text-slate-500">No logs match these filters.</td></tr>
            <tr v-for="log in logs" v-else :key="log.id" class="text-slate-700 hover:bg-slate-50/70 dark:text-slate-300 dark:hover:bg-white/[0.02]">
              <td class="whitespace-nowrap px-3 py-1.5 font-mono text-[11px]">{{ log.id }}</td>
              <td class="whitespace-nowrap px-3 py-1.5"><span class="font-medium text-slate-900 dark:text-white">{{ log.user }}</span><span class="ml-1.5 text-[10px] text-slate-400">#{{ log.user_id }}</span></td>
              <td class="whitespace-nowrap px-3 py-1.5 font-mono text-[11px]">{{ log.checktime }}</td>
              <td class="px-3 py-1.5"><span class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">{{ log.checktype || '—' }}</span></td>
              <td class="whitespace-nowrap px-3 py-1.5">{{ log.sensorid || '—' }}</td><td class="whitespace-nowrap px-3 py-1.5">{{ log.sn || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-3 py-2 text-xs dark:border-slate-800">
        <div class="flex items-center gap-2 text-slate-500"><span>Page {{ page }}</span><label class="flex items-center gap-1.5">Rows<select v-model.number="perPage" class="h-8 rounded-md border-slate-300 bg-transparent px-2 text-xs dark:border-slate-700" @change="loadLogs(null, 1)"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label></div>
        <div class="flex gap-1.5"><button :disabled="!previousCursor || loading" class="h-8 rounded-md border border-slate-300 px-2.5 text-xs disabled:opacity-40 dark:border-slate-700" @click="loadLogs(previousCursor, page - 1)">Previous</button><button :disabled="!nextCursor || loading" class="h-8 rounded-md border border-slate-300 px-2.5 text-xs disabled:opacity-40 dark:border-slate-700" @click="loadLogs(nextCursor, page + 1)">Next</button></div>
      </footer>
    </section>
  </div>
</template>
