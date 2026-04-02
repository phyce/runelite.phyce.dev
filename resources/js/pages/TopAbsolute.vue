<script setup lang="ts">
import { show } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { GrowthEntry } from '@/types';
import { formatNumber } from '@/utils/formatting';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    entries: GrowthEntry[] | null;
    period: string;
}>();

const currentPeriod = ref(props.period);

const periods = [
    { value: '24h', label: '24h' },
    { value: '7d', label: '7d' },
    { value: '30d', label: '30d' },
    { value: '180d', label: '180d' },
    { value: '1y', label: '1y' },
];

function setPeriod(period: string): void {
    currentPeriod.value = period;
    router.get('/top/absolute', { period }, { preserveState: true, preserveScroll: true });
}

type SortField = 'rank' | 'name' | 'absolute_growth' | 'pct_growth';
type SortDirection = 'asc' | 'desc';

const sortField = ref<SortField>('absolute_growth');
const sortDirection = ref<SortDirection>('desc');

function handleSort(field: SortField): void {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'desc';
    }
}

function sortIndicator(field: SortField): string {
    if (sortField.value !== field) return '↕';
    return sortDirection.value === 'asc' ? '↑' : '↓';
}

function getValue(entry: GrowthEntry, field: SortField): string | number {
    switch (field) {
        case 'rank': return entry.rank;
        case 'name': return (entry.plugin.display || entry.plugin.name).toLowerCase();
        case 'absolute_growth': return entry.absolute_growth;
        case 'pct_growth': return entry.pct_growth;
    }
}

function hasWindowBaseline(entry: GrowthEntry): boolean {
    if (!entry.plugin.created_on || !entry.window_start) {
        return true;
    }
    return new Date(entry.plugin.created_on) <= new Date(entry.window_start);
}

const sortedEntries = computed(() => {
    if (!props.entries) {
        return [];
    }
    return [...props.entries].sort((a, b) => {
        const va = getValue(a, sortField.value);
        const vb = getValue(b, sortField.value);
        const dir = sortDirection.value === 'asc' ? 1 : -1;
        if (typeof va === 'number' && typeof vb === 'number') {
            return (va - vb) * dir;
        }
        return String(va).localeCompare(String(vb)) * dir;
    });
});
</script>

<template>
    <Head title="Most Popular - RuneLite Plugin Stats" />

    <div class="top-absolute">
        <div class="top-absolute__top-bar">
            <div class="top-absolute__header">
                <h1 class="top-absolute__title">Most Popular</h1>
                <p class="top-absolute__subtitle">Ranked by total install count gained in the selected time period.</p>
            </div>

            <div class="top-absolute__periods">
                <button
                    v-for="p in periods"
                    :key="p.value"
                    class="top-absolute__period-btn"
                    :class="currentPeriod === p.value ? 'top-absolute__period-btn--active' : 'top-absolute__period-btn--inactive'"
                    @click="setPeriod(p.value)"
                >
                    {{ p.label }}
                </button>
            </div>
        </div>

        <div v-if="!sortedEntries.length" class="top-absolute__empty">
            <p>Growth data is not yet available for this period. Check back soon.</p>
        </div>

        <div v-else class="top-absolute__wrapper">
            <table class="top-absolute__table">
                <thead class="top-absolute__head">
                    <tr>
                        <th
                            scope="col"
                            class="top-absolute__head-cell top-absolute__head-cell--rank top-absolute__head-cell--sortable"
                            @click="handleSort('rank')"
                        >
                            #
                            <span :class="sortField === 'rank' ? 'top-absolute__sort--active' : 'top-absolute__sort--inactive'">{{ sortIndicator('rank') }}</span>
                        </th>
                        <th
                            scope="col"
                            class="top-absolute__head-cell top-absolute__head-cell--sortable"
                            @click="handleSort('name')"
                        >
                            Plugin
                            <span :class="sortField === 'name' ? 'top-absolute__sort--active' : 'top-absolute__sort--inactive'">{{ sortIndicator('name') }}</span>
                        </th>
                        <th
                            scope="col"
                            class="top-absolute__head-cell top-absolute__head-cell--num top-absolute__head-cell--sortable"
                            @click="handleSort('absolute_growth')"
                        >
                            Installs
                            <span :class="sortField === 'absolute_growth' ? 'top-absolute__sort--active' : 'top-absolute__sort--inactive'">{{ sortIndicator('absolute_growth') }}</span>
                        </th>
                        <th
                            scope="col"
                            class="top-absolute__head-cell top-absolute__head-cell--num top-absolute__head-cell--sortable"
                            @click="handleSort('pct_growth')"
                        >
                            % Change
                            <span :class="sortField === 'pct_growth' ? 'top-absolute__sort--active' : 'top-absolute__sort--inactive'">{{ sortIndicator('pct_growth') }}</span>
                        </th>
                        <th scope="col" class="top-absolute__head-cell"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(entry, index) in sortedEntries"
                        :key="entry.plugin.id"
                        class="top-absolute__row"
                        :class="index % 2 === 0 ? 'top-absolute__row--even' : 'top-absolute__row--odd'"
                    >
                        <td class="top-absolute__cell top-absolute__cell--rank">{{ entry.rank }}</td>
                        <td class="top-absolute__cell">
                            <div class="top-absolute__plugin">
                                <a
                                    :href="`https://runelite.net/plugin-hub/show/${entry.plugin.name}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="top-absolute__plugin-name"
                                >{{ entry.plugin.display || entry.plugin.name }}</a>
                                <span v-if="entry.plugin.author" class="top-absolute__plugin-author">by {{ entry.plugin.author }}</span>
                            </div>
                        </td>
                        <td class="top-absolute__cell top-absolute__cell--installs">
                            <div class="top-absolute__installs">
                                <span class="top-absolute__installs-total">{{ formatNumber(entry.plugin.current_installs) }}</span>
                                <span class="top-absolute__installs-gain">+{{ formatNumber(entry.absolute_growth) }}</span>
                            </div>
                        </td>
                        <td class="top-absolute__cell top-absolute__cell--pct">
                            {{ hasWindowBaseline(entry) ? (entry.pct_growth >= 0 ? '+' : '') + entry.pct_growth.toFixed(1) + '%' : '—' }}
                        </td>
                        <td class="top-absolute__cell top-absolute__cell--action">
                            <a :href="show.url(entry.plugin.name)" class="top-absolute__stats-link">Stats</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.top-absolute {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 pt-4;
}

/* ── Top bar ── */

.top-absolute__top-bar {
    @apply flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between;
}

.top-absolute__header {
    @apply flex flex-col gap-1;
}

.top-absolute__title {
    @apply text-2xl font-bold tracking-tight text-orange-500;
}

.top-absolute__subtitle {
    @apply text-sm text-gray-400;
}

/* ── Period selector ── */

.top-absolute__periods {
    @apply flex flex-shrink-0 flex-wrap gap-2 sm:justify-end;
}

.top-absolute__period-btn {
    @apply rounded-full px-4 py-1 text-sm font-medium transition-colors duration-100;
}

.top-absolute__period-btn--active {
    background: #c54704;
    color: #fff;
}

.top-absolute__period-btn--inactive {
    @apply bg-neutral-800 text-gray-400;
    border: 1px solid #333;
}

.top-absolute__period-btn--inactive:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}

/* ── Empty ── */

.top-absolute__empty {
    @apply rounded-lg border border-neutral-700 bg-neutral-900 p-10 text-center text-gray-400;
}

/* ── Table wrapper ── */

.top-absolute__wrapper {
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
    scrollbar-width: thin;
    scrollbar-color: #c54704 #1a1a1a;
}

.top-absolute__wrapper::-webkit-scrollbar {
    height: 6px;
}

.top-absolute__wrapper::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.top-absolute__wrapper::-webkit-scrollbar-thumb {
    background-color: #c54704;
    border-radius: 4px;
}

/* ── Table ── */

.top-absolute__table {
    @apply w-full text-left text-sm;
}

/* ── Head ── */

.top-absolute__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.top-absolute__head-cell {
    @apply px-4 py-3 text-xs font-medium uppercase tracking-wider text-gray-500;
}

.top-absolute__head-cell--rank {
    @apply w-12 text-right;
}

.top-absolute__head-cell--num {
    @apply text-right;
}

.top-absolute__head-cell--sortable {
    @apply cursor-pointer select-none;
}

.top-absolute__head-cell--sortable:hover {
    color: #ff6c21;
}

.top-absolute__sort--active {
    color: #ff6c21;
}

.top-absolute__sort--inactive {
    @apply text-gray-700;
}

/* ── Rows ── */

.top-absolute__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.top-absolute__row--even {
    @apply bg-neutral-900;
}

.top-absolute__row--odd {
    background: #222222;
}

.top-absolute__row:hover {
    background: #3a2010;
}

/* ── Cells ── */

.top-absolute__cell {
    @apply px-4 py-2.5;
}

.top-absolute__cell--rank {
    @apply w-12 text-right text-sm font-medium tabular-nums text-gray-400;
}

.top-absolute__cell--installs {
    @apply text-right;
}

.top-absolute__installs {
    @apply flex flex-col items-end gap-0;
}

.top-absolute__installs-total {
    @apply text-sm font-bold tabular-nums text-gray-200;
}

.top-absolute__installs-gain {
    @apply text-xs tabular-nums;
    color: #4ade80;
}

.top-absolute__cell--pct {
    @apply text-right tabular-nums font-medium text-gray-300;
}

.top-absolute__cell--action {
    @apply text-right;
}

/* ── Plugin name + author ── */

.top-absolute__plugin {
    @apply flex flex-col gap-0 min-w-0;
}

.top-absolute__plugin-name {
    @apply truncate text-sm font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.top-absolute__plugin-author {
    @apply truncate text-xs text-gray-400;
}

/* ── Stats link ── */

.top-absolute__stats-link {
    @apply shrink-0 rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.top-absolute__stats-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}
</style>
