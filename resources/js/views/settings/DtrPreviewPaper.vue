<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import PrintableAttendance from '@/views/user/components/PrintableAttendance.vue'
const props = defineProps({ form: { type: Object, required: true } })
const viewport = ref(null)
const availableWidth = ref(540)
const now = new Date()
const sampleUser = { name: 'JUAN DELA CRUZ', department: 'Employee department name' }
const settings = computed(() => ({
  biometricDtrTemplate: props.form.biometric_dtr_template,
  biometricDtrTemplate2LogoPosition: props.form.biometric_dtr_template2_logo_position,
  biometricDtrTemplate2LogoLeft: props.form.biometric_dtr_template2_logo_left,
  biometricDtrTemplate2LogoWidth: props.form.biometric_dtr_template2_logo_width,
  biometricDtrTemplate2LogoHeight: props.form.biometric_dtr_template2_logo_height,
  biometricDtrTemplate2LogoRight: props.form.biometric_dtr_template2_logo_right,
  biometricDtrTemplate2LogoTop: props.form.biometric_dtr_template2_logo_top,
  biometricDtrTemplate2LogoBottom: props.form.biometric_dtr_template2_logo_bottom,
  biometricDtrTemplate2FontSize: props.form.biometric_dtr_template2_font_size,
  biometricDtrTemplate2NameFontSize: props.form.biometric_dtr_template2_name_font_size,
  biometricDtrTemplate2CompanyFontSize: props.form.biometric_dtr_template2_company_font_size,
  biometricDtrTemplate2RepublicFontSize: props.form.biometric_dtr_template2_republic_font_size,
  biometricDtrTemplate2DepartmentFontSize: props.form.biometric_dtr_template2_department_font_size,
  biometricDtrTemplate2AddressFontSize: props.form.biometric_dtr_template2_address_font_size,
  biometricDtrTemplate2TitleFontSize: props.form.biometric_dtr_template2_title_font_size,
  biometricDtrTemplate2ApprovedFontSize: props.form.biometric_dtr_template2_approved_font_size,
  biometricDtrTemplate2TimekeeperFontSize: props.form.biometric_dtr_template2_timekeeper_font_size,
  biometricDtrTemplate2EmployeeFontSize: props.form.biometric_dtr_template2_employee_font_size,
  biometricDtrTemplate2MonthFontSize: props.form.biometric_dtr_template2_month_font_size,
  biometricDtrTemplate2TableHeaderFontSize: props.form.biometric_dtr_template2_table_header_font_size,
  biometricDtrTemplate2TableContentFontSize: props.form.biometric_dtr_template2_table_content_font_size,

  biometricDtrHeaderAddress: props.form.biometric_dtr_header_address,
  biometricDtrTimekeeperUseDefault: props.form.biometric_dtr_timekeeper_use_default,
  biometricDtrTimekeeperName: props.form.biometric_dtr_timekeeper_name,
  biometricDtrTimekeeperSignatureEnabled: props.form.biometric_dtr_timekeeper_signature_enabled,
  biometricDtrTimekeeperSignature: props.form.biometric_dtr_timekeeper_signature,
}))
const paperWidth = computed(() => props.form.biometric_dtr_template === 'template2' ? 537 : 312)
const scale = computed(() => Math.min(1, availableWidth.value / paperWidth.value))
let observer
onMounted(() => {
  observer = new ResizeObserver(([entry]) => { availableWidth.value = entry.contentRect.width })
  observer.observe(viewport.value)
})
onBeforeUnmount(() => observer?.disconnect())
</script>
<template>
    <div class="mt-4 max-h-[75vh] overflow-y-auto rounded-md border border-slate-200 bg-slate-200 p-3 dark:border-slate-700 dark:bg-slate-800">
      <div ref="viewport">
        <div class="mx-auto bg-white text-black shadow-sm" :style="{ width: `${paperWidth}px`, zoom: scale }">
          <PrintableAttendance
            :settings-override="settings"
            :user="sampleUser"
            :selected-year="now.getFullYear()"
            :selected-month="now.getMonth() + 1"
            :attendance-records="[]"
            :company-name="form.company_school_name"
            :company-logo="form.company_school_logo"
            :show-logo="form.company_school_logo_print_enabled"
            :show-controls="false"
            :signatory-name="form.biometric_dtr_signatory_use_default ? form.biometric_dtr_signatory_name : ''"
            :signatory-signature="form.biometric_dtr_signatory_signature"
            :signatory-signature-enabled="form.biometric_dtr_signatory_use_default && form.biometric_dtr_signatory_signature_enabled"
          />
        </div>
      </div>
    </div>
</template>
