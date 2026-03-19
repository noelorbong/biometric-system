<template>
  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

    <!-- Profile Details -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <h5 class="mb-4 text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Profile Details</h5>
      <dl class="space-y-2.5">
        <div v-for="(item, i) in profileFields" :key="`pf-${i}`"
          class="flex items-center justify-between gap-4 border-b border-gray-100 pb-2.5 last:border-0 last:pb-0 dark:border-gray-800">
          <dt class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500">{{ item.label }}</dt>
          <dd class="text-right text-sm font-medium text-gray-800 dark:text-gray-200">{{ item.value }}</dd>
        </div>
      </dl>
    </div>

    <!-- Contacts -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <h5 class="mb-4 text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Contacts</h5>
      <div v-if="user.contacts?.length" class="space-y-2.5">
        <div
          v-for="contact in user.contacts"
          :key="`contact-${contact.id || contact.value}`"
          class="flex items-start justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/40"
        >
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ contact.type || 'contact' }}</p>
            <p class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ contact.value || '-' }}</p>
          </div>
          <span
            v-if="contact.is_primary"
            class="mt-0.5 shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-600 dark:bg-brand-900/30 dark:text-brand-400"
          >Primary</span>
        </div>
      </div>
      <p v-else class="text-sm text-gray-400 dark:text-gray-500">No contact records</p>
    </div>

    <!-- Addresses -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:col-span-2">
      <h5 class="mb-4 text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Addresses</h5>
      <div v-if="user.addresses?.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div
          v-for="address in user.addresses"
          :key="`address-${address.id || address.address1}`"
          class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/40"
        >
          <div class="mb-1.5 flex items-center justify-between gap-2">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ address.label || 'address' }}</p>
            <span
              v-if="address.is_primary"
              class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-600 dark:bg-brand-900/30 dark:text-brand-400"
            >Primary</span>
          </div>
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatAddress(address) }}</p>
        </div>
      </div>
      <p v-else class="text-sm text-gray-400 dark:text-gray-500">No address records</p>
    </div>

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
  { label: 'Biometric ID',    value: props.user.biometric_info?.user_id || '-' },
])

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
</script>
