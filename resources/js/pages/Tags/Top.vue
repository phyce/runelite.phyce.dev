<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show } from '@/actions/App/Http/Controllers/TagController';
import TagTabs from '@/components/TagTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TagRankEntry, TagRankings } from '@/types';
import { formatNumber } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    rankings: TagRankings | null;
}>();

const rows = computed((): TagRankEntry[] => props.rankings?.entries ?? []);
</script>

<template>
    <Head title="Top Tags | RuneLite Plugin Stats" />

    <div class="tags-top">
        <div class="tags-top__header">
            <h1 class="tags-top__title">Top Tags</h1>
            <p class="tags-top__subtitle">Ranked by popularity, growth, and various other metrics.</p>
        </div>

        <TagTabs />

        <div v-if="!rows.length" class="tags-top__empty">
            <p>Tag rankings are not yet available. Check back soon.</p>
        </div>

        <div v-else class="tags-top__wrapper">
            <table class="tags-top__table">
                <thead class="tags-top__head">
                    <tr>
                        <th scope="col" class="tags-top__head-cell tags-top__head-cell--rank">#</th>
                        <th scope="col" class="tags-top__head-cell">Tag</th>
                        <th scope="col" class="tags-top__head-cell tags-top__head-cell--num">Plugins</th>
                        <th scope="col" class="tags-top__head-cell tags-top__head-cell--num" title="Combined current installs of plugins using this tag">Installs</th>
                        <th scope="col" class="tags-top__head-cell"><span class="tags-top__head-cell-label">Plugins</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(entry, index) in rows"
                        :key="entry.slug"
                        class="tags-top__row"
                        :class="index % 2 === 0 ? 'tags-top__row--even' : 'tags-top__row--odd'"
                    >
                        <td class="tags-top__cell tags-top__cell--rank">{{ entry.rank }}</td>
                        <td class="tags-top__cell">
                            <a :href="show.url(entry.slug)" class="tags-top__name">{{ entry.name }}</a>
                        </td>
                        <td class="tags-top__cell tags-top__cell--num">{{ formatNumber(entry.plugin_count) }}</td>
                        <td class="tags-top__cell tags-top__cell--num">{{ formatNumber(entry.total_installs) }}</td>
                        <td class="tags-top__cell tags-top__cell--action">
                            <a :href="show.url(entry.slug)" class="tags-top__view-link">View</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.tags-top {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-3 px-4 pt-2 sm:gap-4 sm:pt-4;
}

.tags-top__header {
    @apply flex flex-col gap-0.5;
}

.tags-top__title {
    @apply text-xl font-bold tracking-tight text-orange-500 sm:text-2xl;
}

.tags-top__subtitle {
    @apply hidden text-sm text-gray-400 sm:block;
}

.tags-top__empty {
    @apply rounded-lg border border-neutral-700 bg-neutral-900 p-10 text-center text-gray-400;
}

.tags-top__wrapper {
    position: relative;
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
}

.tags-top__table {
    @apply w-full text-left text-sm;
}

.tags-top__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.tags-top__head-cell {
    @apply px-2 py-2 text-xs font-medium uppercase tracking-wider text-gray-400 sm:px-4 sm:py-3;
    white-space: nowrap;
}

.tags-top__head-cell--rank {
    @apply w-8 text-right sm:w-12;
    padding-right: 0;
}

.tags-top__head-cell--num {
    @apply text-right;
    width: 1%;
}

.tags-top__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.tags-top__row--even {
    @apply bg-neutral-900;
}

.tags-top__row--odd {
    background: #222222;
}

.tags-top__row:hover {
    background: #3a2010;
}

.tags-top__cell {
    @apply px-2 py-1.5 text-gray-300 sm:px-4 sm:py-2.5;
}

.tags-top__cell--rank {
    @apply w-8 text-right text-sm font-medium tabular-nums text-gray-400 sm:w-12;
    padding-right: 0;
}

.tags-top__cell--num {
    @apply text-right font-medium tabular-nums text-gray-200;
    width: 1%;
    white-space: nowrap;
}

.tags-top__cell--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.tags-top__name {
    @apply font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.tags-top__view-link {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.tags-top__view-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}

.tags-top__head-cell-label {
    @apply sr-only;
}
</style>
