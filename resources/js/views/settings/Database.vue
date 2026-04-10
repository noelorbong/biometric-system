<script setup>
import { computed, onMounted, ref } from 'vue'
import Swal from 'sweetalert2'
import 'sweetalert2/src/sweetalert2.scss'
import SettingsNav from '@/views/settings/SettingsNav.vue'
import { useAppSettingStore } from '@/store/AppSettingStore'
import { useAuthStore } from '@/store/AuthStore'

const appSettingStore = useAppSettingStore()
const authStore = useAuthStore()

const backupPassphrase = ref('')
const backupPassphraseConfirm = ref('')
const backupFile = ref(null)
const backuping = ref(false)
const exportingBackup = ref(false)
const importingBackup = ref(false)

const autoBackupConfig = ref(null)
const autoBackupFiles = ref([])
const loadingAutoBackup = ref(false)
const savingAutoBackup = ref(false)
const runningAutoBackupNow = ref(false)
const autoRunResult = ref(null)

const autoBackupForm = ref({
  enabled: false,
  schedule: 'daily',
  time: '02:00',
  retain_days: 7,
  passphrase: '',
  passphrase_confirm: '',
})

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 1800,
  timerProgressBar: true,
})

const toastResult = (message, icon = 'success') => {
  Toast.fire({ icon, title: message })
}

const getPassphraseError = () => {
  const pass = backupPassphrase.value || ''
  const passConfirm = backupPassphraseConfirm.value || ''

  if (pass.length < 8) {
    return 'Passphrase must be at least 8 characters.'
  }

  if (pass !== passConfirm) {
    return 'Passphrase and confirmation do not match.'
  }

  return null
}

const passphraseError = computed(() => getPassphraseError())

const getAutoBackupPassphraseError = () => {
  const passphrase = autoBackupForm.value.passphrase || ''
  const confirm = autoBackupForm.value.passphrase_confirm || ''

  if (passphrase === '' && confirm === '') {
    return null
  }

  if (passphrase.length < 8) {
    return 'Auto-backup passphrase must be at least 8 characters.'
  }

  if (passphrase !== confirm) {
    return 'Auto-backup passphrase and confirmation do not match.'
  }

  return null
}

const autoBackupPassphraseError = computed(() => getAutoBackupPassphraseError())

const parseBlobError = async (blob) => {
  try {
    const text = await blob.text()
    const json = JSON.parse(text)
    const firstValidation = Object.values(json?.errors || {})?.[0]?.[0]
    return firstValidation || json?.message || json?.error || null
  } catch (error) {
    return null
  }
}

const parseApiError = (resp, fallbackMessage) => {
  const errors = resp?.data?.response?.data?.errors || {}
  const firstValidation = Object.values(errors)?.[0]?.[0]
  return firstValidation || resp?.data?.response?.data?.error || resp?.data?.response?.data?.message || fallbackMessage
}

const loadAutoBackupStatus = async () => {
  loadingAutoBackup.value = true
  const resp = await appSettingStore.loadBackupAutoStatus()
  loadingAutoBackup.value = false

  if (!resp.success) {
    toastResult(parseApiError(resp, 'Unable to load backup status'), 'error')
    return
  }

  autoBackupConfig.value = resp.data?.auto_backup || null
  autoBackupFiles.value = resp.data?.backups || []
  autoBackupForm.value = {
    enabled: Boolean(resp.data?.auto_backup?.enabled ?? false),
    schedule: resp.data?.auto_backup?.schedule || 'daily',
    time: resp.data?.auto_backup?.time || '02:00',
    retain_days: Number(resp.data?.auto_backup?.retain_days || 7),
    passphrase: '',
    passphrase_confirm: '',
  }
}

const runDatabaseBackup = async () => {
  if (passphraseError.value) {
    toastResult(passphraseError.value, 'warning')
    return
  }

  backuping.value = true
  const resp = await appSettingStore.backupDatabase(backupPassphrase.value)
  backuping.value = false

  if (!resp.success) {
    toastResult(parseApiError(resp, 'Unable to create backup'), 'error')
    return
  }

  toastResult(`Backup created: ${resp?.data?.filename || 'database backup file'}`)
  await loadAutoBackupStatus()
}

const runDatabaseExport = async () => {
  if (passphraseError.value) {
    toastResult(passphraseError.value, 'warning')
    return
  }

  exportingBackup.value = true
  const resp = await appSettingStore.exportDatabase(backupPassphrase.value)
  exportingBackup.value = false

  if (!resp.success) {
    if (resp.isBlobError && resp?.data?.response?.data instanceof Blob) {
      const blobMessage = await parseBlobError(resp.data.response.data)
      toastResult(blobMessage || 'Unable to export backup', 'error')
      return
    }

    toastResult(parseApiError(resp, 'Unable to export backup'), 'error')
    return
  }

  const url = window.URL.createObjectURL(resp.data.blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = resp.data.filename || 'db-export.bkp'
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  window.URL.revokeObjectURL(url)

  toastResult('Encrypted export downloaded')
}

const onBackupFileSelected = (event) => {
  backupFile.value = event?.target?.files?.[0] || null
}

const runDatabaseImport = async () => {
  if (passphraseError.value) {
    toastResult(passphraseError.value, 'warning')
    return
  }

  if (!backupFile.value) {
    toastResult('Choose an encrypted backup file first.', 'warning')
    return
  }

  const confirmation = await Swal.fire({
    title: 'Import database backup?',
    text: 'This will replace current data with the backup content.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Import Backup',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#e11d48',
  })

  if (!confirmation.isConfirmed) {
    return
  }

  importingBackup.value = true
  const resp = await appSettingStore.importDatabase(backupFile.value, backupPassphrase.value)
  importingBackup.value = false

  if (!resp.success) {
    toastResult(parseApiError(resp, 'Unable to import backup'), 'error')
    return
  }

  toastResult(`Database import complete (${Number(resp?.data?.restored_tables || 0)} tables)`)
  await loadAutoBackupStatus()
}

const updateAutoBackupField = (field, value) => {
  autoBackupForm.value = {
    ...autoBackupForm.value,
    [field]: value,
  }
}

const saveAutoBackupSettings = async () => {
  if (autoBackupPassphraseError.value) {
    toastResult(autoBackupPassphraseError.value, 'warning')
    return
  }

  savingAutoBackup.value = true
  const resp = await appSettingStore.updateAutoBackupConfig({
    enabled: Boolean(autoBackupForm.value.enabled),
    schedule: autoBackupForm.value.schedule,
    time: autoBackupForm.value.time || '02:00',
    retain_days: Number(autoBackupForm.value.retain_days || 7),
    passphrase: autoBackupForm.value.passphrase || null,
  })
  savingAutoBackup.value = false

  if (!resp.success) {
    toastResult(parseApiError(resp, 'Unable to save auto-backup settings'), 'error')
    return
  }

  toastResult('Automatic backup settings updated')
  await loadAutoBackupStatus()
}

const runAutoBackupNow = async () => {
  runningAutoBackupNow.value = true
  autoRunResult.value = null
  const resp = await appSettingStore.runAutoBackupNow()
  runningAutoBackupNow.value = false

  if (!resp.success) {
    toastResult(parseApiError(resp, 'Unable to run auto backup'), 'error')
    return
  }

  autoRunResult.value = resp.data
  toastResult(resp?.data?.success ? 'Auto backup finished' : 'Auto backup finished with issues', resp?.data?.success ? 'success' : 'warning')
  await loadAutoBackupStatus()
}

const downloadBackupFile = async (filename) => {
  const resp = await appSettingStore.downloadBackupFile(filename)

  if (!resp.success) {
    if (resp.isBlobError && resp?.data?.response?.data instanceof Blob) {
      const blobMessage = await parseBlobError(resp.data.response.data)
      toastResult(blobMessage || 'Unable to download backup file', 'error')
      return
    }

    toastResult(parseApiError(resp, 'Unable to download backup file'), 'error')
    return
  }

  const url = window.URL.createObjectURL(resp.data.blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = resp.data.filename || filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  window.URL.revokeObjectURL(url)
}

const deleteBackupFile = async (filename) => {
  const confirmation = await Swal.fire({
    title: 'Delete backup file?',
    text: `This will permanently delete ${filename}.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#e11d48',
  })

  if (!confirmation.isConfirmed) {
    return
  }

  const resp = await appSettingStore.deleteBackupFile(filename)
  if (!resp.success) {
    toastResult(parseApiError(resp, 'Unable to delete backup file'), 'error')
    return
  }

  toastResult('Backup file deleted')
  await loadAutoBackupStatus()
}

const restoreBackupFile = async (filename) => {
  const { value: passphrase } = await Swal.fire({
    title: 'Restore backup file?',
    text: `This will replace current database data using ${filename}.`,
    input: 'password',
    inputPlaceholder: 'Enter backup passphrase',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Restore',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#e11d48',
    inputValidator: (value) => {
      if (!value || value.length < 8) {
        return 'Passphrase must be at least 8 characters.'
      }
      return null
    },
  })

  if (!passphrase) {
    return
  }

  const resp = await appSettingStore.restoreBackupFile(filename, passphrase)
  if (!resp.success) {
    toastResult(parseApiError(resp, 'Unable to restore backup file'), 'error')
    return
  }

  await Swal.fire({
    icon: 'success',
    title: 'Backup restored',
    text: `Restored ${Number(resp?.data?.restored_tables || 0)} table(s). You will be signed out now.`,
    confirmButtonText: 'Continue',
    confirmButtonColor: '#0284c7',
  })

  authStore.clearAccount()
}

const formatBytes = (bytes) => {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

const formatIso = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(async () => {
  await loadAutoBackupStatus()
})
</script>

<template>
  <div class="space-y-6">
    <SettingsNav />

    <section class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03] lg:p-5">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Database Settings</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Encrypted backup, import/export, and automatic backup controls.</p>
        </div>
        <button
          type="button"
          @click="runAutoBackupNow"
          :disabled="runningAutoBackupNow"
          class="inline-flex h-11 items-center justify-center rounded-xl border border-emerald-300 bg-emerald-100 px-4 text-sm font-semibold text-emerald-900 transition hover:bg-emerald-200 disabled:cursor-not-allowed disabled:opacity-70 dark:border-emerald-900/50 dark:bg-emerald-900/25 dark:text-emerald-300 dark:hover:bg-emerald-900/35"
        >
          {{ runningAutoBackupNow ? 'Running backup now...' : 'Run Auto Backup Now' }}
        </button>
      </div>

      <div v-if="autoRunResult" class="mt-3 rounded-lg border px-3 py-2 text-xs" :class="autoRunResult.success ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300'">
        <p class="font-semibold">Exit code: {{ autoRunResult.exit_code }}</p>
        <p v-if="autoRunResult.output" class="mt-1 whitespace-pre-line break-words">{{ autoRunResult.output }}</p>
      </div>

      <div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50/80 p-4 dark:border-indigo-900/50 dark:bg-indigo-950/20">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-700 dark:text-indigo-300">Auto Backup Config</p>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Stored in app settings (database).</p>
          </div>
          <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
            <input :checked="autoBackupForm.enabled" @change="updateAutoBackupField('enabled', $event.target.checked)" type="checkbox" class="h-4 w-4" />
            <span>{{ autoBackupForm.enabled ? 'Enabled' : 'Disabled' }}</span>
          </label>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300">Schedule</label>
            <select :value="autoBackupForm.schedule" @change="updateAutoBackupField('schedule', $event.target.value)" class="h-10 w-full rounded-md border border-indigo-200 bg-white px-3 text-sm text-slate-800 dark:border-indigo-900/60 dark:bg-slate-900 dark:text-white/90">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="hourly">Hourly</option>
              <option value="everyTwelveHours">Every 12 Hours</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300">Run Time</label>
            <input :value="autoBackupForm.time" @input="updateAutoBackupField('time', $event.target.value)" type="time" :disabled="autoBackupForm.schedule === 'hourly' || autoBackupForm.schedule === 'everyTwelveHours'" class="h-10 w-full rounded-md border border-indigo-200 bg-white px-3 text-sm text-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-indigo-900/60 dark:bg-slate-900 dark:text-white/90" />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300">Retention (days)</label>
            <input :value="autoBackupForm.retain_days" @input="updateAutoBackupField('retain_days', Number($event.target.value))" type="number" min="1" max="365" class="h-10 w-full rounded-md border border-indigo-200 bg-white px-3 text-sm text-slate-800 dark:border-indigo-900/60 dark:bg-slate-900 dark:text-white/90" />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300">Backup Passphrase</label>
            <input :value="autoBackupForm.passphrase" @input="updateAutoBackupField('passphrase', $event.target.value)" type="password" placeholder="Leave blank to keep current" class="h-10 w-full rounded-md border border-indigo-200 bg-white px-3 text-sm text-slate-800 dark:border-indigo-900/60 dark:bg-slate-900 dark:text-white/90" />
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300">Confirm Backup Passphrase</label>
            <input :value="autoBackupForm.passphrase_confirm" @input="updateAutoBackupField('passphrase_confirm', $event.target.value)" type="password" placeholder="Repeat new passphrase" class="h-10 w-full rounded-md border border-indigo-200 bg-white px-3 text-sm text-slate-800 dark:border-indigo-900/60 dark:bg-slate-900 dark:text-white/90" />
          </div>
        </div>

        <p v-if="autoBackupPassphraseError" class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">{{ autoBackupPassphraseError }}</p>
        <p v-else-if="autoBackupConfig?.passphrase_configured" class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">A passphrase is already stored. Leave blank to keep it unchanged.</p>

        <button type="button" @click="saveAutoBackupSettings" :disabled="savingAutoBackup || !!autoBackupPassphraseError" class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-indigo-300 bg-indigo-100 px-4 py-2 text-center text-sm font-semibold text-indigo-900 transition hover:bg-indigo-200 disabled:cursor-not-allowed disabled:opacity-70 dark:border-indigo-900/50 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/40">
          {{ savingAutoBackup ? 'Saving auto backup...' : 'Save auto-backup settings' }}
        </button>
      </div>

      <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Manual Encrypted Tools</h3>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
          <input v-model="backupPassphrase" type="password" placeholder="Passphrase" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-white/90" />
          <input v-model="backupPassphraseConfirm" type="password" placeholder="Confirm Passphrase" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-white/90" />
        </div>
        <p v-if="passphraseError" class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">{{ passphraseError }}</p>

        <div class="mt-3 grid gap-3 md:grid-cols-2">
          <button type="button" @click="runDatabaseBackup" :disabled="backuping || !!passphraseError" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-amber-300 bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-900 transition hover:bg-amber-200 disabled:opacity-70 dark:border-amber-900/50 dark:bg-amber-900/25 dark:text-amber-300">{{ backuping ? 'Creating backup...' : 'Create server backup' }}</button>
          <button type="button" @click="runDatabaseExport" :disabled="exportingBackup || !!passphraseError" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-sky-300 bg-sky-100 px-4 py-2 text-sm font-semibold text-sky-900 transition hover:bg-sky-200 disabled:opacity-70 dark:border-sky-900/50 dark:bg-sky-900/25 dark:text-sky-300">{{ exportingBackup ? 'Exporting...' : 'Export encrypted file' }}</button>
        </div>

        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50/80 p-3 dark:border-rose-900/50 dark:bg-rose-950/20">
          <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-rose-700 dark:text-rose-300">Import backup file</label>
          <input type="file" accept=".bkp,.txt,application/octet-stream" @change="onBackupFileSelected" class="block w-full text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-rose-200 file:px-3 file:py-2 file:font-semibold file:text-rose-900 hover:file:bg-rose-300 dark:text-slate-300 dark:file:bg-rose-900/40 dark:file:text-rose-200" />
          <p v-if="backupFile" class="mt-2 break-words text-[11px] text-slate-600 dark:text-slate-400">Selected: {{ backupFile.name }}</p>
          <button type="button" @click="runDatabaseImport" :disabled="importingBackup || !!passphraseError" class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-rose-300 bg-rose-200 px-4 py-2 text-sm font-semibold text-rose-900 transition hover:bg-rose-300 disabled:opacity-70 dark:border-rose-900/60 dark:bg-rose-900/30 dark:text-rose-300">{{ importingBackup ? 'Importing backup...' : 'Import and replace database' }}</button>
        </div>
      </div>

      <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <div class="flex items-center justify-between gap-3">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Server Backup Files</h3>
          <button type="button" @click="loadAutoBackupStatus" :disabled="loadingAutoBackup" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ loadingAutoBackup ? 'Refreshing...' : 'Refresh' }}</button>
        </div>

        <div v-if="autoBackupFiles.length === 0" class="mt-2 rounded-lg border border-slate-200 bg-white px-3 py-3 text-xs text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500">No backups found yet.</div>
        <div v-else class="mt-2 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
          <table class="min-w-full text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
              <tr>
                <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Filename</th>
                <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Size</th>
                <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Created (UTC)</th>
                <th class="px-3 py-2 text-right font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
              <tr v-for="file in autoBackupFiles" :key="file.filename" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="break-all px-3 py-2 font-mono text-slate-700 dark:text-slate-300">{{ file.filename }}</td>
                <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ formatBytes(file.size_bytes) }}</td>
                <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ formatIso(file.modified_at) }}</td>
                <td class="px-3 py-2 text-right">
                  <div class="inline-flex gap-2">
                    <button type="button" @click="restoreBackupFile(file.filename)" class="rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900/50 dark:bg-emerald-900/25 dark:text-emerald-300">Restore</button>
                    <button type="button" @click="downloadBackupFile(file.filename)" class="rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1 font-semibold text-sky-700 hover:bg-sky-100 dark:border-sky-900/50 dark:bg-sky-900/25 dark:text-sky-300">Download</button>
                    <button type="button" @click="deleteBackupFile(file.filename)" class="rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-900/25 dark:text-rose-300">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</template>
