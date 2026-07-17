<template>
  <ThemeProvider>
    <SidebarProvider>
      <RouterView />
      <PwaInstallPrompt />
    </SidebarProvider>
  </ThemeProvider>
</template>

<script setup >
import ThemeProvider from '@/layouts/public-account/ThemeProvider.vue'
import SidebarProvider from '@/layouts/public-account/SidebarProvider.vue'
import PwaInstallPrompt from '@/components/common/PwaInstallPrompt.vue'
</script>
