<template>
  <router-link to="/" class="lg:hidden">
    <img height="40" class="dark:hidden h-[60px]" :src="'/images/logo/banner_white_mode.png'" alt="Logo" />
    <img height="40" class="hidden dark:block h-[60px]" :src="'/images/logo/banner_white_mode.png'" alt="Logo" />
  </router-link>
</template>

<script setup>
import { RouterLink } from 'vue-router'
</script>
