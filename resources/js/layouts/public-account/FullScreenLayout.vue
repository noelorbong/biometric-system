<template>
  <div class="min-h-screen">
    <main>
      <router-view v-slot="{ Component }">
          <transition name="slide-fade" mode="out-in">
            <!-- <transition enter-from-class="opacity-0" enter-active-class="transition duration-300"> -->
            <component :is="Component" />
          </transition>
        </router-view>
    </main>
  </div>
</template>

<script setup lang="ts"></script>
