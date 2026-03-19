<template>
  <div class="min-h-screen xl:flex vl-parent ">
    <loading :enforce-focus="true" :active="loadingStore.isLoading" :can-cancel="false"
      :is-full-page="loadingStore.fullPage" :loader="loadingStore.loader" :color="loadingStore.loaderColor">
      <div class=" text-center">
        <span :style="'color:' + loadingStore.loaderColor">{{ loadingStore.text }}</span>
        <div :style="'background-color:' + loadingStore.loaderColor" class="circle"></div>
        <div :style="'background-color:' + loadingStore.loaderColor" class="circle"></div>
        <div :style="'background-color:' + loadingStore.loaderColor" class="circle"></div>
        <div class="shadow"></div>
        <div class="shadow"></div>
        <div class="shadow"></div>
        
      </div>
    </loading>

    <div class="flex-1 transition-all duration-300 ease-in-out">
      <div class="p-4 mx-auto max-w-screen-2xl md:p-6">
        <!-- <slot></slot> -->
        <router-view v-slot="{ Component }">
          <transition name="slide-fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useAdmissionStore } from '@/store/AdmissionStore'
import { useLoadingStore } from '@/store/LoadingStore'

const loadingStore = useLoadingStore();
const admissionStore = useAdmissionStore();

onMounted(() => {
  admissionStore.all();
})
</script>

<style scoped>
.slide-fade-enter-active {
  transition: all .2s ease;
}

.slide-fade-leave-active {
  transition: all .5s cubic-bezier(1.0, 0.5, 0.8, 1.0);
}

.slide-fade-enter,
.slide-fade-leave-to

/* .slide-fade-leave-active below version 2.1.8 */
  {
  transform: translateX(0px);
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
