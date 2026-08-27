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
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_30%),linear-gradient(135deg,_#0f172a_0%,_#1e293b_45%,_#0f766e_100%)] p-6 text-white shadow-sm dark:border-slate-800 lg:p-7">
      <h1 class="text-3xl font-semibold tracking-tight">Biometric Logs</h1>
      <p class="mt-2 text-sm text-slate-200/90">Review raw check-in and check-out records received from biometric machines.</p>
      <span class="mt-4 inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-inset ring-white/10">Fast server pagination</span>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <form class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" @submit.prevent="loadLogs(null, 1)">
        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">From date
          <input v-model="filters.date_from" type="date" class="mt-1.5 h-11 w-full rounded-xl border-slate-300 bg-transparent text-sm dark:border-slate-700" />
        </label>
        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">To date
          <input v-model="filters.date_to" type="date" class="mt-1.5 h-11 w-full rounded-xl border-slate-300 bg-transparent text-sm dark:border-slate-700" />
        </label>
        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Check type
          <select v-model="filters.checktype" class="mt-1.5 h-11 w-full rounded-xl border-slate-300 bg-transparent text-sm dark:border-slate-700"><option value="">All types</option><option value="I">Check in (I)</option><option value="O">Check out (O)</option></select>
        </label>
        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Sensor ID
          <input v-model.trim="filters.sensorid" type="text" placeholder="All sensors" class="mt-1.5 h-11 w-full rounded-xl border-slate-300 bg-transparent text-sm dark:border-slate-700" />
        </label>
        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Serial number
          <input v-model.trim="filters.sn" type="text" placeholder="All serial numbers" class="mt-1.5 h-11 w-full rounded-xl border-slate-300 bg-transparent text-sm dark:border-slate-700" />
        </label>
        <div class="flex gap-2 sm:col-span-2 xl:col-span-5 xl:justify-end">
          <button type="button" class="h-10 rounded-xl border border-slate-300 px-4 text-sm font-medium text-slate-600 dark:border-slate-700 dark:text-slate-300" @click="resetFilters">Reset</button>
          <button type="submit" class="h-10 rounded-xl bg-sky-600 px-5 text-sm font-semibold text-white hover:bg-sky-500">Apply filters</button>
        </div>
      </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <p v-if="error" class="m-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/70 dark:text-slate-400"><tr>
            <th class="px-5 py-3"><button class="font-semibold" @click="changeSort('id')">ID {{ sortMark('id') }}</button></th>
            <th class="px-5 py-3 font-semibold">User</th>
            <th class="px-5 py-3"><button class="font-semibold" @click="changeSort('CHECKTIME')">Check time {{ sortMark('CHECKTIME') }}</button></th>
            <th class="px-5 py-3 font-semibold">Check type</th><th class="px-5 py-3 font-semibold">Sensor ID</th><th class="px-5 py-3 font-semibold">SN</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-if="loading"><td colspan="6" class="px-5 py-12 text-center text-slate-500">Loading biometric logs…</td></tr>
            <tr v-else-if="!logs.length"><td colspan="6" class="px-5 py-12 text-center text-slate-500">No logs match these filters.</td></tr>
            <tr v-for="log in logs" v-else :key="log.id" class="text-slate-700 hover:bg-slate-50/70 dark:text-slate-300 dark:hover:bg-white/[0.02]">
              <td class="whitespace-nowrap px-5 py-3 font-mono text-xs">{{ log.id }}</td>
              <td class="whitespace-nowrap px-5 py-3"><span class="font-medium text-slate-900 dark:text-white">{{ log.user }}</span><span class="ml-2 text-xs text-slate-400">#{{ log.user_id }}</span></td>
              <td class="whitespace-nowrap px-5 py-3 font-mono text-xs">{{ log.checktime }}</td>
              <td class="px-5 py-3"><span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">{{ log.checktype || '—' }}</span></td>
              <td class="whitespace-nowrap px-5 py-3">{{ log.sensorid || '—' }}</td><td class="whitespace-nowrap px-5 py-3">{{ log.sn || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 text-sm dark:border-slate-800">
        <div class="flex items-center gap-3 text-slate-500"><span>Page {{ page }}</span><label class="flex items-center gap-2">Rows<select v-model.number="perPage" class="h-9 rounded-lg border-slate-300 bg-transparent py-1 text-sm dark:border-slate-700" @change="loadLogs(null, 1)"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label></div>
        <div class="flex gap-2"><button :disabled="!previousCursor || loading" class="rounded-lg border border-slate-300 px-3 py-2 disabled:opacity-40 dark:border-slate-700" @click="loadLogs(previousCursor, page - 1)">Previous</button><button :disabled="!nextCursor || loading" class="rounded-lg border border-slate-300 px-3 py-2 disabled:opacity-40 dark:border-slate-700" @click="loadLogs(nextCursor, page + 1)">Next</button></div>
      </footer>
    </section>
  </div>
</template>
