<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { growing, index, popular, top } from '@/actions/App/Http/Controllers/DeveloperController';

const page = usePage();

const tabs = [
    { href: index.url(), label: 'All Developers' },
    { href: top.url(), label: 'Top' },
    { href: popular.url(), label: 'Most Popular' },
    { href: growing.url(), label: 'Fastest Growing' },
];

const currentPath = computed(() => new URL(page.url, 'http://x').pathname);
</script>

<template>
    <nav class="developer-tabs">
        <a
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="developer-tabs__tab"
            :class="currentPath === tab.href ? 'developer-tabs__tab--active' : 'developer-tabs__tab--inactive'"
        >
            {{ tab.label }}
        </a>
    </nav>
</template>

<style scoped>
@reference "tailwindcss";

.developer-tabs {
    @apply -mx-4 flex gap-1.5 overflow-x-auto px-4 sm:mx-0 sm:px-0;
    scrollbar-width: none;
}

.developer-tabs::-webkit-scrollbar {
    display: none;
}

.developer-tabs__tab {
    @apply flex-shrink-0 rounded-full px-3 py-1 text-sm font-medium transition-colors duration-100 sm:px-4;
}

.developer-tabs__tab--active {
    background: #c54704;
    color: #fff;
}

.developer-tabs__tab--inactive {
    @apply bg-neutral-800 text-gray-400;
    border: 1px solid #333;
}

.developer-tabs__tab--inactive:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}
</style>
