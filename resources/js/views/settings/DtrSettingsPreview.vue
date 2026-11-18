<script setup>
import { onBeforeUnmount, ref } from 'vue'
import DtrPreviewPaper from './DtrPreviewPaper.vue'

const props = defineProps({ form: { type: Object, required: true } })
const emit = defineEmits(['update-field'])
const customizationOpen = ref(false)
const logoDimensions = ['width', 'height']
const logoDirections = ['left', 'right', 'top', 'bottom']
const fontControls = [{ 'key': 'biometric_dtr_template2_company_font_size', 'label': 'Company / school name', 'default': 8 }, { 'key': 'biometric_dtr_template2_republic_font_size', 'label': 'Republic of the Philippines', 'default': 8 }, { 'key': 'biometric_dtr_template2_department_font_size', 'label': 'Employee department', 'default': 8 }, { 'key': 'biometric_dtr_template2_address_font_size', 'label': 'Office address', 'default': 8 }, { 'key': 'biometric_dtr_template2_title_font_size', 'label': 'DAILY TIME RECORD title', 'default': 13 },  { 'key': 'biometric_dtr_template2_table_header_font_size', 'label': 'Table headers', 'default': 7 }, { 'key': 'biometric_dtr_template2_table_content_font_size', 'label': 'Table content font size', 'default': 7 }, { 'key': 'biometric_dtr_template2_name_font_size', 'label': 'Employee name (top)', 'default': 10 }, { 'key': 'biometric_dtr_template2_month_font_size', 'label': 'For the Month of', 'default': 10 },  { 'key': 'biometric_dtr_template2_approved_font_size', 'label': 'Approved by', 'default': 10 }, { 'key': 'biometric_dtr_template2_timekeeper_font_size', 'label': 'Time Keeper', 'default': 10 }, { 'key': 'biometric_dtr_template2_employee_font_size', 'label': 'Employee name (bottom)', 'default': 10 }]
const resetCustomization = () => {
  emit('update-field', 'biometric_dtr_template2_logo_position', 'left')
  logoDimensions.forEach(dimension => emit('update-field', `biometric_dtr_template2_logo_${dimension}`, 15))
  logoDirections.forEach(direction => emit('update-field', `biometric_dtr_template2_logo_${direction}`, ({ left: 0, right: 4, top: 0, bottom: 3 })[direction]))
  fontControls.forEach(control => emit('update-field', control.key, control.default))
}
const customizationDialog = ref(null)
let previousOverflow
const openCustomization = () => {
  customizationDialog.value.showModal()
  customizationOpen.value = true
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
}
const closeCustomization = () => customizationDialog.value?.close()
const handleDialogClose = () => {
  customizationOpen.value = false
  if (previousOverflow !== undefined) document.body.style.overflow = previousOverflow
  previousOverflow = undefined
}
onBeforeUnmount(handleDialogClose)
</script>

<template>
  <aside
    class="min-w-0 self-start rounded-lg border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:sticky lg:top-32"
    aria-label="Live DTR preview">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-base font-semibold text-slate-900 dark:text-white">DTR preview</h2>
      <div class="flex items-center gap-2">
        <span
          class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Live
          preview</span>
        <button type="button"
          class="rounded-md border border-slate-300 p-2 text-slate-600 hover:bg-white focus:ring-2 focus:ring-sky-500 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
          aria-label="Customize selected DTR template" title="Customize selected template"
          :aria-expanded="customizationOpen" aria-haspopup="dialog" aria-controls="dtr-template-customization"
          @click="openCustomization">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
            aria-hidden="true">
            <path
              d="m9 3-.6 2.3-2 .9-2.1-.7-2 3.5 1.6 1.7v2.6L2.3 15l2 3.5 2.1-.7 2 .9L9 21h4l.6-2.3 2-.9 2.1.7 2-3.5-1.6-1.7v-2.6L19.7 9l-2-3.5-2.1.7-2-.9L13 3Z" />
            <circle cx="11" cy="12" r="3" />
          </svg>
        </button>
      </div>
    </div>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Updates as you edit. Save Settings to apply changes to
      reports.</p>
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ form.biometric_dtr_template === 'template2' ?
      'Template 2 · 2 forms per A4 landscape sheet' : 'Original template · 4 forms per landscape sheet' }} · One form
      shown with sample employee details.</p>
    <DtrPreviewPaper :form="form" />
    <Teleport to="body">
      <dialog ref="customizationDialog" id="dtr-template-customization" aria-labelledby="dtr-customization-title"
        class="dtr-customization-dialog w-[min(1100px,calc(100vw-2rem))] max-w-none max-h-[90vh] rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-white"
        @close="handleDialogClose" @click="($event.target === $event.currentTarget) && closeCustomization()">
        <div class="flex max-h-[90vh] flex-col">
          <header
            class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
              <h2 id="dtr-customization-title" class="text-lg font-semibold">Customize DTR template</h2>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Changes preview immediately. Save Settings on
                the General tab to apply them to reports.</p>
            </div>
            <button type="button" autofocus aria-label="Close template settings"
              class="rounded-md px-3 py-2 text-xl hover:bg-slate-100 dark:hover:bg-slate-800"
              @click="closeCustomization">&times;</button>
          </header>
          <div v-if="customizationOpen"
            class="grid min-h-0 gap-4 overflow-y-auto p-5 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
            <div class="min-w-0">
              <section
                class="mt-3 rounded-md border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ form.biometric_dtr_template ===
                  'template2' ? 'Template 2 settings' : 'Original template settings' }}</h3>
                <template v-if="form.biometric_dtr_template === 'template2'">
                  <p class="mt-1 text-xs text-slate-500">Font sizes are in points (pt). Changes appear in the preview
                    immediately.</p>
                  <h4 class="mt-3 text-xs font-medium dark:text-slate-200">Logo size (mm)</h4>
                  <div class="mt-2 grid grid-cols-2 gap-3">
                    <div v-for="dimension in logoDimensions" :key="dimension">
                      <label :for="`dtr-logo-${dimension}`"
                        class="block text-xs font-medium capitalize dark:text-slate-200">{{ dimension }} (mm)</label>
                      <input :id="`dtr-logo-${dimension}`" type="number" min="1" max="50" step="0.5"
                        :value="form[`biometric_dtr_template2_logo_${dimension}`]"
                        @input="Number.isFinite($event.target.valueAsNumber) && emit('update-field', `biometric_dtr_template2_logo_${dimension}`, Math.max(1, Math.min(50, $event.target.valueAsNumber)))"
                        class="mt-1 w-full rounded border border-slate-300 bg-transparent p-2 text-sm dark:border-slate-600 dark:text-white" />
                    </div>
                  </div>
                  <p class="mt-1 text-xs text-slate-500">The logo keeps its proportions inside this size.</p>
                  <h4 class="mt-3 text-xs font-medium dark:text-slate-200">Logo position offsets (mm)</h4>
                  <p class="mt-1 text-xs text-slate-500">Each value moves the logo in that direction. For example, Right
                    2 and Top 2 moves it 2 mm right and 2 mm up.</p>
                  <div class="mt-2 grid grid-cols-2 gap-3">
                    <div v-for="direction in logoDirections" :key="direction">
                      <label :for="`dtr-logo-${direction}`"
                        class="block text-xs font-medium capitalize dark:text-slate-200">{{ direction }} (mm)</label>
                      <input :id="`dtr-logo-${direction}`" type="number" min="0" max="30" step="0.5"
                        :value="form[`biometric_dtr_template2_logo_${direction}`]"
                        @input="Number.isFinite($event.target.valueAsNumber) && emit('update-field', `biometric_dtr_template2_logo_${direction}`, Math.max(0, Math.min(30, $event.target.valueAsNumber)))"
                        class="mt-1 w-full rounded border border-slate-300 bg-transparent p-2 text-sm dark:border-slate-600 dark:text-white" />
                    </div>
                  </div>
                  <div class="mt-3 grid grid-cols-2 gap-3">
                    <div v-for="control in fontControls" :key="control.key">
                      <label :for="control.key" class="block text-xs font-medium dark:text-slate-200">{{ control.label
                        }} (pt)</label>
                      <input :id="control.key" type="number" min="1" max="18" step="0.5" :value="form[control.key]"
                        @input="Number.isFinite($event.target.valueAsNumber) && emit('update-field', control.key, $event.target.valueAsNumber)"
                        class="mt-1 w-full rounded border border-slate-300 bg-transparent p-2 text-sm dark:border-slate-600 dark:text-white" />
                    </div>
                  </div>
                  <button type="button" class="mt-3 text-xs font-medium text-sky-600 dark:text-sky-400"
                    @click="resetCustomization">Reset Template 2 to defaults</button>
                </template>
                <p v-else class="mt-2 text-xs text-slate-500">Select Template 2 to customize logo position and font
                  sizes.</p>
              </section>

            </div>
            <div class="min-w-0">
              <h3 class="text-sm font-semibold">Live preview</h3>
              <DtrPreviewPaper :form="form" />
            </div>
          </div>
          <footer class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-700">
            <button type="button"
              class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700"
              @click="closeCustomization">Done</button>
          </footer>
        </div>
      </dialog>
    </Teleport>
  </aside>
</template>

<style scoped>
.dtr-customization-dialog {
  margin: auto;
}

.dtr-customization-dialog::backdrop {
  background: rgb(15 23 42 / 0.6);
  backdrop-filter: blur(3px);
}
</style>
