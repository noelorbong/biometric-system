<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import Swal from "sweetalert2";
import 'sweetalert2/src/sweetalert2.scss'
import UserTable from './components/Table.vue'
import ModalUser from './components/Modal.vue'
import ModalDelete from '@/components/common/ModalDelete.vue'
import { PlusIcon } from '@/icons'
import Button from '@/components/ui/Button.vue'
import { useUserStore } from '@/store/UserStore'
import { useLoadingStore } from '@/store/LoadingStore'
import { useMachineStore } from '@/store/MachineStore'
import ImportUserDatModal from './components/ImportUserDatModal.vue'
import ImportBiometricTemplateDatModal from './components/ImportBiometricTemplateDatModal.vue'

import FingerprintEnrollModal from './components/FingerprintEnrollModal.vue'
import { useAuthStore } from '@/store/AuthStore'

const props = defineProps({
  web_layout: {
    type: Object,
    default: () => {},
  }
})

const authStore = useAuthStore();
const router = useRouter();

const loadingStore = useLoadingStore();
const { isLoading, text } = storeToRefs(loadingStore)

const userStore = useUserStore();
const { users, officeShifts, departments, colleges } = storeToRefs(userStore)
const machineStore = useMachineStore();
const { machines } = storeToRefs(machineStore)


const isUserAddModal = ref(false)
const isImportUserDatModal = ref(false)
const isImportBiometricTemplateDatModal = ref(false)
const isDeleteModal = ref(false)
const enrollModal    = ref({ open: false, user: null, machineId: null, machineName: '' })
const enrollLoading  = ref(false)
const enrollStatusText = ref('')
const enrollCompletedFingers = ref([])
const enrollActiveFinger = ref(null)
const enrollLastCompletedFinger = ref(null)
const enrollPollToken = ref(0)
const enrollRequestToken = ref(0)
let enrollAbortController = null
let enrollmentStartRequest = null
const enrollmentMayBeActive = ref(false)
const enrollmentClosing = ref(false)

const isEditUser = ref(false)
const search_user = ref('')
const user = ref({ name: '' })

const Toast = Swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 1500,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.onmouseenter = Swal.stopTimer;
    toast.onmouseleave = Swal.resumeTimer;
  }
});

onMounted(() => {
  userStore.loadUsers();
  machineStore.loadMachines();
})

const managedUsers = computed(() => users.value.filter((item) => item.id !== authStore.user.id))

var filteredUsers = computed(() => sortedUsers.value.filter((item) => {
  return (
    (
      (item.profile?.display_name || '')
      +" "+
      ((item.profile?.first_name || '') + ' ' + (item.profile?.last_name || '')).trim()
      +" "+
      item.email
      +" "+
      (item.primary_contact?.value || '')
    ).toLowerCase().indexOf(search_user.value.toLowerCase()) > -1);
}))

var sortedUsers = computed(() => [...managedUsers.value].sort((a, b) => {
  if (a.name > b.name) return 1;
  if (a.name < b.name) return -1;
  return 0
}))

const userStats = computed(() => {
  const total = managedUsers.value.length
  const active = managedUsers.value.filter((item) => Number(item.status) === 1).length
  const withShift = managedUsers.value.filter((item) => item.office_shift_id !== null && item.office_shift_id !== undefined).length
  const withAffiliation = managedUsers.value.filter((item) => item.department_id || item.college_id).length

  return {
    total,
    active,
    withShift,
    withAffiliation,
  }
})

const toastResult = (message, icon)=>{
  Toast.fire({
        icon: icon,
        title: message
      });
}

const saveUser = async (event) => {
  isLoading.value = true;
  if (isEditUser.value) {
    text.value = 'Updating User..';
    var result = await userStore.updateUser(event);
    if (result.success) {
      toastResult('Updated Successfully', 'success');
      isUserAddModal.value = false;
    } else {
      toastResult('Unable to update user', 'error');
    }
  } else {
    text.value = 'Storing User..';
    var result = await userStore.storeUser(event);
    if (result.success) {
      toastResult('Stored Successfully', 'success');
      isUserAddModal.value = false;
    } else {
      toastResult('Unable to create user', 'error');
    }
  }

  isLoading.value = false;
}

const addUser = () => {
  isUserAddModal.value = true;
  isEditUser.value = false;
  user.value = {
    name: '',
    display_name: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    name_extension: '',
    dob: '',
    gender: '',
    image: '',
    thumbnail: '',
    contact_type: '',
    contact_value: '',
    contacts: [{ id: null, type: 'mobile', value: '', is_primary: true }],
    address_label: '',
    address1: '',
    address2: '',
    barangay: '',
    municipality: '',
    province: '',
    zipcode: '',
    addresses: [{ id: null, label: 'home', address1: '', address2: '', barangay: '', municipality: '', province: '', zipcode: '', is_primary: true }],
    email: '',
    status: 1,
    role: 0,
    office_shift_id: null,
    department_id: null,
    college_id: null,
    password: '',
  }
}

const openImportUserDatModal = () => {
  isImportUserDatModal.value = true
}

const openImportBiometricTemplateDatModal = () => {
  isImportBiometricTemplateDatModal.value = true
}

const handleUserDatImported = async (result) => {
  await userStore.loadUsers()
  isImportUserDatModal.value = false

  const summary = result?.summary || {}
  await Swal.fire({
    icon: 'success',
    title: 'Import Completed',
    html: `<div class="space-y-1 text-left text-sm text-slate-600">
      <p>Processed: <strong>${summary.processed ?? 0}</strong></p>
      <p>Created users: <strong>${summary.created_users ?? 0}</strong></p>
      <p>Updated users: <strong>${summary.updated_users ?? 0}</strong></p>
      <p>Created userinfo: <strong>${summary.created_userinfo ?? 0}</strong></p>
      <p>Updated userinfo: <strong>${summary.updated_userinfo ?? 0}</strong></p>
      <p>Skipped existing: <strong>${summary.skipped_existing ?? 0}</strong></p>
    </div>`,
    confirmButtonText: 'OK',
  })
}

const handleBiometricTemplateDatImported = async (result) => {
  isImportBiometricTemplateDatModal.value = false

  const summary = result?.summary || {}
  await Swal.fire({
    icon: 'success',
    title: 'Template Import Completed',
    html: `<div class="space-y-1 text-left text-sm text-slate-600">
      <p>Processed: <strong>${summary.processed ?? 0}</strong></p>
      <p>Created templates: <strong>${summary.created_templates ?? 0}</strong></p>
      <p>Updated templates: <strong>${summary.updated_templates ?? 0}</strong></p>
      <p>Skipped existing: <strong>${summary.skipped_existing ?? 0}</strong></p>
      <p>Skipped missing user: <strong>${summary.skipped_missing_user ?? 0}</strong></p>
    </div>`,
    confirmButtonText: 'OK',
  })
}

const editUser = (event) => {

  isUserAddModal.value = true;
  isEditUser.value = true;
  user.value = {
    id: event.id,
    name: event.name,
    email: event.email,
    role: event.role,
    status: Number(event.status),
    office_shift_id: event.office_shift_id ?? null,
    department_id: event.department_id ?? null,
    college_id: event.college_id ?? null,
    password: '',
    display_name: event.profile?.display_name || '',
    first_name: event.profile?.first_name || '',
    middle_name: event.profile?.middle_name || '',
    last_name: event.profile?.last_name || '',
    name_extension: event.profile?.name_extension || '',
    dob: event.profile?.dob || '',
    gender: event.profile?.gender || '',
    image: event.profile?.image || '',
    thumbnail: event.profile?.thumbnail || '',
    contact_type: event.primary_contact?.type || '',
    contact_value: event.primary_contact?.value || '',
    contacts: event.contacts?.length ? JSON.parse(JSON.stringify(event.contacts)) : [{ id: null, type: 'mobile', value: '', is_primary: true }],
    address_label: event.primary_address?.label || '',
    address1: event.primary_address?.address1 || '',
    address2: event.primary_address?.address2 || '',
    barangay: event.primary_address?.barangay || '',
    municipality: event.primary_address?.municipality || '',
    province: event.primary_address?.province || '',
    zipcode: event.primary_address?.zipcode || '',
    addresses: event.addresses?.length ? JSON.parse(JSON.stringify(event.addresses)) : [{ id: null, label: 'home', address1: '', address2: '', barangay: '', municipality: '', province: '', zipcode: '', is_primary: true }],
  };
}

const viewUser = (event) => {
  router.push({ name: 'UserView', params: { id: event.id } });
}

const deleteUserPop = (event) => {
  isDeleteModal.value = true;
  user.value = event;
}

const deleteUser = async (event) => {
  isDeleteModal.value = false;
  isLoading.value = true;
  text.value = 'Deleting User..';
  var result = await userStore.deleteUser(event);
  if (result.success) {
      toastResult('Remove Successfully');
    } else {
      toastResult('Unable to delete user', 'error');
  }

  isLoading.value = false;
}

const updateOfficeShift = async (payload) => {
  const result = await userStore.updateUserOfficeShift(payload);
  if (result.success) {
    toastResult('Office shift updated', 'success');
  } else {
    toastResult('Unable to update office shift', 'error');
  }
}

const updateUserAffiliation = async (payload) => {
  const result = await userStore.updateUserAffiliation(payload);
  if (result.success) {
    toastResult('Department/College updated', 'success');
  } else {
    toastResult('Unable to update department/college', 'error');
  }
}

const ensureMachinesLoaded = async () => {
  if (machines.value.length) {
    return true;
  }

  const result = await machineStore.loadMachines();
  if (result?.success) {
    return true;
  }

  await Swal.fire({
    icon: 'error',
    title: 'Unable to Load Machines',
    text: result?.data?.response?.data?.message || 'Please check machine setup and try again.',
    confirmButtonText: 'OK',
  })

  return false;
}

const buildMachineLabel = (machine) => {
  return `${machine.MachineAlias || 'Machine'}${machine.IP ? ` (${machine.IP})` : ''}`
}

const LAST_UPLOAD_MACHINES_KEY = 'user-machine-upload-selection'

const getRememberedUploadMachineIds = () => {
  try {
    const raw = localStorage.getItem(LAST_UPLOAD_MACHINES_KEY)
    if (!raw) {
      return []
    }

    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed.map((value) => Number(value)).filter((value) => !Number.isNaN(value)) : []
  } catch {
    return []
  }
}

const rememberUploadMachineIds = (machineIds) => {
  try {
    localStorage.setItem(LAST_UPLOAD_MACHINES_KEY, JSON.stringify(machineIds))
  } catch {
    // best-effort only
  }
}

const chooseMachineAction = async (selectedUser) => {
  const actions = [
    {
      value: 'upload',
      title: 'Upload User + Template',
      description: 'Send the user profile and saved biometric templates to one or more machines.',
      badge: 'Sync',
    },
    {
      value: 'fingerprint',
      title: 'Register Fingerprint',
      description: 'Start fingerprint enrollment on a selected biometric machine.',
      badge: 'Enroll',
    },
    {
      value: 'face',
      title: 'Register Face',
      description: 'Start face registration and wait for the saved face template.',
      badge: 'Capture',
    },
  ]

  const actionHtml = actions.map((action, index) => `
    <label class="machine-action-card ${index === 0 ? 'is-selected' : ''}" data-machine-action-card>
      <input type="radio" name="machine-action" value="${action.value}" ${index === 0 ? 'checked' : ''} />
      <span class="machine-action-mark"></span>
      <span class="machine-action-copy">
        <span class="machine-action-title">${action.title}</span>
        <span class="machine-action-description">${action.description}</span>
      </span>
      <span class="machine-action-badge">${action.badge}</span>
    </label>
  `).join('')

  return Swal.fire({
    title: 'Choose Machine Action',
    html: `
      <div class="machine-action-dialog">
        <p class="machine-action-subtitle">Select what you want to do for <strong>${selectedUser.name}</strong>.</p>
        <div class="machine-action-list">${actionHtml}</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Continue',
    cancelButtonText: 'Cancel',
    focusConfirm: false,
    customClass: {
      popup: 'machine-action-popup',
      title: 'machine-action-heading',
      confirmButton: 'machine-action-confirm',
      cancelButton: 'machine-action-cancel',
      actions: 'machine-action-buttons',
    },
    didOpen: () => {
      const cards = Array.from(document.querySelectorAll('[data-machine-action-card]'))
      cards.forEach((card) => {
        card.addEventListener('click', () => {
          cards.forEach((item) => item.classList.remove('is-selected'))
          card.classList.add('is-selected')
        })
      })
    },
    preConfirm: () => {
      const value = document.querySelector('input[name="machine-action"]:checked')?.value
      if (!value) {
        Swal.showValidationMessage('Select a machine action.')
        return false
      }

      return value
    },
  })
}

const chooseUploadMachines = async (selectedUser, availableMachines) => {
  const rememberedMachineIds = getRememberedUploadMachineIds()
  const machineHtml = availableMachines.map((machine) => {
    const inputId = `machine-upload-${machine.ID}`
    const isChecked = rememberedMachineIds.includes(Number(machine.ID))
    const checked = isChecked ? 'checked' : ''
    const meta = [
      machine.IP ? `IP ${machine.IP}` : null,
      machine.Port ? `Port ${machine.Port}` : null,
      machine.SerialNumber ? `SN ${machine.SerialNumber}` : null,
    ].filter(Boolean).join(' · ')

    return `
      <label for="${inputId}" class="machine-action-card ${isChecked ? 'is-selected' : ''}" data-upload-machine-card>
        <input id="${inputId}" type="checkbox" value="${machine.ID}" ${checked} />
        <span class="machine-action-checkmark"></span>
        <span class="machine-action-copy">
          <span class="machine-action-title">${buildMachineLabel(machine)}</span>
          <span class="machine-action-description">${meta || 'Upload this user and saved template rows.'}</span>
        </span>
        <span class="machine-action-badge">Upload</span>
      </label>
    `
  }).join('')

  return Swal.fire({
    title: 'Select Upload Machines',
    html: `
      <div class="machine-action-dialog">
        <p class="machine-action-subtitle">Choose one or more machines for <strong>${selectedUser.name}</strong>.</p>
        <label for="upload-machine-select-all" class="machine-action-select-all">
          <input id="upload-machine-select-all" type="checkbox" />
          <span class="machine-action-checkmark"></span>
          <span class="machine-action-select-all-copy">
            <span>Select All</span>
            <small>Toggle every available upload target</small>
          </span>
        </label>
        <div id="upload-machine-list" class="machine-action-list machine-action-scroll">${machineHtml}</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Upload Selected Machines',
    cancelButtonText: 'Cancel',
    focusConfirm: false,
    customClass: {
      popup: 'machine-action-popup',
      title: 'machine-action-heading',
      confirmButton: 'machine-action-confirm',
      cancelButton: 'machine-action-cancel',
      actions: 'machine-action-buttons',
    },
    didOpen: () => {
      const selectAll = document.getElementById('upload-machine-select-all')
      const checkboxes = Array.from(document.querySelectorAll('#upload-machine-list input[type="checkbox"]'))
      const cards = Array.from(document.querySelectorAll('[data-upload-machine-card]'))

      const syncCards = () => {
        cards.forEach((card) => {
          const input = card.querySelector('input[type="checkbox"]')
          card.classList.toggle('is-selected', Boolean(input?.checked))
        })
      }

      const syncSelectAll = () => {
        const checkedCount = checkboxes.filter((input) => input.checked).length
        if (selectAll) {
          selectAll.checked = checkedCount > 0 && checkedCount === checkboxes.length
          selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length
          selectAll.closest('.machine-action-select-all')?.classList.toggle('is-selected', checkedCount > 0)
        }
        syncCards()
      }

      if (selectAll) {
        selectAll.addEventListener('change', (event) => {
          const checked = Boolean(event.target?.checked)
          checkboxes.forEach((input) => {
            input.checked = checked
          })
          syncSelectAll()
        })
      }

      checkboxes.forEach((input) => {
        input.addEventListener('change', syncSelectAll)
      })

      syncSelectAll()
    },
    preConfirm: () => {
      const selectedMachineIds = Array.from(document.querySelectorAll('#upload-machine-list input[type="checkbox"]:checked'))
        .map((input) => Number(input.value))
        .filter((value) => !Number.isNaN(value))

      if (!selectedMachineIds.length) {
        Swal.showValidationMessage('Select at least one machine.')
        return false
      }

      rememberUploadMachineIds(selectedMachineIds)

      return selectedMachineIds
    },
  })
}

const chooseRegisterMachine = async (selectedUser, availableMachines, registrationType = 'fingerprint') => {
  const registrationLabel = registrationType === 'face' ? 'face registration' : 'fingerprint registration'
  const machineHtml = availableMachines.map((machine, index) => {
    const label = buildMachineLabel(machine)
    const isFirst = index === 0
    const meta = [
      machine.IP ? `IP ${machine.IP}` : null,
      machine.Port ? `Port ${machine.Port}` : null,
      machine.SerialNumber ? `SN ${machine.SerialNumber}` : null,
    ].filter(Boolean).join(' · ')

    return `
      <label class="machine-action-card ${isFirst ? 'is-selected' : ''}" data-machine-card>
        <input type="radio" name="registration-machine" value="${machine.ID}" ${isFirst ? 'checked' : ''} />
        <span class="machine-action-mark"></span>
        <span class="machine-action-copy">
          <span class="machine-action-title">${label}</span>
          <span class="machine-action-description">${meta || 'Ready for registration'}</span>
        </span>
        <span class="machine-action-badge">${registrationType === 'face' ? 'Face' : 'Finger'}</span>
      </label>
    `
  }).join('')

  return Swal.fire({
    title: 'Select Registration Machine',
    html: `
      <div class="machine-action-dialog">
        <p class="machine-action-subtitle">Choose one biometric device for ${registrationLabel} of <strong>${selectedUser.name}</strong>.</p>
        <div class="machine-action-list">${machineHtml}</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Continue',
    cancelButtonText: 'Cancel',
    focusConfirm: false,
    customClass: {
      popup: 'machine-action-popup',
      title: 'machine-action-heading',
      confirmButton: 'machine-action-confirm',
      cancelButton: 'machine-action-cancel',
      actions: 'machine-action-buttons',
    },
    didOpen: () => {
      const cards = Array.from(document.querySelectorAll('[data-machine-card]'))
      cards.forEach((card) => {
        card.addEventListener('click', () => {
          cards.forEach((item) => item.classList.remove('is-selected'))
          card.classList.add('is-selected')
        })
      })
    },
    preConfirm: () => {
      const value = document.querySelector('input[name="registration-machine"]:checked')?.value
      if (!value) {
        Swal.showValidationMessage('Target machine is required.')
        return false
      }

      return Number(value)
    },
  })
}

const uploadUserToMachines = async (selectedUser, selectedMachineIds, availableMachines) => {
  const uploadResults = []

  Swal.fire({
    title: 'Uploading User',
    html: `<p class="text-sm text-gray-600">Uploading <strong>${selectedUser.name}</strong> to ${selectedMachineIds.length} machine(s)...</p>`,
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading()
    },
  })

  for (const machineId of selectedMachineIds) {
    const response = await machineStore.pushUser({
      user_id: selectedUser.id,
      machine_id: machineId,
      include_templates: true,
      prepare_registration: false,
    })

    const machine = availableMachines.find((item) => item.ID === machineId)

    uploadResults.push({
      machineId,
      machineName: machine ? buildMachineLabel(machine) : `Machine ${machineId}`,
      success: response.success,
      payload: response.success ? (response.data || {}) : null,
      error: response.success ? null : (response?.data?.response?.data?.message || 'Unable to complete the machine action.'),
    })
  }

  Swal.close()

  const successResults = uploadResults.filter((item) => item.success)
  const failedResults = uploadResults.filter((item) => !item.success)

  const successHtml = successResults.length
    ? successResults.map((item) => {
        const uploadedTemplates = item.payload?.templates_uploaded ?? 0
        const copiedTemplates = item.payload?.templates_copied ?? 0
        const attemptedTemplates = item.payload?.templates_upload_attempted ?? 0
        return `
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-emerald-900">${item.machineName}</p>
                <p class="text-xs text-emerald-700">User info uploaded successfully.</p>
              </div>
              <span class="rounded-full bg-emerald-600 px-2 py-1 text-xs font-bold text-white">OK</span>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
              <div class="rounded-md bg-white px-2 py-2">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">Written</p>
                <p class="text-lg font-extrabold text-emerald-700">${uploadedTemplates}</p>
              </div>
              <div class="rounded-md bg-white px-2 py-2">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">Attempted</p>
                <p class="text-lg font-extrabold text-slate-700">${attemptedTemplates}</p>
              </div>
              <div class="rounded-md bg-white px-2 py-2">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">Copied</p>
                <p class="text-lg font-extrabold text-slate-700">${copiedTemplates}</p>
              </div>
            </div>
          </div>
        `
      }).join('')
    : ''

  const failedHtml = failedResults.length
    ? failedResults.map((item) => `
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-3">
          <p class="font-semibold text-red-900">${item.machineName}</p>
          <p class="mt-1 text-xs text-red-700">${item.error}</p>
        </div>
      `).join('')
    : ''

  await Swal.fire({
    icon: failedResults.length ? (successResults.length ? 'warning' : 'error') : 'success',
    title: failedResults.length ? 'Upload Finished With Issues' : 'User Uploaded',
    html: `
      <div class="space-y-3 text-left text-sm text-gray-600">
          <p><strong>${selectedUser.name}</strong> ${successResults.length ? 'uploaded to ' + successResults.length + ' machine(s).' : 'was not uploaded to any machine.'}</p>
        ${successResults.length ? `<div><p class="font-semibold text-emerald-700">Successful uploads</p><div class="mt-2 space-y-2">${successHtml}</div></div>` : ''}
        ${failedResults.length ? `<div><p class="font-semibold text-red-700">Failed uploads</p><div class="mt-2 space-y-2">${failedHtml}</div></div>` : ''}
      </div>
    `,
    confirmButtonText: 'OK',
  })
}

const openMachineAction = async (selectedUser) => {
  const hasMachines = await ensureMachinesLoaded();
  if (!hasMachines) {
    return;
  }

  const availableMachines = machines.value.filter((machine) => {
    return Boolean(machine?.ID) && machine?.Enabled !== false;
  })

  if (!availableMachines.length) {
    await Swal.fire({
      icon: 'warning',
      title: 'No Available Machines',
      text: 'Add and enable at least one biometric machine first.',
      confirmButtonText: 'OK',
    })
    return;
  }

  const actionResult = await chooseMachineAction(selectedUser)

  if (actionResult.isDismissed) {
    return;
  }

  const selectedAction = actionResult.value

  // Register Fingerprint — open the finger-selection modal instead of a direct API call
  if (selectedAction === 'fingerprint') {
    const machineResult = await chooseRegisterMachine(selectedUser, availableMachines, 'fingerprint')

    if (machineResult.isDismissed) {
      return
    }

    const machineId = Number(machineResult.value)
    if (Number.isNaN(machineId)) {
      return
    }

    const machine = machines.value.find(m => m.ID === machineId)
    enrollPollToken.value += 1
    enrollStatusText.value = ''
    enrollCompletedFingers.value = []
    enrollActiveFinger.value = null
    enrollLastCompletedFinger.value = null
    enrollModal.value = {
      open:        true,
      user:        selectedUser,
      machineId:   machineId,
      machineName: machine?.MachineAlias || 'Selected Machine',
    }

    const modalToken = enrollPollToken.value
    loadExistingEnrolledFingers({
      userId: selectedUser.id,
      machineId,
      token: modalToken,
    })
    return;
  }

  if (selectedAction === 'face') {
    const machineResult = await chooseRegisterMachine(selectedUser, availableMachines, 'face')

    if (machineResult.isDismissed) {
      return
    }

    const machineId = Number(machineResult.value)
    if (Number.isNaN(machineId)) {
      return
    }

    Swal.fire({
      title: 'Starting Face Registration',
      html: `<p class="text-sm text-gray-600">Triggering face registration for <strong>${selectedUser.name}</strong>...</p>`,
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        Swal.showLoading()
      },
    })

    const response = await machineStore.enrollFace({
      user_id: selectedUser.id,
      machine_id: machineId,
    })

    Swal.close()

    if (!response.success) {
      await Swal.fire({
        icon: 'error',
        title: 'Face Registration Failed',
        text: response?.data?.response?.data?.message || 'Unable to trigger face registration on the selected machine.',
        confirmButtonText: 'OK',
      })
      return
    }

    const payload = response.data || {}

    const captureResult = await Swal.fire({
      icon: 'info',
      title: 'Complete Face Capture',
      html: `<div class="space-y-2 text-left text-sm text-gray-600">
        <p><strong>User:</strong> ${selectedUser.name}</p>
        <p><strong>Machine:</strong> ${payload?.machine?.name || 'Selected Machine'}</p>
        <p>${payload.instructions || 'Follow the machine prompts to complete face capture.'}</p>
        <p>The app will not check the machine until you confirm capture is finished.</p>
      </div>`,
      confirmButtonText: 'I Finished Face Capture',
      cancelButtonText: 'Close',
      showCancelButton: true,
      allowOutsideClick: false,
      allowEscapeKey: false,
    })

    if (captureResult.isDismissed) {
      return
    }

    Swal.fire({
      title: 'Checking Saved Face Template',
      html: `<div class="space-y-2 text-left text-sm text-gray-600">
        <p>Checking whether the face template was saved locally.</p>
        <p>The app will poll lightly and only contact the machine occasionally.</p>
      </div>`,
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        Swal.showLoading()
      },
    })

    const waitResult = await waitForFaceTemplateSaved({
      userId: selectedUser.id,
      machineId,
      timeoutMs: 5000,
      initialDelayMs: 5000,
      pollIntervalMs: 12000,
      remotePullEvery: 2,
    })

    Swal.close()

    if (!waitResult.found) {
      await Swal.fire({
        icon: 'warning',
        title: 'Face Capture Triggered',
        html: `<div class="space-y-2 text-left text-sm text-gray-600">
          <p><strong>User:</strong> ${selectedUser.name}</p>
          <p><strong>Machine:</strong> ${payload?.machine?.name || 'Selected Machine'}</p>
          <p>Face registration was triggered, but the face template is not in local table yet.</p>
          <p>Please complete the capture on the device, then try again if needed.</p>
        </div>`,
        confirmButtonText: 'OK',
      })
      return
    }

    const saved = waitResult.data || {}
    await Swal.fire({
      icon: 'success',
      title: 'Face Registration Success',
      html: `<div class="space-y-2 text-left text-sm text-gray-600">
        <p><strong>User:</strong> ${selectedUser.name}</p>
        <p><strong>Machine:</strong> ${payload?.machine?.name || 'Selected Machine'}</p>
        <p>${saved.message || 'Face template saved in local template table.'}</p>
        <p><strong>Template Slot:</strong> ${saved?.template?.backup_number ?? '-'}</p>
        <p>${payload.instructions || 'Follow the machine prompts to complete face capture.'}</p>
      </div>`,
      confirmButtonText: 'OK',
    })

    return
  }

  const uploadResult = await chooseUploadMachines(selectedUser, availableMachines)

  if (uploadResult.isDismissed) {
    return;
  }

  await uploadUserToMachines(selectedUser, uploadResult.value || [], availableMachines)
}

const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms))

const loadExistingEnrolledFingers = async ({ userId, machineId, token }) => {
  // Local-only checks avoid repeated device reads when opening the modal.
  const supportedFingerIds = [0, 1, 2, 3, 4, 5, 6, 7, 8]

  const checks = await Promise.allSettled(
    supportedFingerIds.map(async (fingerId) => {
      const status = await machineStore.enrollmentTemplateStatus({
        user_id: userId,
        machine_id: machineId,
        finger_id: fingerId,
        local_only: true,
      })

      return { fingerId, found: Boolean(status.success && status?.data?.found) }
    })
  )

  if (token !== enrollPollToken.value || !enrollModal.value.open) {
    return
  }

  const savedFingerIds = checks
    .filter((item) => item.status === 'fulfilled' && item.value.found)
    .map((item) => item.value.fingerId)

  enrollCompletedFingers.value = savedFingerIds

  if (savedFingerIds.length > 0) {
    enrollStatusText.value = `Loaded ${savedFingerIds.length} previously registered finger(s) from template table.`
  } else {
    enrollStatusText.value = 'No saved fingerprint templates found yet for this user in the template table.'
  }
}

const waitForTemplateSaved = async ({ userId, machineId, fingerId, token, signal, timeoutMs = 60000 }) => {
  const startedAt = Date.now()
  let lastPullError = null

  // Give the device time to finish its three-scan workflow and leave capture
  // mode before opening a second connection to download the template.
  await sleep(10000)

  while (Date.now() - startedAt < timeoutMs) {
    if (token !== enrollPollToken.value || !enrollModal.value.open) {
      return { cancelled: true }
    }

    const status = await machineStore.enrollmentTemplateStatus({
      user_id: userId,
      machine_id: machineId,
      finger_id: fingerId,
    }, {
      signal,
      timeout: 12000,
    })

    if (status.success && status?.data?.found) {
      return { found: true, data: status.data }
    }

    if (status.success && status?.data?.pull_error) {
      lastPullError = status.data.pull_error
    }

    // Keep the message from looking frozen if the scan actually failed or
    // was dismissed on the device instead of completing.
    const elapsedMs = Date.now() - startedAt
    if (token === enrollPollToken.value && enrollModal.value.open) {
      enrollStatusText.value = elapsedMs > 20000
        ? 'Still waiting for the template to appear. If the scan failed or was cancelled on the device, press Cancel and try this finger again.'
        : 'Enrollment started. Waiting for template to be saved in local database...'
    }

    await sleep(5000)
  }

  return { found: false, pullError: lastPullError }
}

const waitForFaceTemplateSaved = async ({
  userId,
  machineId,
  timeoutMs = 240000,
  initialDelayMs = 30000,
  pollIntervalMs = 10000,
  remotePullEvery = 3,
}) => {
  const startedAt = Date.now()
  let pollCount = 0

  if (initialDelayMs > 0) {
    await sleep(initialDelayMs)
  }

  while (Date.now() - startedAt < timeoutMs) {
    pollCount += 1
    const shouldPullFromDevice = pollCount % remotePullEvery === 0
    const status = await machineStore.enrollmentFaceStatus({
      user_id: userId,
      machine_id: machineId,
      local_only: !shouldPullFromDevice,
    })

    if (status.success && status?.data?.found) {
      return { found: true, data: status.data }
    }

    await sleep(pollIntervalMs)
  }

  return { found: false }
}

const handleEnrollConfirm = async ({ fingerId, duress }) => {
  const { user, machineId, machineName } = enrollModal.value
  if (!user || !machineId || enrollLoading.value) return

  enrollPollToken.value += 1
  const pollToken = enrollPollToken.value
  enrollRequestToken.value += 1
  const requestToken = enrollRequestToken.value
  enrollAbortController?.abort()
  enrollLoading.value    = true
  enrollActiveFinger.value = fingerId
  enrollStatusText.value = 'Triggering enrollment on the machine...'
  // Clear any stale "Registration Finished" banner from a previous attempt
  // (e.g. re-enrolling the same finger without re-selecting it first).
  enrollLastCompletedFinger.value = null

  enrollmentMayBeActive.value = true
  enrollAbortController = new AbortController()
  enrollmentStartRequest = machineStore.enrollFingerprint({
    user_id:   user.id,
    machine_id: machineId,
    finger_id:  fingerId,
  }, {
    signal: enrollAbortController.signal,
    timeout: 12000,
  })
  const response = await enrollmentStartRequest
  enrollmentStartRequest = null
  enrollAbortController = null
  enrollLoading.value = false

  if (requestToken !== enrollRequestToken.value || !enrollModal.value.open) {
    return
  }

  if (!response.success) {
    enrollmentMayBeActive.value = false
    enrollLoading.value = false
    enrollActiveFinger.value = null
    enrollStatusText.value = 'Failed to start enrollment. You can select another finger and try again.'
    await Swal.fire({
      icon: 'error',
      title: 'Enrollment Failed',
      text: response?.data?.response?.data?.message || 'Unable to trigger enrollment on the device.',
      confirmButtonText: 'OK',
    })
    return;
  }

  const payload = response.data || {}

  enrollStatusText.value = 'Enrollment started. Waiting for template to be saved in local database...'
  enrollAbortController = new AbortController()

  const waitResult = await waitForTemplateSaved({
    userId: user.id,
    machineId,
    fingerId,
    token: pollToken,
    signal: enrollAbortController.signal,
    timeoutMs: 60000,
  })

  if (requestToken !== enrollRequestToken.value || !enrollModal.value.open) return
  enrollAbortController = null
  enrollLoading.value = false
  enrollmentMayBeActive.value = false

  if (waitResult.cancelled) {
    return
  }

  if (waitResult.found) {
    if (!enrollCompletedFingers.value.includes(fingerId)) {
      enrollCompletedFingers.value.push(fingerId)
    }
    enrollLastCompletedFinger.value = fingerId
    enrollActiveFinger.value = null
    enrollStatusText.value = `Enrollment finished for ${payload.finger_label || `Finger ${fingerId}`}. Template saved locally. You can enroll another finger now.`
    return
  }

  enrollActiveFinger.value = null
  enrollStatusText.value = waitResult.pullError
    ? `Enrollment did not complete: the template could not be pulled from the device (${waitResult.pullError}). Check that the scan finished on the device, then try this finger again.`
    : 'Enrollment did not complete: no template was detected. The scan may have failed or been cancelled on the device. You can try this finger again.'
}

const resetEnrollmentSelection = () => {
  enrollmentMayBeActive.value = false
  enrollmentClosing.value = false
  enrollLoading.value = false
  enrollActiveFinger.value = null
  enrollLastCompletedFinger.value = null
  enrollStatusText.value = ''
}

const cancelEnrollment = async () => {
  if (enrollmentClosing.value) return

  if (!enrollmentMayBeActive.value) {
    closeEnrollModal()
    return
  }

  enrollmentClosing.value = true
  enrollPollToken.value += 1
  enrollRequestToken.value += 1
  enrollAbortController?.abort()
  enrollAbortController = null
  enrollLoading.value = false
  enrollActiveFinger.value = null
  enrollStatusText.value = 'Cancelling enrollment on the machine...'

  if (enrollmentStartRequest) {
    await enrollmentStartRequest
    enrollmentStartRequest = null
  }

  const result = await machineStore.cancelEnrollment(
    { machine_id: enrollModal.value.machineId },
    { timeout: 15000 },
  )

  if (!result.success) {
    enrollmentClosing.value = false
    enrollStatusText.value = result?.data?.response?.data?.message || 'Unable to cancel enrollment on the machine.'
    return
  }

  resetEnrollmentSelection()
}

const closeEnrollModal = () => {
  enrollmentMayBeActive.value = false
  enrollPollToken.value += 1
  enrollRequestToken.value += 1
  enrollAbortController?.abort()
  enrollAbortController = null
  enrollmentStartRequest = null
  enrollLoading.value = false
  enrollActiveFinger.value = null
  enrollLastCompletedFinger.value = null
  enrollStatusText.value = ''
  enrollCompletedFingers.value = []
  enrollModal.value.open = false
}
</script>

<template>
  <div class="space-y-2">
    <section class="admin-page-header dark -ml-4 -mt-4 md:-ml-6 md:-mt-6 sticky p-2 top-0 z-20 -mr-4 border pb-4 shadow-sm md:-mr-6">
      <div class="flex flex-col gap-4 min-[1024px]:flex-row min-[1024px]:items-center min-[1024px]:justify-between">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Users</h1>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
            <span><strong class="font-semibold text-emerald-600 dark:text-emerald-400">{{ userStats.active }}</strong> active</span>
            <span><strong class="font-semibold text-slate-700 dark:text-slate-200">{{ userStats.withShift }}</strong> with shift</span>
            <!-- <span><strong class="font-semibold text-slate-700 dark:text-slate-200">{{ userStats.withAffiliation }}</strong> affiliated</span> -->
          </div>
            <!-- <span class="text-sm text-slate-500 dark:text-slate-400">{{ filteredUsers.length }} of {{ userStats.total }} shown</span> -->
          </div>

        </div>

        <div class="flex flex-col gap-3 sm:ml-auto sm:flex-row sm:items-center xl:justify-end">
          <div class="relative min-w-0 flex-1 sm:w-72 sm:flex-none">
          <button class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 pb-0.5">
            <svg class="fill-slate-500 dark:fill-slate-400" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z"
                fill="" />
            </svg>
          </button>
          <input id="search_button" type="text" v-model="search_user" placeholder="Search name, email, or contact"
            class="h-9 w-full rounded border border-white/15 bg-slate-950/30 py-2 pl-11 pr-4 text-sm font-medium text-white shadow-inner outline-none transition placeholder:text-slate-400 focus:border-cyan-300/60 focus:ring-2 focus:ring-cyan-300/30" />
          </div>
          <div class="grid grid-cols-3 gap-2 sm:flex sm:items-center">
            <Button @click="addUser" :className="'h-9 justify-center whitespace-nowrap rounded border border-cyan-300/30 bg-[linear-gradient(135deg,_#0891b2_0%,_#0f766e_100%)] px-3 text-xs font-semibold text-white shadow-sm hover:bg-[linear-gradient(135deg,_#0e7490_0%,_#115e59_100%)] focus:outline-none focus:ring-2 focus:ring-cyan-300/40'" size="sm" variant="primary"
              :startIcon="PlusIcon">User</Button>
            <Button @click="openImportUserDatModal" :className="'h-9 justify-center whitespace-nowrap rounded border border-white/15 bg-white/10 px-3 text-xs font-semibold text-white shadow-sm hover:border-cyan-200/40 hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-cyan-300/40'" size="sm" variant="outline">
              Import Users
            </Button>
            <Button @click="openImportBiometricTemplateDatModal" :className="'h-9 justify-center whitespace-nowrap rounded border border-white/15 bg-white/10 px-3 text-xs font-semibold text-white shadow-sm hover:border-cyan-200/40 hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-cyan-300/40'" size="sm" variant="outline">
              Import Templates
            </Button>
          </div>
        </div>
      </div>
    </section>

    <UserTable
      @viewUser="viewUser"
      @deleteUser="deleteUserPop"
      @editUser="editUser"
      @machineAction="openMachineAction"
      @updateOfficeShift="updateOfficeShift"
      @updateUserAffiliation="updateUserAffiliation"
      :users="filteredUsers"
      :officeShifts="officeShifts"
      :departments="departments"
      :colleges="colleges"
      :web_layout="web_layout"
    />
    <ModalUser :authUser="authStore.user" :isEditUser="isEditUser" :user="user" :users="users" :officeShifts="officeShifts" :departments="departments" :colleges="colleges" v-if="isUserAddModal" @save="saveUser"
      @close="isUserAddModal = false" :edit_type="1"/>
    <ModalDelete head="User" :data="user" :text="user.name" v-if="isDeleteModal" @save="saveUser" @close="isDeleteModal = false"
      @delete="deleteUser" />
    <FingerprintEnrollModal
      v-if="enrollModal.open"
      :user="enrollModal.user"
      :machine-id="enrollModal.machineId"
      :machine-name="enrollModal.machineName"
      :loading="enrollLoading"
      :enrollment-active="enrollmentMayBeActive"
      :status-text="enrollStatusText"
      :completed-finger-ids="enrollCompletedFingers"
      :active-finger-id="enrollActiveFinger"
      :last-completed-finger-id="enrollLastCompletedFinger"
      @close="closeEnrollModal"
      @cancel-enrollment="cancelEnrollment"
      @confirm="handleEnrollConfirm"
    />
    <ImportUserDatModal
      v-if="isImportUserDatModal"
      @close="isImportUserDatModal = false"
      @imported="handleUserDatImported"
    />
    <ImportBiometricTemplateDatModal
      v-if="isImportBiometricTemplateDatModal"
      @close="isImportBiometricTemplateDatModal = false"
      @imported="handleBiometricTemplateDatImported"
    />
  </div>
</template>

<style>
.machine-action-popup {
  border-radius: 0;
  border: 1px solid #cbd5e1;
  padding: 0;
  overflow: hidden;
}

.machine-action-heading {
  margin: 0;
  padding: 18px 20px 10px;
  background:
    radial-gradient(circle at top left, rgba(14, 165, 233, 0.18), transparent 30%),
    linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f766e 100%);
  color: #fff;
  font-size: 18px;
  font-weight: 700;
}

.machine-action-dialog {
  padding: 14px 18px 0;
  text-align: left;
}

.machine-action-subtitle {
  margin: 0 0 12px;
  color: #475569;
  font-size: 13px;
}

.machine-action-list {
  display: grid;
  gap: 8px;
}

.machine-action-card {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
  min-height: 74px;
  border: 1px solid #cbd5e1;
  border-radius: 0;
  background: #fff;
  padding: 11px 12px;
  cursor: pointer;
  transition: border-color 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.machine-action-card:hover,
.machine-action-card.is-selected {
  border-color: #0891b2;
  background: #ecfeff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.machine-action-card input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.machine-action-mark {
  width: 14px;
  height: 14px;
  border: 2px solid #94a3b8;
  border-radius: 999px;
  background: #fff;
  box-shadow: inset 0 0 0 3px #fff;
}

.machine-action-card.is-selected .machine-action-mark {
  border-color: #0891b2;
  background: #0891b2;
}

.machine-action-checkmark {
  position: relative;
  width: 15px;
  height: 15px;
  border: 2px solid #94a3b8;
  background: #fff;
}

.machine-action-card.is-selected .machine-action-checkmark,
.machine-action-select-all.is-selected .machine-action-checkmark {
  border-color: #0891b2;
  background: #0891b2;
}

.machine-action-card.is-selected .machine-action-checkmark::after,
.machine-action-select-all.is-selected .machine-action-checkmark::after {
  content: '';
  position: absolute;
  left: 3px;
  top: 0;
  width: 5px;
  height: 9px;
  border: solid #fff;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.machine-action-copy {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.machine-action-title {
  color: #0f172a;
  font-size: 13px;
  font-weight: 700;
}

.machine-action-description {
  color: #64748b;
  font-size: 12px;
  line-height: 1.35;
}

.machine-action-badge {
  border: 1px solid #bae6fd;
  background: #f0f9ff;
  color: #0369a1;
  padding: 3px 7px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
}

.machine-action-select-all {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 10px;
  align-items: center;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  padding: 10px 12px;
  cursor: pointer;
}

.machine-action-select-all:hover,
.machine-action-select-all.is-selected {
  border-color: #0891b2;
  background: #ecfeff;
}

.machine-action-select-all input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.machine-action-select-all-copy {
  display: grid;
  gap: 1px;
  color: #0f172a;
  font-size: 13px;
  font-weight: 700;
}

.machine-action-select-all-copy small {
  color: #64748b;
  font-size: 11px;
  font-weight: 500;
}

.machine-action-scroll {
  max-height: 288px;
  overflow-y: auto;
  padding-right: 4px;
}

.machine-action-buttons {
  border-top: 1px solid #e2e8f0;
  margin: 16px 0 0;
  padding: 12px 18px 16px;
}

.machine-action-confirm,
.machine-action-cancel {
  border-radius: 0 !important;
  height: 34px;
  padding: 0 14px;
  font-size: 12px;
  font-weight: 700;
}

.machine-action-confirm {
  border: 1px solid rgba(103, 232, 249, 0.3) !important;
  background: linear-gradient(135deg, #0891b2 0%, #0f766e 100%) !important;
}

.machine-action-cancel {
  border: 1px solid #cbd5e1 !important;
  background: #fff !important;
  color: #334155 !important;
}
</style>
