<template>
  <section class="pb-0">
    <div class="flex flex-wrap items-center gap-1">
      <RouterLink
        to="/main/settings"
        class="inline-flex h-7 items-center rounded-none border-b-2 px-2 text-[11px] font-semibold transition"
        :class="$route.path === '/main/settings'
          ? 'border-teal-400 bg-teal-400/10 text-teal-200'
          : 'border-transparent text-slate-300 hover:bg-white/5 hover:text-white'"
      >
        General Settings
      </RouterLink>

      <RouterLink
        to="/main/settings/database"
        class="inline-flex h-7 items-center rounded-none border-b-2 px-2 text-[11px] font-semibold transition"
        :class="$route.path === '/main/settings/database'
          ? 'border-teal-400 bg-teal-400/10 text-teal-200'
          : 'border-transparent text-slate-300 hover:bg-white/5 hover:text-white'"
      >
        Database Settings
      </RouterLink>
    </div>
  </section>
</template>
