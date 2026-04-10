<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-white/[0.03]">
    <div class="flex flex-wrap items-center gap-2">
      <RouterLink
        to="/main/settings"
        class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-semibold transition"
        :class="$route.path === '/main/settings'
          ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300'
          : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
      >
        General Settings
      </RouterLink>

      <RouterLink
        to="/main/settings/database"
        class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-semibold transition"
        :class="$route.path === '/main/settings/database'
          ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
          : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
      >
        Database Settings
      </RouterLink>
    </div>
  </section>
</template>
