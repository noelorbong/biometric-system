<script setup>
import { ref, onUnmounted, onBeforeUnmount, onMounted } from "vue";
import { storeToRefs } from 'pinia'
import moment from 'moment'
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'
import { useSidebar } from '@/composables/useSidebar'
import Backdrop from './Backdrop.vue'
const { isExpanded, isHovered } = useSidebar()

import { useAuthStore } from '@/store/AuthStore'
const authStore = useAuthStore();
const { authenticated, user } = storeToRefs(authStore)


import { useLoadingStore } from '@/store/LoadingStore'
const loadingStore = useLoadingStore();
const { isLoading, text, isGifLoader } = storeToRefs(loadingStore)

import { useUserStore } from '@/store/UserStore'
const userStore = useUserStore();
const { users } = storeToRefs(userStore)


const web_layout = ref({
  screenWidth: 0,
  screenHeight: 0,
  bodyWidth: 0,
  bodyHeight: 0
})

let sideBar = ref(null);
let headerBar = ref(null);
let siderBarWidth = ref(0);
let headerHeight = ref(0);

onMounted(async() => {

  
window.addEventListener("resize", myEventHandler);

web_layout.value.screenWidth = window.innerWidth;
web_layout.value.screenHeight = window.innerHeight;
web_layout.value.bodyWidth = web_layout.value.screenWidth - siderBarWidth.value;
web_layout.value.bodyHeight = web_layout.value.screenHeight - headerHeight.value;
})

let myEventHandler = (e) => {
  web_layout.value.screenWidth = e.target.innerWidth;
  web_layout.value.screenHeight = e.target.innerHeight;
  web_layout.value.bodyWidth = web_layout.value.screenWidth - siderBarWidth.value;
  web_layout.value.bodyHeight = web_layout.value.screenHeight - headerHeight.value;
};

</script>

<template>
  <div class="min-h-screen xl:flex vl-parent ">
    <loading :enforce-focus="true" :active="loadingStore.isLoading" :can-cancel="false"
      :is-full-page="loadingStore.fullPage" :loader="loadingStore.loader" :color="loadingStore.loaderColor">
      <div class="text-center" >
        <span :style="'color:' + loadingStore.loaderColor">{{ text }}</span>
        <div :style="'background-color:' + loadingStore.loaderColor" class="circle"></div>
        <div :style="'background-color:' + loadingStore.loaderColor" class="circle"></div>
        <div :style="'background-color:' + loadingStore.loaderColor" class="circle"></div>
        <div class="shadow"></div>
        <div class="shadow"></div>
        <div class="shadow"></div>
      </div>
    </loading>

    <app-sidebar ref="sideBar" />
    <Backdrop />
    <div class="flex-1 transition-all duration-300 ease-in-out"
      :class="[isExpanded || isHovered ? 'lg:ml-[290px]' : 'lg:ml-[90px]']">
      <app-header ref="headerBar" />
      <div class="p-4 mx-auto max-w-screen-2xl md:p-6">
        <!-- <slot></slot> -->
        <router-view v-slot="{ Component }">
          <transition name="slide-fade" mode="out-in">
            <!-- <transition enter-from-class="opacity-0" enter-active-class="transition duration-300"> -->
            <component :is="Component" :web_layout="web_layout"  />
          </transition>
        </router-view>
      </div>
    </div>
  </div>
</template>



<style scoped>
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}

.slide-fade-leave-active {
  transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateX(20px);
  opacity: 0;
}

.wrapper {
  width: 200px;
  height: 60px;
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
}

.circle {
  width: 20px;
  height: 20px;
  position: absolute;
  border-radius: 50%;
  left: 15%;
  transform-origin: 50%;
  animation: circle 0.5s alternate infinite ease;
}

@keyframes circle {
  0% {
    top: 60px;
    height: 5px;
    border-radius: 50px 50px 25px 25px;
    transform: scaleX(1.7);
  }

  40% {
    height: 20px;
    border-radius: 50%;
    transform: scaleX(1);
  }

  100% {
    top: 0%;
  }
}

.circle:nth-child(2) {
  left: 45%;
  animation-delay: 0.2s;
}

.circle:nth-child(3) {
  left: auto;
  right: 15%;
  animation-delay: 0.3s;
}

.wrapper span {
  position: absolute;
  top: 60px;
  font-size: 15px;
  width: 100%;
  text-align: center;
}
</style>

<style>
.pagination-container {
  display: flex;
  column-gap: 3px;
  margin-top: auto;
  margin-bottom: auto;
}

.paginate-buttons {
  height: 35px;

  width: 35px;

  border-radius: 20px;

  cursor: pointer;

  background-color: rgb(242, 242, 242);

  border: 1px solid rgb(217, 217, 217);

  color: black;
}

.paginate-buttons:hover {
  background-color: #d8d8d8;
}

.active-page {
  background-color: #3498db;

  border: 1px solid #3498db;

  color: white;
}

.active-page:hover {
  background-color: #2988c8;
}
</style>

