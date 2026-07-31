<script setup lang="ts">
import { provide, ref } from 'vue';
import AppFooter from '@/components/AppFooter.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppNav from '@/components/AppNav.vue';
import { pluginSearchKey } from '@/lib/pluginSearch';

provide(pluginSearchKey, ref(''));
</script>

<template>
    <div class="app-layout">
        <AppHeader />
        <AppNav />
        <main class="app-layout__main">
            <slot />
        </main>
        <AppFooter />
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.app-layout {
    @apply flex min-h-screen flex-col bg-neutral-800 text-gray-200;
}

.app-layout__main {
    @apply flex-1;
}
</style>
