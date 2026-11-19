<template>
    <div>
        <Modal @close="$emit('close')">
            <template #body>
                <div class="user-form-dialog no-scrollbar relative m-2 max-h-[90vh] w-full max-w-[880px] overflow-y-auto border border-slate-300 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                    <div class="sticky top-0 z-40 flex items-start justify-between gap-3 border-b border-white/10 bg-[linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] px-4 py-3 text-white">
                        <div>
                            <h4 class="text-xs font-semibold text-white">{{ isEditUser ? 'Update User' : 'Add User' }}</h4>
                            <p class="mt-1 text-[11px] text-slate-300">Account, personal details, contacts and addresses.</p>
                        </div>
                    <button @click="$emit('close')"
                        type="button" aria-label="Close user form" class="flex h-7 w-7 shrink-0 items-center justify-center border border-white/20 text-slate-200 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300">
                        <svg class="fill-current" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z"
                                fill="" />
                        </svg>
                    </button>
                    </div>

                    <div v-if="props.saveError" role="alert" class="mb-5 rounded-none border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                        {{ props.saveError }}
                    </div>

                    <form class="flex flex-col gap-y-3 bg-slate-50/60 p-3 dark:bg-slate-950/20" @submit.prevent="submitForm">
                        <section class="rounded-none border border-slate-200 bg-white p-3 dark:border-gray-700">
                            <h5 class="mb-3 border-b border-slate-100 pb-2 text-[10px] font-semibold uppercase tracking-wider text-teal-700 dark:text-gray-300">Account</h5>
                            <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-[96px_minmax(0,1fr)]">
                                <div v-if="!props.isEditUser || authStore.user.role === 0 || authStore.user.role === 1" class="mx-auto w-20 self-start sm:mx-0 sm:w-full sm:self-stretch">
                                    <input type="file" @change="uploadImage($event)" name="image" id="image" accept=".png,.gif,.jpg,.jpeg,.webp,image/png,image/gif,image/jpeg,image/webp" hidden />
                                    <label for="image" class="w-full h-20 sm:min-h-24 sm:h-full group cursor-pointer grid items-center rounded-none bg-teal-50/50 border border-dashed border-teal-300">
                                        <div v-if="!form_data.image" class="text-teal-600 group-hover:scale-105 transition-all">
                                            <p class="text-center text-xl">+</p>
                                            <p class="text-center text-[10px] font-medium">Upload photo</p>
                                        </div>
                                        <div v-else class="relative h-full min-h-20 sm:min-h-24">
                                            <img :src="form_data.image" alt="image" class="absolute inset-0 h-full w-full object-cover" />
                                        </div>
                                    </label>
                                </div>

                                <div class="grid min-w-0 grid-cols-2 gap-2 sm:gap-3" >
                                    <div class="col-span-full grid min-w-0 grid-cols-2 gap-2 sm:grid-cols-3" v-if="!props.isEditUser || authStore.user.role === 0 || authStore.user.role === 1">
                                        <div>
                                            <label for="username" class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Username<span v-if="!form_data.email" class="text-error-500">*</span></label>
                                            <input id="username" v-model="form_data.username" :required="!form_data.email" type="text" autocomplete="off" minlength="3" maxlength="100" pattern="[a-zA-Z0-9._\-]+" title="Use letters, numbers, periods, underscores or hyphens" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs" />
                                        </div>
                                        <div>
                                            <label for="email" class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Email <span class="text-[10px] font-normal text-slate-400">(optional)</span></label>
                                            <input id="email" type="email" v-model="form_data.email"
                                                class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs" />
                                        </div>
                                        <div class="col-span-2 sm:col-span-1">
                                            <label for="password" class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">{{ isEditUser ? 'New ' : '' }}Password<span class="text-error-500">*</span></label>
                                            <div class="relative">
                                                <input :required="!isEditUser" v-model="form_data.password" :type="showPassword ? 'text' : 'password'" id="password" autocomplete="new-password"
                                                    class="h-8 w-full rounded-none border border-gray-300 bg-transparent py-1 pl-2 pr-12 text-xs" />
                                                <span @click="togglePasswordVisibility" class="absolute right-2 top-1/2 z-30 -translate-y-1/2 cursor-pointer text-[11px] font-medium text-teal-700">
                                                    {{ showPassword ? 'Hide' : 'Show' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="authStore.user.role == 1">
                                        <label for="status" class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Status<span class="text-error-500">*</span></label>
                                        <select v-model="form_data.status" id="status" required class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs">
                                            <option :value="1">Active</option>
                                            <option :value="0">Inactive</option>
                                        </select>
                                    </div>
                                    <div v-if="authStore.user.role == 1">
                                        <label for="role" class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Role<span class="text-error-500">*</span></label>
                                        <select v-model="form_data.role" id="role" required class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs">
                                            <option :value="0">User</option>
                                            <option :value="1">Super Admin</option>
                                        </select>
                                    </div>
                                    
                                    
                                    
                                </div>
                            </div>
                        </section>
                        <section v-if="authStore.user.role == 1" class="border border-slate-200 bg-white p-3 dark:border-gray-700">
                            <h5 class="mb-3 border-b border-slate-100 pb-2 text-[10px] font-semibold uppercase tracking-wider text-teal-700 dark:text-gray-300">Work assignment</h5>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
                                <div v-if="authStore.user.role == 1">
                                        <label for="office_shift_id" class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Office Shift</label>
                                        <select v-model="form_data.office_shift_id" id="office_shift_id" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs">
                                            <option :value="null">No Shift</option>
                                            <option v-for="shift in props.officeShifts" :key="`shift-${shift.id}`" :value="shift.id">
                                                {{ shift.name }} - {{ shift.schedule || 'Flexible Time' }}
                                            </option>
                                        </select>
                                    </div>
<div v-if="authStore.user.role == 1">
                                        <label for="department_id" class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Department</label>
                                        <select v-model="form_data.department_id" id="department_id" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs">
                                            <option :value="null">No Department</option>
                                            <option v-for="department in props.departments" :key="`department-${department.id}`" :value="department.id">
                                                {{ department.department_name }}
                                            </option>
                                        </select>
                                    </div>
<div v-if="authStore.user.role == 1">
                                        <label for="college_id" class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">College</label>
                                        <select v-model="form_data.college_id" id="college_id" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs">
                                            <option :value="null">No College</option>
                                            <option v-for="college in props.colleges" :key="`college-${college.id}`" :value="college.id">
                                                {{ college.college_long || college.college_short || `College #${college.id}` }}
                                            </option>
                                        </select>
                                    </div>
                            </div>
                        </section>

                        <section class="rounded-none border border-slate-200 bg-white p-3 dark:border-gray-700" v-if="authStore.user.role == 1">
                            <h5 class="mb-3 border-b border-slate-100 pb-2 text-[10px] font-semibold uppercase tracking-wider text-teal-700 dark:text-gray-300">Profile</h5>
                            <div class="grid grid-cols-2 gap-2 md:grid-cols-3 sm:gap-3">
                                <div class="col-span-full">
                                    <label class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Display Name</label>
                                    <input type="text" v-model="form_data.display_name" placeholder="Optional custom name shown in user table" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Last Name<span class="text-error-500">*</span></label>
                                    <input required type="text" v-model="form_data.last_name" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">First Name<span class="text-error-500">*</span></label>
                                    <input required type="text" v-model="form_data.first_name" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Middle Name</label>
                                    <input type="text" v-model="form_data.middle_name" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Name Extension</label>
                                    <input type="text" v-model="form_data.name_extension" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Birth Date</label>
                                    <input type="date" v-model="form_data.dob" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Gender</label>
                                    <select v-model="form_data.gender" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs">
                                        <option value="">Select Option</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-3 rounded-none border border-slate-200 bg-white p-3 dark:border-gray-700">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h6 class="text-xs font-semibold text-gray-700 dark:text-gray-300">In-Charge</h6>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Assign another existing user as the default signatory for this user's DTR.
                                        </p>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-gray-300">
                                        <input v-model="form_data.in_charge_enabled" type="checkbox" class="h-4 w-4" />
                                        Use in-charge
                                    </label>
                                </div>

                                <div class="mt-3">
                                    <label class="mb-1 block text-[11px] font-medium text-gray-700 dark:text-gray-400">Choose In-Charge</label>
                                    <select
                                        v-model="form_data.in_charge_user_id"
                                        :disabled="!form_data.in_charge_enabled"
                                        class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-2 py-1 text-xs disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <option :value="null">Select user</option>
                                        <option v-for="item in inChargeUsers" :key="`in-charge-${item.id}`" :value="item.id">
                                            {{ getUserOptionLabel(item) }}
                                        </option>
                                    </select>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Display name is used when available, otherwise the user name.</p>
                                </div>
                            </div>
                        </section>

                        <section v-if="!props.isEditUser || authStore.user.role === 0 || authStore.user.role === 1" class="rounded-none border border-slate-200 bg-white p-3 dark:border-gray-700">
                            <div class="mb-3 flex items-center justify-between">
                                <h5 class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Contacts</h5>
                                <button type="button" @click="addContact" class="rounded-none border border-teal-200 px-3 py-1.5 text-xs font-medium text-teal-700">Add Contact</button>
                            </div>
                            <div class="space-y-3">
                                <div v-for="(contact, idx) in form_data.contacts" :key="`contact-${idx}`" class="rounded-none border border-slate-200 bg-white p-2.5 dark:border-gray-700">
                                    <div class="grid min-w-0 grid-cols-2 items-end gap-2 sm:grid-cols-[minmax(100px,1fr)_minmax(0,2fr)]">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Type</label>
                                            <select v-model="contact.type" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-3 text-xs">
                                                <option value="mobile">Mobile</option>
                                                <option value="phone">Phone</option>
                                                <option value="whatsapp">Whatsapp</option>
                                                <option value="email">Email</option>
                                            </select>
                                        </div>
                                        <div class="min-w-0">
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Value</label>
                                            <input type="text" v-model="contact.value" placeholder="e.g. +639123456789" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-3 text-xs" />
                                        </div>
                                        <div class="col-span-full flex flex-wrap items-center justify-end gap-2">
                                            <button type="button" @click="setPrimaryContact(idx)" class="rounded-none border px-3 h-8 text-xs" :class="contact.is_primary ? 'border-green-400 text-green-600' : 'border-gray-300 text-gray-500'">
                                                {{ contact.is_primary ? 'Primary' : 'Set Primary' }}
                                            </button>
                                            <button type="button" @click="removeContact(idx)" class="rounded-none border border-red-300 text-red-600 h-8 px-3 text-xs" :disabled="form_data.contacts.length === 1">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section v-if="!props.isEditUser || authStore.user.role === 0 || authStore.user.role === 1" class="rounded-none border border-slate-200 bg-white p-3 dark:border-gray-700">
                            <div class="mb-3 flex items-center justify-between">
                                <h5 class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Addresses</h5>
                                <button type="button" @click="addAddress" class="rounded-none border border-teal-200 px-3 py-1.5 text-xs font-medium text-teal-700">Add Address</button>
                            </div>
                            <div class="space-y-3">
                                <div v-for="(address, idx) in form_data.addresses" :key="`address-${idx}`" class="rounded-none border border-slate-200 bg-white p-2.5 dark:border-gray-700">
                                    <div class="grid min-w-0 grid-cols-2 lg:grid-cols-3 gap-2 items-end">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Label</label>
                                            <select v-model="address.label" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-3 text-xs">
                                                <option value="home">Home</option>
                                                <option value="work">Work</option>
                                                <option value="billing">Billing</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="min-w-0">
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Address 1</label>
                                            <input type="text" v-model="address.address1" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-3 text-xs" />
                                        </div>
                                        <div class="min-w-0">
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Address 2</label>
                                            <input type="text" v-model="address.address2" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-3 text-xs" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Barangay</label>
                                            <input type="text" v-model="address.barangay" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-3 text-xs" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Municipality</label>
                                            <input type="text" v-model="address.municipality" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-3 text-xs" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Province</label>
                                            <input type="text" v-model="address.province" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-3 text-xs" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Zipcode</label>
                                            <input type="text" v-model="address.zipcode" class="h-8 w-full rounded-none border border-gray-300 bg-transparent px-3 text-xs" />
                                        </div>
                                        <div class="col-span-full flex flex-wrap items-center justify-end gap-2">
                                            <button type="button" @click="setPrimaryAddress(idx)" class="rounded-none border px-3 h-8 text-xs" :class="address.is_primary ? 'border-green-400 text-green-600' : 'border-gray-300 text-gray-500'">
                                                {{ address.is_primary ? 'Primary' : 'Set Primary' }}
                                            </button>
                                            <button type="button" @click="removeAddress(idx)" class="rounded-none border border-red-300 text-red-600 h-8 px-3 text-xs" :disabled="form_data.addresses.length === 1">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="sticky bottom-0 z-30 -mx-3 -mb-3 mt-1 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-3 py-3 dark:border-slate-700 dark:bg-slate-900">
                            <button @click="$emit('close')" type="button" class="flex w-full justify-center rounded-none border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 sm:w-auto">Close</button>
                            <button type="submit" class="flex w-full justify-center rounded-none border border-cyan-300/30 bg-[linear-gradient(135deg,_#0891b2_0%,_#0f766e_100%)] px-2 py-1 text-xs font-medium text-white hover:bg-[linear-gradient(135deg,_#0e7490_0%,_#115e59_100%)] sm:w-auto">{{ isEditUser ? 'Update Changes' : 'Save' }}</button>
                        </div>
                    </form>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/store/AuthStore'
const authStore = useAuthStore();
import Modal from '@/components/common/Modal.vue'

const props = defineProps({
    user: Object,
    officeShifts: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    colleges: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
    isEditUser: Boolean,
    authUser: Object,
    edit_type: {
        type: Number,
        default: 1,
    },
    saveError: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['save', 'close'])

const newContact = () => ({ id: null, type: 'mobile', value: '', is_primary: false })
const newAddress = () => ({ id: null, label: 'home', address1: '', address2: '', barangay: '', municipality: '', province: '', zipcode: '', is_primary: false })

const createDefaultFormData = () => ({
    id: null,
    name: '',
    display_name: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    username: '',
    status: 1,
    role: 0,
    office_shift_id: null,
    department_id: null,
    college_id: null,
    in_charge_enabled: false,
    in_charge_user_id: null,
    image: '',
    thumbnail: '',
    name_extension: '',
    dob: '',
    gender: '',
    password: '',
    contacts: [Object.assign(newContact(), { is_primary: true })],
    addresses: [Object.assign(newAddress(), { is_primary: true })],
})

const normalizeNullableNumber = (value) => {
    if (value === '' || value === null || value === undefined) {
        return null
    }

    const parsed = Number(value)
    return Number.isNaN(parsed) ? null : parsed
}

const normalizeDateInputValue = (value) => {
    if (!value) {
        return ''
    }

    if (typeof value === 'string') {
        const trimmed = value.trim()
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return trimmed
        }

        const parsed = new Date(trimmed)
        if (!Number.isNaN(parsed.getTime())) {
            const y = parsed.getFullYear()
            const m = String(parsed.getMonth() + 1).padStart(2, '0')
            const d = String(parsed.getDate()).padStart(2, '0')
            return `${y}-${m}-${d}`
        }
    }

    return ''
}

const hasAddressContent = (address) => {
    return [
        address?.address1,
        address?.address2,
        address?.barangay,
        address?.municipality,
        address?.province,
        address?.zipcode,
    ].some((value) => String(value || '').trim() !== '')
}

const hydrateForm = () => {
    if (!(props.isEditUser && props.user)) {
        form_data.value = createDefaultFormData()
        showPassword.value = false
        return
    }

    form_data.value = {
        ...createDefaultFormData(),
        ...JSON.parse(JSON.stringify(props.user)),
        id: normalizeNullableNumber(props.user.id),
        username: props.user.username || '',
        display_name: props.user.display_name ?? props.user.profile?.display_name ?? '',
        status: Number(props.user.status ?? 1),
        role: Number(props.user.role ?? 0),
        office_shift_id: normalizeNullableNumber(props.user.office_shift_id),
        department_id: normalizeNullableNumber(props.user.department_id),
        college_id: normalizeNullableNumber(props.user.college_id),
        in_charge_enabled: Boolean(props.user.in_charge_enabled),
        in_charge_user_id: normalizeNullableNumber(props.user.in_charge_user_id ?? props.user.in_charge_user?.id),
        first_name: props.user.first_name ?? props.user.profile?.first_name ?? '',
        middle_name: props.user.middle_name ?? props.user.profile?.middle_name ?? '',
        last_name: props.user.last_name ?? props.user.profile?.last_name ?? '',
        name_extension: props.user.name_extension ?? props.user.profile?.name_extension ?? '',
        dob: normalizeDateInputValue(props.user.dob || props.user.profile?.dob),
        gender: props.user.gender ?? props.user.profile?.gender ?? '',
        image: props.user.image ?? props.user.profile?.image ?? '',
        thumbnail: props.user.thumbnail ?? props.user.profile?.thumbnail ?? '',
        password: '',
        contacts: props.user.contacts?.length
            ? JSON.parse(JSON.stringify(props.user.contacts)).map((item) => ({
                id: normalizeNullableNumber(item.id),
                type: item.type || 'mobile',
                value: item.value || '',
                is_primary: Boolean(item.is_primary),
            }))
            : [Object.assign(newContact(), { is_primary: true })],
        addresses: props.user.addresses?.length
            ? JSON.parse(JSON.stringify(props.user.addresses)).map((item) => ({
                id: normalizeNullableNumber(item.id),
                label: item.label || 'home',
                address1: item.address1 || '',
                address2: item.address2 || '',
                barangay: item.barangay || '',
                municipality: item.municipality || '',
                province: item.province || '',
                zipcode: item.zipcode || '',
                is_primary: Boolean(item.is_primary),
            }))
            : [Object.assign(newAddress(), { is_primary: true })],
    }

    showPassword.value = false
}

const form_data = ref(createDefaultFormData())

const showPassword = ref(false)

const getUserOptionLabel = (user) => {
    const displayName = String(user?.display_name || user?.profile?.display_name || '').trim()
    return displayName || user?.name || `User #${user?.id || ''}`
}

const inChargeUsers = computed(() => {
    const currentUserId = Number(form_data.value.id || 0)
    return (Array.isArray(props.users) ? props.users : []).filter((user) => Number(user?.id || 0) !== currentUserId)
})

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value
}

const setPrimaryContact = (index) => {
    form_data.value.contacts = form_data.value.contacts.map((item, idx) => ({ ...item, is_primary: idx === index }))
}

const setPrimaryAddress = (index) => {
    form_data.value.addresses = form_data.value.addresses.map((item, idx) => ({ ...item, is_primary: idx === index }))
}

const addContact = () => {
    form_data.value.contacts.push(newContact())
}

const removeContact = (index) => {
    if (form_data.value.contacts.length === 1) return
    const removedPrimary = form_data.value.contacts[index]?.is_primary
    form_data.value.contacts.splice(index, 1)
    if (removedPrimary && form_data.value.contacts.length) {
        form_data.value.contacts[0].is_primary = true
    }
}

const addAddress = () => {
    form_data.value.addresses.push(newAddress())
}

const removeAddress = (index) => {
    if (form_data.value.addresses.length === 1) return
    const removedPrimary = form_data.value.addresses[index]?.is_primary
    form_data.value.addresses.splice(index, 1)
    if (removedPrimary && form_data.value.addresses.length) {
        form_data.value.addresses[0].is_primary = true
    }
}

const submitForm = () => {
    const payload = {
        ...form_data.value,
        id: normalizeNullableNumber(form_data.value.id),
        username: form_data.value.username?.trim() || null,
        email: form_data.value.email?.trim() || null,
        status: Number(form_data.value.status ?? 1),
        role: Number(form_data.value.role ?? 0),
        office_shift_id: normalizeNullableNumber(form_data.value.office_shift_id),
        department_id: normalizeNullableNumber(form_data.value.department_id),
        college_id: normalizeNullableNumber(form_data.value.college_id),
        in_charge_enabled: Boolean(form_data.value.in_charge_enabled),
        in_charge_user_id: normalizeNullableNumber(form_data.value.in_charge_user_id),
        contacts: form_data.value.contacts.filter((item) => item.type || item.value),
        addresses: form_data.value.addresses.filter((item) => normalizeNullableNumber(item?.id) !== null || hasAddressContent(item)),
    }

    const primaryContact = payload.contacts.find((item) => item.is_primary) || payload.contacts[0]
    const primaryAddress = payload.addresses.find((item) => item.is_primary) || payload.addresses[0]

    payload.contact_type = primaryContact?.type || ''
    payload.contact_value = primaryContact?.value || ''
    payload.address_label = primaryAddress?.label || ''
    payload.address1 = primaryAddress?.address1 || ''
    payload.address2 = primaryAddress?.address2 || ''
    payload.barangay = primaryAddress?.barangay || ''
    payload.municipality = primaryAddress?.municipality || ''
    payload.province = primaryAddress?.province || ''
    payload.zipcode = primaryAddress?.zipcode || ''

    emit('save', payload)
}

watch(() => [props.user, props.isEditUser], hydrateForm, { immediate: true, deep: true })

const uploadImage = async (event) => {
    var images = event.target.files
    if (images.length <= 0) {
        return
    }

    const formData = new FormData()
    formData.append('file', images[0])

    try {
        const response = await axios.post('/api/media/upload', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })
        form_data.value.image = response.data.path
        form_data.value.thumbnail = response.data.data.thumbnail || response.data.path
    } catch (error) {
        console.error('Server Error:', error.response)
    }
}
</script>

<style scoped>
.user-form-dialog form .grid > div { min-width: 0; }
.user-form-dialog input:not([type="checkbox"]):not([type="file"]),
.user-form-dialog select { min-width: 0; max-width: 100%; font-size: 11px; color: #0f172a; border-color: #cbd5e1; }
.user-form-dialog input:focus,
.user-form-dialog select:focus { outline: none; border-color: #0d9488; box-shadow: 0 0 0 2px rgb(20 184 166 / 15%); }
.user-form-dialog input[type="checkbox"] { accent-color: #0f766e; }
.user-form-dialog button:focus-visible { outline: 2px solid #14b8a6; outline-offset: 2px; }
.dark .user-form-dialog input:not([type="checkbox"]):not([type="file"]),
.dark .user-form-dialog select { color: #e2e8f0; background-color: #0f172a; border-color: #475569; }
.dark .user-form-dialog section { background-color: #0f172a; }
</style>
