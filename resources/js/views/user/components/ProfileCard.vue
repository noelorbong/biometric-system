<template>
  <div class="grid items-start gap-3 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
    <div class="min-w-0 space-y-3">
      <section class="min-w-0 border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center gap-3 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-teal-50/60 px-4 py-3 dark:border-slate-700 dark:from-slate-800 dark:to-teal-950/30">
          <img :src="user.profile?.thumbnail || user.profile?.image || user.thumbnail || user.image || '/images/extras/add-image.png'" alt="Profile photo" class="h-12 w-12 shrink-0 border border-white bg-white object-cover shadow-sm dark:border-slate-600" />
          <div class="min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-teal-700 dark:text-teal-400">Profile details</p>
            <h2 :title="profileName" class="mt-0.5 truncate text-sm font-semibold text-slate-900 dark:text-white">{{ profileName }}</h2>
            <p class="mt-0.5 break-all text-[11px] text-slate-500 dark:text-slate-400">{{ user.email || 'No email recorded' }}</p>
          </div>
        </div>
        <div class="px-4 py-3">
          <h3 class="mb-3 flex items-center gap-2 text-[11px] font-semibold text-slate-700 dark:text-slate-200"><span class="h-3 w-0.5 bg-teal-600"></span>Personal information</h3>
          <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
            <div v-for="item in personalFields" :key="item.label" class="min-w-0">
              <dt class="text-[10px] text-slate-500 dark:text-slate-400">{{ item.label }}</dt>
              <dd class="mt-1 break-words text-xs font-medium text-slate-800 dark:text-slate-100">{{ item.value }}</dd>
            </div>
          </dl>
        </div>
        <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
          <h3 class="mb-3 flex items-center gap-2 text-[11px] font-semibold text-slate-700 dark:text-slate-200"><span class="h-3 w-0.5 bg-teal-600"></span>Work &amp; account</h3>
          <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
            <div v-for="item in workFields" :key="item.label" class="min-w-0" :class="{ 'col-span-2': item.label === 'Department' }">
              <dt class="text-[10px] text-slate-500 dark:text-slate-400">{{ item.label }}</dt>
              <dd class="mt-1 break-words text-xs font-medium text-slate-800 dark:text-slate-100">{{ item.value }}</dd>
            </div>
          </dl>
        </div>
      </section>
      <div class="grid items-start gap-3 sm:grid-cols-2">
        <section class="border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
            <svg class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1" /><path d="m3 6 9 7 9-7" /></svg>
            <h3 class="text-xs font-semibold text-slate-800 dark:text-slate-100">Contacts</h3>
            <span class="ml-auto text-[10px] text-slate-500">{{ user.contacts?.length || 0 }} records</span>
          </div>
          <div v-if="user.contacts?.length" class="divide-y divide-slate-100 px-4 dark:divide-slate-800">
            <div v-for="contact in user.contacts" :key="contact.id || contact.value" class="flex items-start justify-between gap-3 py-3">
              <div class="min-w-0"><p class="text-[10px] capitalize text-slate-500 dark:text-slate-400">{{ contact.type || 'Contact' }}</p><p class="mt-1 break-all text-xs font-medium text-slate-800 dark:text-slate-100">{{ contact.value || '-' }}</p></div>
              <span v-if="contact.is_primary" class="shrink-0 bg-teal-50 px-1.5 py-0.5 text-[10px] font-semibold text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">Primary</span>
            </div>
          </div>
          <div v-else class="px-4 py-5"><p class="text-xs font-medium text-slate-600 dark:text-slate-300">No contact records</p><p class="mt-1 text-[11px] text-slate-400">Contact details will appear here when added.</p></div>
        </section>
        <section class="border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
            <svg class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></svg>
            <h3 class="text-xs font-semibold text-slate-800 dark:text-slate-100">Addresses</h3>
            <span class="ml-auto text-[10px] text-slate-500">{{ user.addresses?.length || 0 }} records</span>
          </div>
          <div v-if="user.addresses?.length" class="divide-y divide-slate-100 px-4 dark:divide-slate-800">
            <div v-for="address in user.addresses" :key="address.id || address.address1" class="py-3">
              <div class="mb-1 flex items-center justify-between gap-2"><p class="text-[10px] capitalize text-slate-500 dark:text-slate-400">{{ address.label || 'Address' }}</p><span v-if="address.is_primary" class="bg-teal-50 px-1.5 py-0.5 text-[10px] font-semibold text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">Primary</span></div>
              <p class="break-words text-xs leading-relaxed text-slate-800 dark:text-slate-100">{{ formatAddress(address) }}</p>
            </div>
          </div>
          <div v-else class="px-4 py-5"><p class="text-xs font-medium text-slate-600 dark:text-slate-300">No address records</p><p class="mt-1 text-[11px] text-slate-400">Saved addresses will appear here.</p></div>
        </section>
      </div>
    </div>
    <section class="border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
        <div class="flex flex-wrap items-center gap-2">
          <svg class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
          <h3 class="text-xs font-semibold text-slate-800 dark:text-slate-100">Office shift schedule</h3>
          <span class="border border-teal-200 bg-teal-50 px-2 py-0.5 text-[10px] font-semibold text-teal-700 dark:border-teal-800 dark:bg-teal-900/30 dark:text-teal-300">{{ officeShift?.name || 'No shift assigned' }}</span>
        </div>
        <span v-if="officeShift?.grace_enabled" class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Grace -{{ officeShift.grace_before_minutes || 0 }} / +{{ officeShift.grace_after_minutes || 0 }} min</span>
      </div>
      <div v-if="officeShiftSchedules.length" class="grid gap-3 p-3">
        <div v-for="(schedule, index) in officeShiftSchedules" :key="schedule.id || index" class="border border-slate-200 dark:border-slate-700">
          <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/40">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-400">Schedule {{ schedule.sequence || index + 1 }}</p>
            <span v-if="schedule.is_next_day" class="bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Next day</span>
          </div>
          <div class="flex items-center justify-between gap-3 px-3 py-3">
            <div><p class="text-[10px] text-slate-500 dark:text-slate-400">Time in</p><p class="mt-1 text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ formatScheduleTime(schedule.time_in) }}</p></div>
            <svg class="h-4 w-4 text-teal-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6" /></svg>
            <div class="text-right"><p class="text-[10px] text-slate-500 dark:text-slate-400">Time out</p><p class="mt-1 text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ formatScheduleTime(schedule.time_out) }}</p></div>
          </div>
        </div>
      </div>
      <p v-else class="px-4 py-5 text-xs text-slate-500 dark:text-slate-400">{{ officeShift?.is_flexible ? 'Flexible time. No fixed schedule.' : 'No office shift schedule recorded.' }}</p>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  user: {
    type: Object,
    default: () => ({}),
  }
})

const roleMap = {
  0: 'User',
  1: 'Super Admin',
  2: 'Region Admin',
  3: 'SUC Admin',
  4: 'Campus Admin',
  5: 'College Admin',
  6: 'Employee',
}

const departmentDisplay = computed(() => {
  const name = props.user?.department_ref?.department_name || props.user?.department
  if (!name) return props.user?.department_id ? `#${props.user.department_id}` : '-'
  return props.user?.department_id ? `${name} (#${props.user.department_id})` : name
})

const collegeDisplay = computed(() => {
  const name = props.user?.college_ref?.college_long || props.user?.college_ref?.college_short
  if (!name) return props.user?.college_id ? `#${props.user.college_id}` : '-'
  return props.user?.college_id ? `${name} (#${props.user.college_id})` : name
})

const officeShift = computed(() => props.user.office_shift || props.user.officeShift || null)

const officeShiftSchedules = computed(() => {
  return [...(officeShift.value?.schedules || [])].sort((a, b) => Number(a.sequence || 0) - Number(b.sequence || 0))
})

const formatDob = (value) => {
  if (!value) return '-'
  if (/^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
    const [y, m, d] = String(value).split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' })
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Date(date.getFullYear(), date.getMonth(), date.getDate())
    .toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' })
}

const profileFields = computed(() => [
  { label: 'Email',          value: props.user.email || '-' },
  { label: 'First Name',      value: props.user.profile?.first_name || '-' },
  { label: 'Middle Name',     value: props.user.profile?.middle_name || '-' },
  { label: 'Last Name',       value: props.user.profile?.last_name || '-' },
  { label: 'Name Extension',  value: props.user.profile?.name_extension || '-' },
  { label: 'Birth Date',      value: formatDob(props.user.profile?.dob || props.user.dob) },
  { label: 'Gender',          value: props.user.profile?.gender || '-' },
  { label: 'Department',      value: departmentDisplay.value },
  { label: 'College',         value: collegeDisplay.value },
  { label: 'Office Shift',    value: props.user.office_shift?.name || props.user.officeShift?.name || '-' },
  { label: 'Role',            value: roleMap[props.user.role] || 'User' },
  { label: 'Username',        value: props.user.username || '-' },
  { label: 'Biometric ID',    value: props.user.biometric_info?.user_id || '-' },
])

const personalFields = computed(() => profileFields.value.slice(1, 7))
const workFields = computed(() => profileFields.value.slice(7))
const profileName = computed(() => props.user.profile?.display_name || props.user.display_name || [props.user.profile?.first_name, props.user.profile?.middle_name, props.user.profile?.last_name, props.user.profile?.name_extension].filter(Boolean).join(' ') || props.user.name || 'User profile')

const formatAddress = (address) => {
  return [
    address?.address1,
    address?.address2,
    address?.barangay,
    address?.municipality,
    address?.province,
    address?.zipcode,
  ].filter(Boolean).join(', ') || '-'
}

const formatScheduleTime = (value) => {
  if (!value) {
    return '--:--'
  }

  const [hoursRaw, minutesRaw] = String(value).split(':')
  const hours = Number(hoursRaw)
  const minutes = Number(minutesRaw)

  if (Number.isNaN(hours) || Number.isNaN(minutes)) {
    return String(value)
  }

  const suffix = hours >= 12 ? 'PM' : 'AM'
  const displayHour = hours % 12 === 0 ? 12 : hours % 12

  return `${displayHour}:${String(minutes).padStart(2, '0')} ${suffix}`
}
</script>

