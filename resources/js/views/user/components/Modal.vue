<template>
    <div>
        <Modal @close="$emit('close')">
            <template #body>
                <div class="no-scrollbar relative w-full max-w-[880px] max-h-[90vh] overflow-y-auto m-2 rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                    <button @click="$emit('close')"
                        class="transition-color absolute right-5 top-5 z-999 flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:bg-gray-700 dark:bg-white/[0.05] dark:text-gray-400 dark:hover:bg-white/[0.07] dark:hover:text-gray-300">
                        <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z"
                                fill="" />
                        </svg>
                    </button>

                    <div class="px-2 pr-14">
                        <h4 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                            {{ isEditUser ? 'Update User' : 'Add User' }}
                        </h4>
                        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">
                            Add one account with multiple contacts and addresses.
                        </p>
                    </div>

                    <form class="flex flex-col gap-y-5" @submit.prevent="submitForm">
                        <section class="rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
                            <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Account</h5>
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-start">
                                <div class="lg:col-span-1">
                                    <input type="file" @change="uploadImage($event)" name="image" id="image" accept="image/png, image/gif, image/jpeg" hidden />
                                    <label for="image" class="w-full h-32 group cursor-pointer grid items-center rounded bg-yellow-50/10 border-2 border-dashed border-yellow-300">
                                        <div v-if="!form_data.image" class="text-yellow-300 group-hover:scale-105 transition-all">
                                            <p class="text-center font-extralight text-2xl"><i class="fa-solid fa-plus"></i></p>
                                            <p class="text-center uppercase text-xs font-medium">User Image</p>
                                        </div>
                                        <div v-else class="relative">
                                            <img :src="form_data.image" alt="image" class="h-32 w-full object-cover" />
                                        </div>
                                    </label>
                                </div>

                                <div class="lg:col-span-3 grid grid-cols-1 lg:grid-cols-2 gap-3">
                                    <div>
                                        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email<span class="text-error-500">*</span></label>
                                        <input id="email" required type="email" v-model="form_data.email"
                                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                    </div>
                                    <div>
                                        <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">{{ isEditUser ? 'New ' : '' }}Password<span class="text-error-500">*</span></label>
                                        <div class="relative">
                                            <input :required="!isEditUser" v-model="form_data.password" :type="showPassword ? 'text' : 'password'" id="password" autocomplete="new-password"
                                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pl-4 pr-11 text-sm" />
                                            <span @click="togglePasswordVisibility" class="absolute z-30 text-gray-500 -translate-y-1/2 cursor-pointer right-4 top-1/2">
                                                {{ showPassword ? 'Hide' : 'Show' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="status" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status<span class="text-error-500">*</span></label>
                                        <select v-model="form_data.status" id="status" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm">
                                            <option :value="1">Active</option>
                                            <option :value="0">Inactive</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="role" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Role<span class="text-error-500">*</span></label>
                                        <select v-model="form_data.role" id="role" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm">
                                            <option :value="0">User</option>
                                            <option :value="1">Super Admin</option>
                                        </select>
                                    </div>
                                    <div class="lg:col-span-2">
                                        <label for="office_shift_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Office Shift</label>
                                        <select v-model="form_data.office_shift_id" id="office_shift_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm">
                                            <option :value="null">No Shift</option>
                                            <option v-for="shift in props.officeShifts" :key="`shift-${shift.id}`" :value="shift.id">
                                                {{ shift.name }} - {{ shift.schedule || 'Flexible Time' }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="department_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Department</label>
                                        <select v-model="form_data.department_id" id="department_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm">
                                            <option :value="null">No Department</option>
                                            <option v-for="department in props.departments" :key="`department-${department.id}`" :value="department.id">
                                                {{ department.department_name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="college_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">College</label>
                                        <select v-model="form_data.college_id" id="college_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm">
                                            <option :value="null">No College</option>
                                            <option v-for="college in props.colleges" :key="`college-${college.id}`" :value="college.id">
                                                {{ college.college_long || college.college_short || `College #${college.id}` }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
                            <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Profile</h5>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Last Name<span class="text-error-500">*</span></label>
                                    <input required type="text" v-model="form_data.last_name" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">First Name<span class="text-error-500">*</span></label>
                                    <input required type="text" v-model="form_data.first_name" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Middle Name</label>
                                    <input type="text" v-model="form_data.middle_name" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name Extension</label>
                                    <input type="text" v-model="form_data.name_extension" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Birth Date</label>
                                    <input type="date" v-model="form_data.dob" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Gender</label>
                                    <select v-model="form_data.gender" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm">
                                        <option value="">Select Option</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="mb-3 flex items-center justify-between">
                                <h5 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Contacts</h5>
                                <button type="button" @click="addContact" class="rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600">Add Contact</button>
                            </div>
                            <div class="space-y-3">
                                <div v-for="(contact, idx) in form_data.contacts" :key="`contact-${idx}`" class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-2 items-end">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Type</label>
                                            <select v-model="contact.type" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm">
                                                <option value="mobile">Mobile</option>
                                                <option value="phone">Phone</option>
                                                <option value="whatsapp">Whatsapp</option>
                                                <option value="email">Email</option>
                                            </select>
                                        </div>
                                        <div class="lg:col-span-2">
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Value</label>
                                            <input type="text" v-model="contact.value" placeholder="e.g. +639123456789" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm" />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="setPrimaryContact(idx)" class="rounded-lg border px-3 h-10 text-xs" :class="contact.is_primary ? 'border-green-400 text-green-600' : 'border-gray-300 text-gray-500'">
                                                {{ contact.is_primary ? 'Primary' : 'Set Primary' }}
                                            </button>
                                            <button type="button" @click="removeContact(idx)" class="rounded-lg border border-red-300 text-red-600 h-10 px-3 text-xs" :disabled="form_data.contacts.length === 1">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="mb-3 flex items-center justify-between">
                                <h5 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Addresses</h5>
                                <button type="button" @click="addAddress" class="rounded-lg border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-600">Add Address</button>
                            </div>
                            <div class="space-y-3">
                                <div v-for="(address, idx) in form_data.addresses" :key="`address-${idx}`" class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                    <div class="grid grid-cols-1 lg:grid-cols-6 gap-2 items-end">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Label</label>
                                            <select v-model="address.label" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm">
                                                <option value="home">Home</option>
                                                <option value="work">Work</option>
                                                <option value="billing">Billing</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="lg:col-span-2">
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Address 1</label>
                                            <input type="text" v-model="address.address1" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm" />
                                        </div>
                                        <div class="lg:col-span-2">
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Address 2</label>
                                            <input type="text" v-model="address.address2" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Barangay</label>
                                            <input type="text" v-model="address.barangay" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Municipality</label>
                                            <input type="text" v-model="address.municipality" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Province</label>
                                            <input type="text" v-model="address.province" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-300">Zipcode</label>
                                            <input type="text" v-model="address.zipcode" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm" />
                                        </div>
                                        <div class="lg:col-span-2 flex items-center gap-2">
                                            <button type="button" @click="setPrimaryAddress(idx)" class="rounded-lg border px-3 h-10 text-xs" :class="address.is_primary ? 'border-green-400 text-green-600' : 'border-gray-300 text-gray-500'">
                                                {{ address.is_primary ? 'Primary' : 'Set Primary' }}
                                            </button>
                                            <button type="button" @click="removeAddress(idx)" class="rounded-lg border border-red-300 text-red-600 h-10 px-3 text-xs" :disabled="form_data.addresses.length === 1">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="flex items-center gap-3 mt-1 lg:justify-end">
                            <button @click="$emit('close')" type="button" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto">Close</button>
                            <button type="submit" class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">{{ isEditUser ? 'Update Changes' : 'Save' }}</button>
                        </div>
                    </form>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
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
    isEditUser: Boolean,
    authUser: Object,
    edit_type: {
        type: Number,
        default: 1,
    },
})

const emit = defineEmits(['save', 'close'])

const newContact = () => ({ id: null, type: 'mobile', value: '', is_primary: false })
const newAddress = () => ({ id: null, label: 'home', address1: '', address2: '', barangay: '', municipality: '', province: '', zipcode: '', is_primary: false })

const createDefaultFormData = () => ({
    id: null,
    name: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    status: 1,
    role: 0,
    office_shift_id: null,
    department_id: null,
    college_id: null,
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
        status: Number(props.user.status ?? 1),
        role: Number(props.user.role ?? 0),
        office_shift_id: normalizeNullableNumber(props.user.office_shift_id),
        department_id: normalizeNullableNumber(props.user.department_id),
        college_id: normalizeNullableNumber(props.user.college_id),
        dob: normalizeDateInputValue(props.user.dob || props.user.profile?.dob),
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
        status: Number(form_data.value.status ?? 1),
        role: Number(form_data.value.role ?? 0),
        office_shift_id: normalizeNullableNumber(form_data.value.office_shift_id),
        department_id: normalizeNullableNumber(form_data.value.department_id),
        college_id: normalizeNullableNumber(form_data.value.college_id),
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
        form_data.value.thumbnail = response.data.data.thumbnail
    } catch (error) {
        console.error('Server Error:', error.response)
    }
}
</script>

<style scoped></style>
