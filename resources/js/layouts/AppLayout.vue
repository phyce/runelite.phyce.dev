<script setup lang="ts">
import AppFooter from '@/components/AppFooter.vue';
import AppHeader from '@/components/AppHeader.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-neutral-800 text-gray-200">
        <AppHeader />
        <main class="flex-1 p-4">
            <slot />
        </main>
        <AppFooter />
    </div>
</template>
