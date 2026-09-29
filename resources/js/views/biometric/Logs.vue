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

const sortMark = (column) => sort.by === column ? (sort.direction === 'asc' ? 'ASC' : 'DESC') : ''
const resetFilters = () => {
  Object.assign(filters, { date_from: '', date_to: '', checktype: '', sensorid: '', sn: '' })
  loadLogs(null, 1)
}

onMounted(() => loadLogs(null, 1))
</script>

<template>
  <div class="space-y-3">
    <div class="admin-page-header dark -ml-4 -mt-4 md:-ml-6 md:-mt-6 sticky p-2 top-0 z-20 -mr-4 border pb-4 shadow-sm md:-mr-6 space-y-3">
      <section>
        <div class="flex flex-wrap items-center gap-2.5">
          <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Biometric Logs</h1>
          <span class="text-xs text-slate-500 dark:text-slate-400">Raw check-in and check-out records</span>
          <span class="rounded bg-sky-50 px-1.5 py-0.5 text-[11px] font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">Server pagination</span>
        </div>
      </section>

  <section class="border-t border-white/15 pt-3">
        <form class="grid gap-2 sm:grid-cols-2 min-[800px]:grid-cols-[repeat(5,minmax(0,1fr))_auto] min-[800px]:items-end" @submit.prevent="loadLogs(null, 1)">
          <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">From date
            <input v-model="filters.date_from" type="date" class="mt-1 h-8 w-full rounded border border-white/15 bg-slate-950/30 px-2 text-xs font-semibold text-white shadow-inner focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-300/30" />
          </label>
          <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">To date
            <input v-model="filters.date_to" type="date" class="mt-1 h-8 w-full rounded border border-white/15 bg-slate-950/30 px-2 text-xs font-semibold text-white shadow-inner focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-300/30" />
          </label>
          <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Check type
            <select v-model="filters.checktype" class="mt-1 h-8 w-full rounded border border-white/15 bg-slate-950/30 px-2 text-xs font-semibold text-white shadow-inner focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-300/30"><option value="" class="bg-white text-slate-900">All types</option><option value="I" class="bg-white text-slate-900">Check in (I)</option><option value="O" class="bg-white text-slate-900">Check out (O)</option></select>
          </label>
          <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sensor ID
            <input v-model.trim="filters.sensorid" type="text" placeholder="All sensors" class="mt-1 h-8 w-full rounded border border-white/15 bg-slate-950/30 px-2 text-xs font-semibold text-white placeholder-slate-400 shadow-inner focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-300/30" />
          </label>
          <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Serial number
            <input v-model.trim="filters.sn" type="text" placeholder="All serial numbers" class="mt-1 h-8 w-full rounded border border-white/15 bg-slate-950/30 px-2 text-xs font-semibold text-white placeholder-slate-400 shadow-inner focus:border-cyan-300/60 focus:outline-none focus:ring-2 focus:ring-cyan-300/30" />
          </label>
          <div class="flex gap-2 sm:col-span-2 min-[800px]:col-span-1 min-[800px]:justify-end">
            <button type="button" class="inline-flex h-8 items-center justify-center rounded border border-white/15 bg-white/10 px-3 text-xs font-semibold text-white shadow-sm transition hover:border-cyan-200/40 hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-cyan-300/40" @click="resetFilters">Reset</button>
            <button type="submit" class="inline-flex h-8 items-center justify-center rounded border border-cyan-300/30 bg-[linear-gradient(135deg,_#0891b2_0%,_#0f766e_100%)] px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-[linear-gradient(135deg,_#0e7490_0%,_#115e59_100%)] focus:outline-none focus:ring-2 focus:ring-cyan-300/40">Apply filters</button>
          </div>
        </form>
      </section>
    </div>

    <section class="overflow-hidden rounded-none border border-slate-300 bg-white shadow-sm dark:border-slate-700 dark:bg-white/[0.03]">
      <p v-if="error" class="m-3 rounded-md bg-rose-50 p-2 text-xs text-rose-700">{{ error }}</p>
      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse text-left text-xs">
          <thead class="bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] text-[11px] uppercase tracking-wide text-white dark:bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_30%),linear-gradient(135deg,_rgba(15,23,42,0.96)_0%,_rgba(30,41,59,0.98)_40%,_rgba(15,118,110,0.92)_100%)]"><tr>
            <th class="border-r border-white/15 px-3 py-2"><button class="font-semibold text-slate-100 hover:text-white" @click="changeSort('id')">ID {{ sortMark('id') }}</button></th>
            <th class="border-r border-white/15 px-3 py-2 font-semibold text-slate-100">User</th>
            <th class="border-r border-white/15 px-3 py-2"><button class="font-semibold text-slate-100 hover:text-white" @click="changeSort('CHECKTIME')">Check time {{ sortMark('CHECKTIME') }}</button></th>
            <th class="border-r border-white/15 px-3 py-2 font-semibold text-slate-100">Check type</th><th class="border-r border-white/15 px-3 py-2 font-semibold text-slate-100">Sensor ID</th><th class="px-3 py-2 font-semibold text-slate-100">SN</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr v-if="loading"><td colspan="6" class="px-3 py-8 text-center text-slate-500">Loading biometric logs...</td></tr>
            <tr v-else-if="!logs.length"><td colspan="6" class="px-3 py-8 text-center text-slate-500">No logs match these filters.</td></tr>
            <tr v-for="log in logs" v-else :key="log.id" class="text-slate-700 hover:bg-slate-50/70 dark:text-slate-300 dark:hover:bg-white/[0.02]">
              <td class="whitespace-nowrap border-r border-slate-200 px-3 py-1.5 font-mono text-[11px] dark:border-slate-700">{{ log.id }}</td>
              <td class="whitespace-nowrap border-r border-slate-200 px-3 py-1.5 dark:border-slate-700"><span class="font-medium text-slate-900 dark:text-white">{{ log.user }}</span><span class="ml-1.5 text-[10px] text-slate-400">#{{ log.user_id }}</span></td>
              <td class="whitespace-nowrap border-r border-slate-200 px-3 py-1.5 font-mono text-[11px] dark:border-slate-700">{{ log.checktime }}</td>
              <td class="border-r border-slate-200 px-3 py-1.5 dark:border-slate-700"><span class="rounded bg-cyan-50 px-2 py-0.5 text-[10px] font-semibold text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200">{{ log.checktype || '—' }}</span></td>
              <td class="whitespace-nowrap border-r border-slate-200 px-3 py-1.5 dark:border-slate-700">{{ log.sensorid || '—' }}</td><td class="whitespace-nowrap px-3 py-1.5">{{ log.sn || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-3 py-2 text-xs dark:border-slate-800">
        <div class="flex items-center gap-2 text-slate-500"><span>Page {{ page }}</span><label class="flex items-center gap-1.5">Rows<select v-model.number="perPage" class="h-8 rounded border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-700 focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" @change="loadLogs(null, 1)"><option :value="10" class="bg-white text-slate-900">10</option><option :value="25" class="bg-white text-slate-900">25</option><option :value="50" class="bg-white text-slate-900">50</option><option :value="100" class="bg-white text-slate-900">100</option></select></label></div>
        <div class="flex gap-1.5"><button :disabled="!previousCursor || loading" class="inline-flex h-8 items-center justify-center rounded border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-cyan-600 hover:bg-cyan-50 hover:text-cyan-800 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-cyan-950/30" @click="loadLogs(previousCursor, page - 1)">Previous</button><button :disabled="!nextCursor || loading" class="inline-flex h-8 items-center justify-center rounded border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-cyan-600 hover:bg-cyan-50 hover:text-cyan-800 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-cyan-950/30" @click="loadLogs(nextCursor, page + 1)">Next</button></div>
      </footer>
    </section>
  </div>
</template>
