<template>
  <ThemeProvider>
    <SidebarProvider>
      <RouterView />
    </SidebarProvider>
  </ThemeProvider>
</template>

<script setup >
import ThemeProvider from '@/layouts/public-account/ThemeProvider.vue'
import SidebarProvider from '@/layouts/public-account/SidebarProvider.vue'
</script>
