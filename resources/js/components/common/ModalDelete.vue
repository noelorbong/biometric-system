<template>
    <div>
        <Modal @close="$emit('close')">
            <template #body>
                <div
                    class="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white mx-2 pt-6 p-4 dark:bg-gray-900 lg:p-6">
                    
                    <button @click="$emit('close')"
                        class="transition-color absolute right-5 top-5 z-999 flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:bg-gray-700 dark:bg-white/[0.05] dark:text-gray-400 dark:hover:bg-white/[0.07] dark:hover:text-gray-300">
                        <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z"
                                fill="" />
                        </svg>
                    </button>
                    <div class="px-2 pr-14 flex">
                        <div
                            class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10">
                            <ExclamationTriangleIcon class="size-6 pl-1 pt-1 text-red-600" aria-hidden="true" />
                        </div>
                        <div class="pl-2">
                            <h4 class="mb-2 flex text-2xl font-semibold text-gray-800 dark:text-white/90">

                                <span class=""> Delete {{head}}</span>
                            </h4>
                            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">
                                Are you sure you want to remove {{ text }} ?
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-2 lg:justify-end">
                        <button @click="$emit('close')" type="button"
                            class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] sm:w-auto">
                            Close
                        </button>
                        <button type="button" @click="$emit('delete', data)"
                            class="flex w-full justify-center rounded-lg bg-red-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-600 sm:w-auto">
                            Delete
                        </button>
                    </div>
                </div>
            </template>
        </Modal>
    </div>
</template>
<script setup>
import { ExclamationTriangleIcon } from '@/icons'
import Modal from './Modal.vue'

const props = defineProps({
    data: Object,
    text:String,
    head:String
})

</script>

<style></style>
