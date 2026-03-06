<script setup lang="ts">
import AppFooter from '@/components/AppFooter.vue';
import AppHeader from '@/components/AppHeader.vue';
</script>

<template>
    <div class="app-layout">
        <AppHeader />
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
    @apply flex-1 p-4;
}
</style>
