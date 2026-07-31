<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show } from '@/actions/App/Http/Controllers/DeveloperController';
import DeveloperTabs from '@/components/DeveloperTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { DeveloperRankEntry, DeveloperRankings } from '@/types';
import { formatNumber } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    rankings: DeveloperRankings | null;
}>();

const rows = computed((): DeveloperRankEntry[] => props.rankings?.entries ?? []);
</script>

<template>
    <Head title="Top RuneLite Plugin Developers | RuneLite Plugin Stats" />

    <div class="dev-top">
        <div class="dev-top__header">
            <h1 class="dev-top__title">Top Developers</h1>
            <p class="dev-top__subtitle">Ranked by popularity, growth, player retention, and various other metrics.</p>
        </div>

        <DeveloperTabs />

        <div v-if="!rows.length" class="dev-top__empty">
            <p>Developer rankings are not yet available. Check back soon.</p>
        </div>

        <div v-else class="dev-top__wrapper">
            <table class="dev-top__table">
                <thead class="dev-top__head">
                    <tr>
                        <th scope="col" class="dev-top__head-cell dev-top__head-cell--rank">#</th>
                        <th scope="col" class="dev-top__head-cell">Developer</th>
                        <th scope="col" class="dev-top__head-cell dev-top__head-cell--num">Plugins</th>
                        <th scope="col" class="dev-top__head-cell dev-top__head-cell--num" title="Peak installs yesterday">Installs</th>
                        <th scope="col" class="dev-top__head-cell"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(entry, index) in rows"
                        :key="entry.slug"
                        class="dev-top__row"
                        :class="index % 2 === 0 ? 'dev-top__row--even' : 'dev-top__row--odd'"
                    >
                        <td class="dev-top__cell dev-top__cell--rank">{{ entry.rank }}</td>
                        <td class="dev-top__cell">
                            <a :href="show.url(entry.slug)" class="dev-top__name">{{ entry.name }}</a>
                        </td>
                        <td class="dev-top__cell dev-top__cell--num">{{ formatNumber(entry.plugin_count) }}</td>
                        <td class="dev-top__cell dev-top__cell--num" title="Peak installs yesterday">
                            {{ formatNumber(entry.total_installs) }}
                        </td>
                        <td class="dev-top__cell dev-top__cell--action">
                            <a :href="show.url(entry.slug)" class="dev-top__profile-link">Profile</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.dev-top {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-3 px-4 pt-2 sm:gap-4 sm:pt-4;
}

/* ── Header ── */

.dev-top__header {
    @apply flex flex-col gap-0.5;
}

.dev-top__title {
    @apply text-xl font-bold tracking-tight text-orange-500 sm:text-2xl;
}

.dev-top__subtitle {
    @apply text-sm text-gray-400;
}

.dev-top__empty {
    @apply rounded-lg border border-neutral-700 bg-neutral-900 p-10 text-center text-gray-400;
}

.dev-top__wrapper {
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
    scrollbar-width: thin;
    scrollbar-color: #c54704 #1a1a1a;
}

.dev-top__wrapper::-webkit-scrollbar {
    height: 6px;
}

.dev-top__wrapper::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.dev-top__wrapper::-webkit-scrollbar-thumb {
    background-color: #c54704;
    border-radius: 4px;
}

.dev-top__table {
    @apply w-full text-left text-sm;
}

.dev-top__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.dev-top__head-cell {
    @apply px-2 py-2 text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-4 sm:py-3;
    white-space: nowrap;
}

.dev-top__head-cell--rank {
    @apply w-8 text-right sm:w-12;
    padding-right: 0;
}

.dev-top__head-cell--num {
    @apply text-right;
    width: 1%;
}

.dev-top__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.dev-top__row--even {
    @apply bg-neutral-900;
}

.dev-top__row--odd {
    background: #222222;
}

.dev-top__row:hover {
    background: #3a2010;
}

.dev-top__cell {
    @apply px-2 py-1.5 text-gray-300 sm:px-4 sm:py-2.5;
}

.dev-top__cell--rank {
    @apply w-8 text-right text-sm font-medium tabular-nums text-gray-400 sm:w-12;
    padding-right: 0;
}

.dev-top__cell--num {
    @apply text-right font-medium tabular-nums text-gray-200;
    width: 1%;
    white-space: nowrap;
}

.dev-top__cell--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.dev-top__name {
    @apply font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.dev-top__profile-link {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.dev-top__profile-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}
</style>
