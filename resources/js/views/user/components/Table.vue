<template>
  <div class="space-y-4">
    <div class=" rounded-[26px] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
      <!-- <div class="border-b border-slate-200 bg-[linear-gradient(135deg,_rgba(16,185,129,0.08),_rgba(14,165,233,0.07))] px-5 py-4 dark:border-slate-800 dark:bg-[linear-gradient(135deg,_rgba(16,185,129,0.08),_rgba(59,130,246,0.05))]">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">User Records</h3>
          </div>
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex rounded-full bg-slate-900 px-3 py-1 font-semibold text-white dark:bg-slate-100 dark:text-slate-900">{{ users.length }} listed</span>
            <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:ring-emerald-800">{{ virtualUsers.length }} visible on page</span>
          </div>
        </div>
      </div> -->

      <div class="border-b border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/50">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1.2fr)_auto]">
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Role</label>
            <select v-model="filters.role" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
              <option value="">All Roles</option>
              <option v-for="role in roleOptions" :key="`role-filter-${role.value}`" :value="String(role.value)">
                {{ role.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Office Shift</label>
            <select v-model="filters.office_shift_id" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
              <option value="">All Shifts</option>
              <option value="none">No Shift</option>
              <option v-for="shift in officeShifts" :key="`shift-filter-${shift.id}`" :value="String(shift.id)">
                {{ shift.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Department</label>
            <select v-model="filters.department_id" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
              <option value="">All Departments</option>
              <option value="none">No Department</option>
              <option v-for="department in departments" :key="`department-filter-${department.id}`" :value="String(department.id)">
                {{ department.department_name }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">College</label>
            <select v-model="filters.college_id" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
              <option value="">All Colleges</option>
              <option value="none">No College</option>
              <option v-for="college in colleges" :key="`college-filter-${college.id}`" :value="String(college.id)">
                {{ college.college_long || college.college_short || `College #${college.id}` }}
              </option>
            </select>
          </div>
          <div class="flex items-end">
            <button
              type="button"
              @click="resetFilters"
              :disabled="!hasActiveFilters"
              class="h-10 w-full rounded-xl border px-4 text-sm font-semibold transition sm:w-auto"
              :class="hasActiveFilters
                ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-slate-800 dark:bg-slate-900'"
            >
              Reset
            </button>
          </div>
        </div>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
          Showing <span class="font-semibold text-slate-700 dark:text-slate-200">{{ sortedUsers.length }}</span> of <span class="font-semibold text-slate-700 dark:text-slate-200">{{ users.length }}</span> users after filters.
        </p>
      </div>

      <div :style="web_layout.bodyWidth > 928 && web_layout.bodyWidth < 1500 ? { width: (web_layout.bodyWidth-50) + 'px' } : {}" class="hidden max-w-full overflow-x-auto custom-scrollbar md:block">
        <table class="min-w-full border-collapse">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50/90 dark:border-slate-700 dark:bg-slate-900/80">
              <th class="px-4 py-3 text-left">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 dark:text-slate-300">Actions</p>
              </th>
              <th class="px-4 py-3 text-left">
                <button type="button" @click="toggleSort('user')" class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400">
                  User
                  <SortIcon class="h-3.5 w-3.5" :class="sortIconClass('user')" />
                  <span v-if="sortBy === 'user'" class="text-[10px] leading-none">{{ sortDirectionLabel }}</span>
                </button>
              </th>

              <th class="px-4 py-3 text-left">
                <button type="button" @click="toggleSort('role')" class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400">
                  Role
                  <SortIcon class="h-3.5 w-3.5" :class="sortIconClass('role')" />
                  <span v-if="sortBy === 'role'" class="text-[10px] leading-none">{{ sortDirectionLabel }}</span>
                </button>
              </th>
              <th class="px-4 py-3 text-left ">
                <button type="button" @click="toggleSort('office_shift')" class="inline-flex min-w-[140px] items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400">
                  Office Shift
                  <SortIcon class="h-3.5 w-3.5" :class="sortIconClass('office_shift')" />
                  <span v-if="sortBy === 'office_shift'" class="text-[10px] leading-none">{{ sortDirectionLabel }}</span>
                </button>
              </th>
              <th class="px-4 py-3 text-left">
                <button type="button" @click="toggleSort('department')" class="inline-flex min-w-[110px] items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400">
                  Department
                  <SortIcon class="h-3.5 w-3.5" :class="sortIconClass('department')" />
                  <span v-if="sortBy === 'department'" class="text-[10px] leading-none">{{ sortDirectionLabel }}</span>
                </button>
              </th>
              <th class="px-4 py-3 text-left">
                <button type="button" @click="toggleSort('college')" class="inline-flex min-w-[110px] items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400">
                  College
                  <SortIcon class="h-3.5 w-3.5" :class="sortIconClass('college')" />
                  <span v-if="sortBy === 'college'" class="text-[10px] leading-none">{{ sortDirectionLabel }}</span>
                </button>
              </th>
               <th class="px-4 py-3 text-center">
                <button type="button" @click="toggleSort('status')" class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400">
                  Status
                  <SortIcon class="h-3.5 w-3.5" :class="sortIconClass('status')" />
                  <span v-if="sortBy === 'status'" class="text-[10px] leading-none">{{ sortDirectionLabel }}</span>
                </button>
              </th>
              <th class="px-4 py-3 text-left">
                <button type="button" @click="toggleSort('last_login')" class="inline-flex min-w-[120px] items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 transition-colors hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-400">
                  Last Login
                  <SortIcon class="h-3.5 w-3.5" :class="sortIconClass('last_login')" />
                  <span v-if="sortBy === 'last_login'" class="text-[10px] leading-none">{{ sortDirectionLabel }}</span>
                </button>
              </th>

               <!-- <th class="px-4 py-3 text-left">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 dark:text-slate-300">Contact</p>
              </th> -->
              <!-- <th class="px-4 py-3 text-left">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-600 dark:text-slate-300">Address</p>
              </th> -->

            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <tr v-for="(_user, index) in virtualUsers" :key="index"
              class="odd:bg-white even:bg-slate-50/60 transition-colors duration-150 hover:bg-emerald-50/50 dark:odd:bg-transparent dark:even:bg-slate-900/30 dark:hover:bg-slate-800/70">
            <template v-if="authUser.id != _user.id">
               <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    @click="$emit('viewUser', _user)"
                    title="View"
                    class="rounded-xl border border-slate-300 p-2 text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <!-- <button
                    @click="$emit('machineAction', _user)"
                    title="Machine Upload"
                    class="rounded-xl border border-emerald-300 p-2 text-emerald-600 transition-colors hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-950"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m3 6V7m3 10v-4m5 8H4a2 2 0 01-2-2V7a2 2 0 012-2h16a2 2 0 012 2v12a2 2 0 01-2 2z" />
                    </svg>
                  </button> -->
                  <div class="relative" v-click-outside="closeActionMenu">
                    <button
                      @click.stop="toggleActionMenu(_user.id)"
                      title="More Actions"
                      class="rounded-xl border border-slate-300 p-2 text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <circle cx="12" cy="5" r="2" />
                        <circle cx="12" cy="12" r="2" />
                        <circle cx="12" cy="19" r="2" />
                      </svg>
                    </button>

                    <div
                      v-if="isActionMenuOpen(_user.id)"
                      class="absolute left-0 top-full z-30 mt-2 w-44 rounded-2xl border border-slate-200 bg-white p-2 text-left shadow-xl dark:border-slate-700 dark:bg-slate-900"
                    >
                    <button
                         @click="$emit('machineAction', _user)"
                    title="Machine Upload"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-xl text-sm px-3 py-2 text-emerald-600 transition-colors hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-950"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m3 6V7m3 10v-4m5 8H4a2 2 0 01-2-2V7a2 2 0 012-2h16a2 2 0 012 2v12a2 2 0 01-2 2z" />
                    </svg>
                        Machine Upload
                      </button>

                      <button
                        @click="handleActionMenu('editUser', _user)"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-blue-600 transition-colors hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50"
                      >
                        <PencilIcon class="h-4 w-4" />
                        Edit User
                      </button>
                      <button
                        @click="handleActionMenu('deleteUser', _user)"
                        type="button"
                        class="mt-1 flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/50"
                      >
                        <TrashIcon class="h-4 w-4" />
                        Delete User
                      </button>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 h-14">
                <div class="flex min-w-[180px] items-center gap-3">
                  <div class="shrink-0">
                    <img class="h-10 w-10 rounded-2xl object-cover ring-2 ring-slate-200 dark:ring-slate-700"
                      :src="_user.thumbnail ? _user.thumbnail : '/images/extras/add-image.png'"
                      :alt="$capitalizeWords(displayName(_user))" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <span @click="$emit('viewUser', _user)" class="block cursor-pointer text-sm font-semibold text-slate-900 hover:text-emerald-600 dark:text-slate-100 dark:hover:text-emerald-400">
                      {{ $capitalizeWords(displayName(_user)) }}
                    </span>
                    <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                      {{ _user.email }}
                    </span>
                    <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                      ID {{ _user.id }}
                    </span>
                  </div>
                </div>
              </td>

              <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                  {{ roleDescription(_user.role) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <select
                  :value="_user.office_shift_id ?? ''"
                  @change="onOfficeShiftChange($event, _user)"
                  class="h-9 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                >
                  <option value="">No Shift</option>
                  <option v-for="shift in officeShifts" :key="`shift-${shift.id}`" :value="String(shift.id)">
                    {{ shift.name }}
                  </option>
                </select>
              </td>
              <td class="px-4 py-3">
                <select
                  :value="_user.department_id ?? ''"
                  @change="onDepartmentChange($event, _user)"
                  class="h-9 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                >
                  <option value="">No Department</option>
                  <option v-for="department in departments" :key="`department-${department.id}`" :value="String(department.id)">
                    {{ department.department_name }}
                  </option>
                </select>
              </td>
              <td class="px-4 py-3">
                <select
                  :value="_user.college_id ?? ''"
                  @change="onCollegeChange($event, _user)"
                  class="h-9 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                >
                  <option value="">No College</option>
                  <option v-for="college in colleges" :key="`college-${college.id}`" :value="String(college.id)">
                    {{ college.college_long || college.college_short || `College #${college.id}` }}
                  </option>
                </select>
              </td>

              <td class="px-4 py-3 text-center">
                <Badge :color="_user.status ? 'success' : 'error'">
                  {{ _user.status ? 'Active' : 'Inactive' }}
                </Badge>
              </td>
              <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                {{ formatDateTime(_user.last_login) }}
              </td>
              <!-- <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                <div class="space-y-1">
                  <p class="font-medium text-slate-800 dark:text-slate-100">{{ _user.primary_contact?.value || '-' }}</p>
                  <p class="text-xs text-slate-500 dark:text-slate-400">{{ _user.primary_contact?.type || '' }}</p>
                </div>
              </td> -->
              <!-- <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                <div class="max-w-[240px]">
                  <p class="line-clamp-2">{{ addressSummary(_user.primary_address) }}</p>
                </div>
              </td> -->

            </template>
          </tr>
        </tbody>
        </table>
      </div>
      <div class="space-y-3 bg-slate-50/50 p-4 dark:bg-slate-900/60 md:hidden">
        <template v-for="(_user, index) in virtualUsers" :key="`mobile-${index}`">
          <div v-if="authUser.id != _user.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800/90">
            <div class="mb-3 flex items-start gap-3">
              <img class="h-12 w-12 rounded-2xl object-cover ring-2 ring-slate-200 dark:ring-slate-600"
                :src="_user.thumbnail ? _user.thumbnail : '/images/extras/add-image.png'"
                :alt="$capitalizeWords(displayName(_user))" />
              <div class="flex-1 min-w-0">
                <p @click="$emit('viewUser', _user)" class="cursor-pointer text-sm font-bold text-slate-900 hover:text-emerald-600 dark:text-slate-100 dark:hover:text-emerald-400">
                  {{ $capitalizeWords(displayName(_user)) }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ _user.email }}</p>
                <div class="mt-2 flex items-center gap-2">
                  <Badge :color="_user.status ? 'success' : 'error'">
                    {{ _user.status ? 'Active' : 'Inactive' }}
                  </Badge>
                  <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ roleDescription(_user.role) }}</span>
                </div>
              </div>
            </div>

            <div class="mb-3 grid grid-cols-2 gap-2 border-t border-slate-200 pt-3 text-xs dark:border-slate-700">
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/70">
                <span class="block font-semibold text-slate-500 dark:text-slate-400">Contact</span>
                <span class="mt-1 block text-slate-700 dark:text-slate-200">{{ _user.primary_contact?.value || '-' }}</span>
              </div>
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/70">
                <span class="block font-semibold text-slate-500 dark:text-slate-400">Last Login</span>
                <span class="mt-1 block text-slate-700 dark:text-slate-200">{{ formatDateTime(_user.last_login) }}</span>
              </div>
              <div class="col-span-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-900/70">
                <span class="block font-semibold text-slate-500 dark:text-slate-400">Address</span>
                <span class="mt-1 block text-slate-700 dark:text-slate-200">{{ addressSummary(_user.primary_address) }}</span>
              </div>
              <div class="col-span-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-900/70">
                <span class="block font-semibold text-slate-500 dark:text-slate-400">Department</span>
                <span class="mt-1 block text-slate-700 dark:text-slate-200">{{ _user.department_ref?.department_name || _user.department || '-' }}</span>
              </div>
              <div>
                <span class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">Department</span>
                <select
                  :value="_user.department_id ?? ''"
                  @change="onDepartmentChange($event, _user)"
                  class="h-9 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                >
                  <option value="">No Department</option>
                  <option v-for="department in departments" :key="`mobile-department-${department.id}`" :value="String(department.id)">
                    {{ department.department_name }}
                  </option>
                </select>
              </div>
              <div>
                <span class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">College</span>
                <select
                  :value="_user.college_id ?? ''"
                  @change="onCollegeChange($event, _user)"
                  class="h-9 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                >
                  <option value="">No College</option>
                  <option v-for="college in colleges" :key="`mobile-college-${college.id}`" :value="String(college.id)">
                    {{ college.college_long || college.college_short || `College #${college.id}` }}
                  </option>
                </select>
              </div>
              <div>
                <span class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">Shift</span>
                <select
                  :value="_user.office_shift_id ?? ''"
                  @change="onOfficeShiftChange($event, _user)"
                  class="h-9 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 text-xs text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                >
                  <option value="">No Shift</option>
                  <option v-for="shift in officeShifts" :key="`mobile-shift-${shift.id}`" :value="String(shift.id)">
                    {{ shift.name }}
                  </option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
              <button @click="$emit('viewUser', _user)" type="button"
                class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">
                View
              </button>
              <button @click="$emit('machineAction', _user)" type="button"
                class="rounded-xl border border-emerald-300 px-3 py-2 text-xs font-medium text-emerald-600 transition-colors hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-950">
                Machine
              </button>
              <div class="relative col-span-2" v-click-outside="closeActionMenu">
                <button
                  @click.stop="toggleActionMenu(_user.id)"
                  type="button"
                  class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <circle cx="12" cy="5" r="2" />
                    <circle cx="12" cy="12" r="2" />
                    <circle cx="12" cy="19" r="2" />
                  </svg>
                  More Actions
                </button>

                <div
                  v-if="isActionMenuOpen(_user.id)"
                  class="mt-2 space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-2 shadow-sm dark:border-slate-700 dark:bg-slate-900/80"
                >
                  <button
                    @click="handleActionMenu('editUser', _user)"
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-blue-300 px-3 py-2 text-xs font-medium text-blue-600 transition-colors hover:bg-blue-50 dark:border-blue-600 dark:text-blue-400 dark:hover:bg-blue-950"
                  >
                    <PencilIcon class="h-4 w-4" />
                    Edit
                  </button>
                  <button
                    @click="handleActionMenu('deleteUser', _user)"
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-300 px-3 py-2 text-xs font-medium text-red-600 transition-colors hover:bg-red-50 dark:border-red-600 dark:text-red-400 dark:hover:bg-red-950"
                  >
                    <TrashIcon class="h-4 w-4" />
                    Delete
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <div class="flex flex-col items-center justify-between gap-4 rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-white/[0.03] sm:flex-row">
      <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Show</label>
        <select v-model="items_per_page" id="items_per_page"
          class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
          <option value="5">5</option>
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="text-sm text-slate-500 dark:text-slate-400">entries</span>
      </div>
      <div class="text-sm text-slate-500 dark:text-slate-400">
        Page <span class="font-semibold text-slate-900 dark:text-white">{{ currentPage }}</span>
      </div>
      <vue-awesome-paginate 
        :total-items="sortedUsers.length"
        :items-per-page="parseInt(items_per_page)" 
        :max-pages-shown="5"
        v-model="currentPage"
        :on-click="() => window.scrollTo({ top: 0, behavior: 'smooth' })"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import Badge from '@/components/ui/Badge.vue'
import { TrashIcon, PencilIcon, SortIcon } from '@/icons'
import vClickOutside from '@/components/common/v-click-outside.vue'

const props = defineProps({
  users: {
    type: Array,
    default: () => [],
  },
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
  web_layout: {
    type: Object,
    default: () => {},
  }
})

import { useAuthStore } from '@/store/AuthStore'
const authStore = useAuthStore()
const { user: authUser } = storeToRefs(authStore)

const items_per_page = ref(5)
const currentPage = ref(1)
const actionMenuUserId = ref(null)
const sortBy = ref('')
const sortDirection = ref('asc')
const filters = ref({
  role: '',
  office_shift_id: '',
  department_id: '',
  college_id: '',
})

const roleMap = {
  0: 'User',
  1: 'Super Admin'
}

const roleDescription = (id) => {
  return roleMap[id] || 'User'
}

const roleOptions = computed(() => {
  return Object.entries(roleMap).map(([value, label]) => ({ value, label }))
})

const hasActiveFilters = computed(() => {
  return Object.values(filters.value).some((value) => String(value || '') !== '')
})

const resetFilters = () => {
  filters.value = {
    role: '',
    office_shift_id: '',
    department_id: '',
    college_id: '',
  }
}

const sortDirectionLabel = computed(() => sortDirection.value === 'asc' ? 'ASC' : 'DESC')

const sortIconClass = (column) => {
  return sortBy.value === column
    ? 'text-emerald-600 dark:text-emerald-400'
    : 'text-slate-400 dark:text-slate-500'
}

const toggleSort = (column) => {
  if (sortBy.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column
    sortDirection.value = 'asc'
  }

  currentPage.value = 1
}

const displayName = (_user) => {
  const customDisplayName = (_user.profile?.display_name || '').trim()
  if (customDisplayName) {
    return customDisplayName
  }

  const first = _user.profile?.first_name || ''
  const last = _user.profile?.last_name || ''
  const full = `${first} ${last}`.trim()
  return full || _user.name || '-'
}

const addressSummary = (address) => {
  if (!address) {
    return '-'
  }

  return [
    address.address1,
    address.address2,
    address.barangay,
    address.municipality,
    address.province,
    address.zipcode,
  ].filter(Boolean).join(', ')
}

const formatDateTime = (value) => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const lookupOfficeShiftName = (id) => {
  const shift = props.officeShifts.find((item) => Number(item.id) === Number(id))
  return shift?.name || ''
}

const lookupDepartmentName = (user) => {
  const department = props.departments.find((item) => Number(item.id) === Number(user.department_id))
  return department?.department_name || user.department_ref?.department_name || user.department || ''
}

const lookupCollegeName = (user) => {
  const college = props.colleges.find((item) => Number(item.id) === Number(user.college_id))
  return college?.college_long || college?.college_short || user.college_ref?.college_long || user.college_ref?.college_short || ''
}

const normalizeSortValue = (user, column) => {
  if (column === 'user') {
    return `${displayName(user)} ${user.email || ''}`.trim().toLocaleLowerCase()
  }

  if (column === 'role') {
    return roleDescription(user.role).toLocaleLowerCase()
  }

  if (column === 'office_shift') {
    return lookupOfficeShiftName(user.office_shift_id).toLocaleLowerCase()
  }

  if (column === 'department') {
    return lookupDepartmentName(user).toLocaleLowerCase()
  }

  if (column === 'college') {
    return lookupCollegeName(user).toLocaleLowerCase()
  }

  if (column === 'status') {
    return user.status ? 1 : 0
  }

  if (column === 'last_login') {
    const time = new Date(user.last_login).getTime()
    return Number.isNaN(time) ? 0 : time
  }

  return ''
}

const matchesNullableFilter = (actualValue, filterValue) => {
  const filter = String(filterValue || '')

  if (!filter) {
    return true
  }

  if (filter === 'none') {
    return actualValue === null || actualValue === undefined || actualValue === ''
  }

  return String(actualValue ?? '') === filter
}

const filteredUsers = computed(() => {
  return [...(props.users || [])].filter((user) => {
    if (filters.value.role !== '' && String(user.role ?? '') !== String(filters.value.role)) {
      return false
    }

    return (
      matchesNullableFilter(user.office_shift_id, filters.value.office_shift_id) &&
      matchesNullableFilter(user.department_id, filters.value.department_id) &&
      matchesNullableFilter(user.college_id, filters.value.college_id)
    )
  })
})

const sortedUsers = computed(() => {
  const data = [...filteredUsers.value]

  if (!sortBy.value) {
    return data
  }

  const direction = sortDirection.value === 'asc' ? 1 : -1

  return data.sort((firstUser, secondUser) => {
    const firstValue = normalizeSortValue(firstUser, sortBy.value)
    const secondValue = normalizeSortValue(secondUser, sortBy.value)

    if (typeof firstValue === 'number' && typeof secondValue === 'number') {
      return (firstValue - secondValue) * direction
    }

    return String(firstValue).localeCompare(String(secondValue), undefined, {
      numeric: true,
      sensitivity: 'base',
    }) * direction
  })
})

const onOfficeShiftChange = (event, user) => {
  const rawValue = event?.target?.value;
  const parsedValue = rawValue === '' ? null : Number(rawValue);

  if (parsedValue === user.office_shift_id) {
    return;
  }

  emit('updateOfficeShift', {
    id: user.id,
    office_shift_id: Number.isNaN(parsedValue) ? null : parsedValue,
  });
}

const onDepartmentChange = (event, user) => {
  const rawValue = event?.target?.value;
  const parsedValue = rawValue === '' ? null : Number(rawValue);

  if (parsedValue === user.department_id) {
    return;
  }

  emit('updateUserAffiliation', {
    id: user.id,
    department_id: Number.isNaN(parsedValue) ? null : parsedValue,
    college_id: user.college_id ?? null,
  });
}

const onCollegeChange = (event, user) => {
  const rawValue = event?.target?.value;
  const parsedValue = rawValue === '' ? null : Number(rawValue);

  if (parsedValue === user.college_id) {
    return;
  }

  emit('updateUserAffiliation', {
    id: user.id,
    department_id: user.department_id ?? null,
    college_id: Number.isNaN(parsedValue) ? null : parsedValue,
  });
}

const toggleActionMenu = (userId) => {
  actionMenuUserId.value = actionMenuUserId.value === userId ? null : userId
}

const closeActionMenu = () => {
  actionMenuUserId.value = null
}

const isActionMenuOpen = (userId) => actionMenuUserId.value === userId

const handleActionMenu = (eventName, user) => {
  closeActionMenu()
  emit(eventName, user)
}

const emit = defineEmits(['viewUser', 'editUser', 'deleteUser', 'machineAction', 'updateOfficeShift', 'updateUserAffiliation'])

watch(filters, () => {
  currentPage.value = 1
}, { deep: true })

watch(() => props.users, () => {
  currentPage.value = 1
})

var virtualUsers = computed(() => {
  var array_length = sortedUsers.value.length
  var pageNo = currentPage.value
  var pageSize = parseInt(items_per_page.value)
  var pageCount = Math.ceil(array_length / pageSize)

  if (pageNo == 0) pageNo = 1
  if (pageNo < 0) pageNo = pageCount
  else if (pageNo > pageCount) pageNo = pageCount
  var startRow = (pageNo - 1) * pageSize + 1
  var endRow = startRow + pageSize - 1

  if (array_length) {
    var data = queryFromVirtualDB(array_length, startRow, endRow)
  } else {
    return []
  }
  if (data.length <= 0) return []
  return data
})

function queryFromVirtualDB(array_length, startRow, endRow) {
  var result = []
  var data = sortedUsers.value
  for (var i = startRow - 1; i < endRow; i++) {
    if (i < array_length) {
      result.push(data[i])
    }
  }
  return result
}
</script>

<style scoped></style>
