<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show } from '@/actions/App/Http/Controllers/PluginController';
import { index as tagsIndex } from '@/actions/App/Http/Controllers/TagController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TagDetail } from '@/types';
import { formatDate, formatNumber, truncateString } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    tag: TagDetail;
    title: string;
}>();

const facts = computed(() => [
    { label: 'Rank by installs', value: `#${formatNumber(props.tag.ranks?.installs ?? 0)}` },
    { label: 'Plugins', value: formatNumber(props.tag.plugin_count) },
    { label: 'Total installs', value: formatNumber(props.tag.total_installs) },
    { label: 'First used', value: props.tag.first_used ? formatDate(props.tag.first_used) : '—' },
    { label: 'Last updated', value: props.tag.last_updated ? formatDate(props.tag.last_updated) : '—' },
]);

const rows = computed(() => props.tag.plugins ?? []);
</script>

<template>
    <Head :title="title" />

    <div class="tag-plugins">
        <div class="tag-plugins__header">
            <h1 class="tag-plugins__title">{{ tag.name }}</h1>
            <p class="tag-plugins__subtitle">
                RuneLite plugins tagged {{ tag.name }}, ranked by install count.
                <a :href="tagsIndex.url()" class="tag-plugins__all-link">All tags</a>
            </p>
        </div>

        <div class="tag-plugins__facts">
            <div v-for="fact in facts" :key="fact.label" class="tag-plugins__fact">
                <span class="tag-plugins__fact-label">{{ fact.label }}</span>
                <span class="tag-plugins__fact-value">{{ fact.value }}</span>
            </div>
        </div>

        <div v-if="!rows.length" class="tag-plugins__empty">
            <p>No plugins found with this tag. Check back soon.</p>
        </div>

        <div v-else class="tag-plugins__wrapper">
            <table class="tag-plugins__table">
                <thead class="tag-plugins__head">
                    <tr>
                        <th scope="col" class="tag-plugins__head-cell tag-plugins__head-cell--rank" title="Position by install count">#</th>
                        <th scope="col" class="tag-plugins__head-cell">Plugin</th>
                        <th scope="col" class="tag-plugins__head-cell tag-plugins__head-cell--num">Installs</th>
                        <th scope="col" class="tag-plugins__head-cell tag-plugins__head-cell--num">All-Time High</th>
                        <th scope="col" class="tag-plugins__head-cell">Description</th>
                        <th scope="col" class="tag-plugins__head-cell tag-plugins__head-cell--num">Updated</th>
                        <th scope="col" class="tag-plugins__head-cell"><span class="tag-plugins__head-cell-label">Stats</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(entry, index) in rows"
                        :key="entry.plugin.id"
                        :title="entry.plugin.warning"
                        class="tag-plugins__row"
                        :class="index % 2 === 0 ? 'tag-plugins__row--even' : 'tag-plugins__row--odd'"
                    >
                        <td class="tag-plugins__cell tag-plugins__cell--rank">{{ index + 1 }}</td>
                        <td class="tag-plugins__cell">
                            <a
                                class="tag-plugins__name-link"
                                :href="`https://runelite.net/plugin-hub/show/${entry.plugin.name}`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >{{ entry.plugin.display || entry.plugin.name }}</a>
                            <span v-if="entry.plugin.author" class="tag-plugins__author">by {{ entry.plugin.author }}</span>
                        </td>
                        <td class="tag-plugins__cell tag-plugins__cell--num">{{ formatNumber(entry.plugin.current_installs) }}</td>
                        <td class="tag-plugins__cell tag-plugins__cell--num tag-plugins__cell--secondary">{{ formatNumber(entry.plugin.all_time_high) }}</td>
                        <td class="tag-plugins__cell tag-plugins__cell--desc">{{ truncateString(entry.plugin.description, 100) }}</td>
                        <td class="tag-plugins__cell tag-plugins__cell--secondary tag-plugins__cell--date">{{ formatDate(entry.plugin.updated_on) }}</td>
                        <td class="tag-plugins__cell tag-plugins__cell--action">
                            <a :href="show.url(entry.plugin.name)" class="tag-plugins__stats-link">Stats</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.tag-plugins {
    @apply mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 pt-2 sm:gap-4 sm:pt-4;
}

.tag-plugins__header {
    @apply flex flex-col gap-0.5;
}

.tag-plugins__title {
    @apply text-xl font-bold tracking-tight text-orange-500 capitalize sm:text-2xl;
}

.tag-plugins__subtitle {
    @apply text-sm text-gray-400;
}

.tag-plugins__all-link {
    @apply text-orange-500 underline-offset-2 hover:underline;
}

.tag-plugins__facts {
    @apply grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5;
}

.tag-plugins__fact {
    @apply flex flex-col gap-0.5 rounded-lg border border-neutral-700 bg-neutral-900 px-3 py-2;
}

.tag-plugins__fact-label {
    @apply text-xs text-gray-500;
}

.tag-plugins__fact-value {
    @apply text-sm font-semibold tabular-nums text-gray-200;
}

.tag-plugins__empty {
    @apply rounded-xl border border-neutral-700 bg-neutral-900 p-6 text-sm text-gray-400;
}

.tag-plugins__wrapper {
    position: relative;
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
}

.tag-plugins__table {
    @apply w-full text-left text-sm;
}

.tag-plugins__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.tag-plugins__head-cell {
    @apply px-4 py-3 text-xs font-medium uppercase tracking-wider text-gray-400;
    white-space: nowrap;
}

.tag-plugins__head-cell--rank {
    @apply w-10 text-right;
}

.tag-plugins__head-cell--num {
    @apply text-right;
}

.tag-plugins__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.tag-plugins__row--even {
    @apply bg-neutral-900;
}

.tag-plugins__row--odd {
    background: #222222;
}

.tag-plugins__row:hover {
    background: #3a2010;
}

.tag-plugins__cell {
    @apply px-4 py-2.5 text-gray-300;
}

.tag-plugins__cell--rank {
    @apply text-right text-xs tabular-nums text-gray-500;
}

.tag-plugins__cell--num {
    @apply text-right tabular-nums font-medium text-gray-200;
    width: 1%;
    white-space: nowrap;
}

.tag-plugins__cell--secondary {
    @apply text-gray-400;
}

.tag-plugins__cell--desc {
    @apply text-gray-400;
}

.tag-plugins__cell--date {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.tag-plugins__cell--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.tag-plugins__name-link {
    @apply font-medium text-white hover:text-orange-500;
}

.tag-plugins__author {
    @apply ml-2 text-xs text-gray-500;
}

.tag-plugins__stats-link {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.tag-plugins__stats-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}

.tag-plugins__head-cell-label {
    @apply sr-only;
}
</style>
