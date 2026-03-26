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

import FingerprintEnrollModal from './components/FingerprintEnrollModal.vue'
import { useAuthStore } from '@/store/AuthStore'
const authStore = useAuthStore();
const router = useRouter();

const loadingStore = useLoadingStore();
const { isLoading, text } = storeToRefs(loadingStore)

const userStore = useUserStore();
const { users, officeShifts, departments, colleges } = storeToRefs(userStore)
const machineStore = useMachineStore();
const { machines } = storeToRefs(machineStore)


const isUserAddModal = ref(false)
const isDeleteModal = ref(false)
const enrollModal    = ref({ open: false, user: null, machineId: null, machineName: '' })
const enrollLoading  = ref(false)
const enrollStatusText = ref('')
const enrollCompletedFingers = ref([])
const enrollActiveFinger = ref(null)
const enrollLastCompletedFinger = ref(null)
const enrollPollToken = ref(0)

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
  return Swal.fire({
    title: 'Choose Machine Action',
    html: `<p class="text-sm text-gray-600">Select what you want to do for <strong>${selectedUser.name}</strong>.</p>`,
    input: 'select',
    inputOptions: {
      upload: 'Upload User + Template',
      fingerprint: 'Register Fingerprint',
      face: 'Register Face',
    },
    inputValue: 'upload',
    inputPlaceholder: 'Choose machine action',
    showCancelButton: true,
    confirmButtonText: 'Continue',
    focusConfirm: false,
    preConfirm: () => {
      const value = Swal.getInput()?.value
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
    const checked = rememberedMachineIds.includes(Number(machine.ID)) ? 'checked' : ''

    return `
      <label for="${inputId}" class="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2 text-left hover:bg-slate-50 cursor-pointer">
        <input id="${inputId}" type="checkbox" value="${machine.ID}" ${checked} class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
        <span>
          <span class="block text-sm font-medium text-slate-800">${buildMachineLabel(machine)}</span>
          <span class="block text-xs text-slate-500">Upload this user's basic info and saved template rows.</span>
        </span>
      </label>
    `
  }).join('')

  return Swal.fire({
    title: 'Select Upload Machines',
    html: `
      <div class="space-y-3 text-left">
        <p class="text-sm text-gray-600">Choose one or more machines for <strong>${selectedUser.name}</strong>.</p>
        <label for="upload-machine-select-all" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 cursor-pointer">
          <input id="upload-machine-select-all" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
          <span class="text-sm font-medium text-slate-700">Select All</span>
        </label>
        <div id="upload-machine-list" class="space-y-2 max-h-72 overflow-y-auto pr-1">${machineHtml}</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Upload Selected Machines',
    focusConfirm: false,
    didOpen: () => {
      const selectAll = document.getElementById('upload-machine-select-all')
      const checkboxes = Array.from(document.querySelectorAll('#upload-machine-list input[type="checkbox"]'))

      const syncSelectAll = () => {
        const checkedCount = checkboxes.filter((input) => input.checked).length
        if (selectAll) {
          selectAll.checked = checkedCount > 0 && checkedCount === checkboxes.length
          selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length
        }
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
  const machineOptions = availableMachines.reduce((carry, machine) => {
    carry[machine.ID] = buildMachineLabel(machine)
    return carry
  }, {})

  return Swal.fire({
    title: 'Select Registration Machine',
    html: `<p class="text-sm text-gray-600">Choose one biometric device for ${registrationLabel} of <strong>${selectedUser.name}</strong>.</p>`,
    input: 'select',
    inputOptions: machineOptions,
    inputPlaceholder: 'Choose target machine',
    showCancelButton: true,
    confirmButtonText: 'Continue',
    focusConfirm: false,
    preConfirm: () => {
      const value = Swal.getInput()?.value
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
        <p><strong>${selectedUser.name}</strong> upload completed.</p>
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

const waitForTemplateSaved = async ({ userId, machineId, fingerId, token, timeoutMs = 120000 }) => {
  const startedAt = Date.now()

  while (Date.now() - startedAt < timeoutMs) {
    if (token !== enrollPollToken.value || !enrollModal.value.open) {
      return { cancelled: true }
    }

    const status = await machineStore.enrollmentTemplateStatus({
      user_id: userId,
      machine_id: machineId,
      finger_id: fingerId,
    })

    if (status.success && status?.data?.found) {
      return { found: true, data: status.data }
    }

    await sleep(2000)
  }

  return { found: false }
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
  enrollLoading.value    = true
  enrollActiveFinger.value = fingerId
  enrollStatusText.value = 'Triggering enrollment on the machine...'

  const response = await machineStore.enrollFingerprint({
    user_id:   user.id,
    machine_id: machineId,
    finger_id:  fingerId,
  })

  enrollLoading.value = false

  if (!response.success) {
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

  const waitResult = await waitForTemplateSaved({
    userId: user.id,
    machineId,
    fingerId,
    token: pollToken,
    timeoutMs: 120000,
  })

  enrollLoading.value = false

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
  enrollStatusText.value = 'Enrollment triggered, but template was not detected in local table yet. You can wait a bit and try this finger again.'
}

const closeEnrollModal = () => {
  enrollPollToken.value += 1
  enrollLoading.value = false
  enrollActiveFinger.value = null
  enrollLastCompletedFinger.value = null
  enrollStatusText.value = ''
  enrollCompletedFingers.value = []
  enrollModal.value.open = false
}
</script>

<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(34,197,94,0.18),_transparent_28%),linear-gradient(135deg,_#111827_0%,_#1f2937_38%,_#0f766e_100%)] p-5 text-white shadow-sm dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top_left,_rgba(74,222,128,0.18),_transparent_28%),linear-gradient(135deg,_rgba(17,24,39,0.96)_0%,_rgba(31,41,55,0.98)_38%,_rgba(15,118,110,0.92)_100%)] lg:p-7">
      <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-200/80">People Directory</p>
          <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white lg:text-4xl">Users</h1>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-200/90">
            Manage user records, update affiliations, and send profiles to biometric devices from one workspace.
          </p>
          <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex rounded-full bg-white/10 px-3 py-1 font-medium text-slate-100 ring-1 ring-inset ring-white/10">
              Managed Users: {{ userStats.total }}
            </span>
            <span class="inline-flex rounded-full bg-emerald-400/15 px-3 py-1 font-medium text-emerald-100 ring-1 ring-inset ring-emerald-300/30">
              Active: {{ userStats.active }}
            </span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:min-w-[460px]">
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Total</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ userStats.total }}</p>
            <p class="mt-1 text-xs text-slate-300">Managed accounts</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Active</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ userStats.active }}</p>
            <p class="mt-1 text-xs text-slate-300">Currently enabled</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">With Shift</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ userStats.withShift }}</p>
            <p class="mt-1 text-xs text-slate-300">Shift assigned</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-300">Affiliated</p>
            <p class="mt-2 text-3xl font-semibold text-white">{{ userStats.withAffiliation }}</p>
            <p class="mt-1 text-xs text-slate-300">Dept or college set</p>
          </div>
        </div>
      </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_280px]">
      <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03] lg:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">User Directory</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Search by name, email, or primary contact number.</p>
          </div>
          <div class="text-sm text-slate-500 dark:text-slate-400">
            Showing <span class="font-semibold text-slate-900 dark:text-white">{{ filteredUsers.length }}</span> of {{ userStats.total }} users
          </div>
        </div>

        <div class="relative mt-4">
          <button class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 pb-0.5">
            <svg class="fill-slate-500 dark:fill-slate-400" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z"
                fill="" />
            </svg>
          </button>
          <input id="search_button" type="text" v-model="search_user" placeholder="Search name, email, or contact"
            class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-12 pr-4 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-emerald-400 focus:bg-white dark:border-slate-700 dark:bg-slate-900/60 dark:text-white/90 dark:placeholder:text-slate-500" />
        </div>
      </div>

      <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03] lg:p-5">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Quick Action</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create a new user record.</p>

        <div class="mt-4 grid gap-3">
          <Button @click="addUser" :className="'h-12 justify-center rounded-2xl whitespace-nowrap text-nowrap'" size="sm" variant="primary"
            :startIcon="PlusIcon">Add User</Button>
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
    />
    <ModalUser :authUser="authStore.user" :isEditUser="isEditUser" :user="user" :officeShifts="officeShifts" :departments="departments" :colleges="colleges" v-if="isUserAddModal" @save="saveUser"
      @close="isUserAddModal = false" :edit_type="1"/>
    <ModalDelete head="User" :data="user" :text="user.name" v-if="isDeleteModal" @save="saveUser" @close="isDeleteModal = false"
      @delete="deleteUser" />
    <FingerprintEnrollModal
      v-if="enrollModal.open"
      :user="enrollModal.user"
      :machine-id="enrollModal.machineId"
      :machine-name="enrollModal.machineName"
      :loading="enrollLoading"
      :status-text="enrollStatusText"
      :completed-finger-ids="enrollCompletedFingers"
      :active-finger-id="enrollActiveFinger"
      :last-completed-finger-id="enrollLastCompletedFinger"
      @close="closeEnrollModal"
      @confirm="handleEnrollConfirm"
    />
  </div>
</template>
