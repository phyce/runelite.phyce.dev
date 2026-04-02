<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const links = [
    { href: '/', label: 'All Plugins', exact: true },
    { href: '/top', label: 'Top', exact: true },
    { href: '/top/absolute', label: 'Most Popular', exact: false },
    { href: '/top/relative', label: 'Fastest Growing', exact: false },
];

const currentPath = computed(() => new URL(page.url, 'http://x').pathname);

function isActive(href: string, exact: boolean): boolean {
    if (exact) {
        return currentPath.value === href;
    }
    return currentPath.value.startsWith(href);
}
</script>

<template>
    <nav class="app-nav">
        <div class="app-nav__inner">
            <a
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="app-nav__link"
                :class="isActive(link.href, link.exact) ? 'app-nav__link--active' : 'app-nav__link--inactive'"
            >
                {{ link.label }}
            </a>
        </div>
    </nav>
</template>

<style scoped>
@reference "tailwindcss";

.app-nav {
    @apply border-b border-neutral-700 bg-neutral-900 px-4;
}

.app-nav__inner {
    @apply flex gap-1 overflow-x-auto py-2;
    scrollbar-width: none;
}

.app-nav__inner::-webkit-scrollbar {
    display: none;
}

.app-nav__link {
    @apply flex-shrink-0 rounded-full px-3 py-1 text-sm transition-colors;
}

.app-nav__link--active {
    @apply bg-orange-700 text-white;
}

.app-nav__link--inactive {
    @apply text-gray-400 hover:bg-neutral-700 hover:text-white;
}
</style>
