<template>
  <div class=" py-3 border-b border-gray-200 last:border-b-0 dark:border-gray-800">

    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2 w-full" >
        <div class="items-center w-full rounded-full max-w-6">
          <component v-if="!image" :is="etsIcon" />
          <img v-else :src="image" />
        </div>
        <div>
          <p class="font-semibold text-gray-800 text-theme-sm dark:text-white/90">{{ label }}</p>
          <span class="block text-gray-500 text-theme-xs dark:text-gray-400">
            <span class="rounded-full bg-success-50 px-1 py-0.5 text-theme-xs font-medium  text-success-600 dark:bg-success-500/15 dark:text-success-500">{{ value.toLocaleString() }}</span> {{ sub_label }}
          </span>

        </div>
      </div>

      <div class="flex w-full max-w-[140px] items-center gap-3">
        <div class="relative block h-2 w-full max-w-[100px] rounded bg-gray-200 dark:bg-gray-800">
          <div :style="'width:' + parseInt(percentage) + '%'"
            class="absolute left-0 top-0 flex h-full items-center justify-center rounded bg-brand-500 text-xs font-medium text-white">
          </div>
        </div>
        <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ parseInt(percentage)?percentage:'0' }}%</p>
      </div>


    </div>
    <div>
      <span class="pl-9 block text-gray-500 text-theme-xs dark:text-gray-400">
        {{ description }}
      </span>
    </div>
  </div>
</template>
<script setup >
//#region Imports
import { ref, computed, markRaw } from "vue";
import { storeToRefs } from 'pinia'

//#endregion

// interface EcommerceProps {
//   icon?: Object,
//   image?: String,
//   label?: String,
//   sub_label?: String,
//   description?: String,
//   value?: Number,
//   percentage?: Number
// }

// defineProps<EcommerceProps>()

//#region Props
const props = defineProps({
  etsIcon: Object,
  image: String,
  label: String,
  sub_label: String,
  description: String,
  value: Number,
  percentage: String
})
//#endregion

</script>
