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
    router.get('/top/relative', { period }, { preserveState: true, preserveScroll: true });
}

type SortField = 'rank' | 'name' | 'pct_growth' | 'absolute_growth';
type SortDirection = 'asc' | 'desc';

const sortField = ref<SortField>('pct_growth');
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
        case 'pct_growth': return entry.pct_growth;
        case 'absolute_growth': return entry.absolute_growth;
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
    <Head title="Fastest Growing | RuneLite Plugin Stats" />

    <div class="top-relative">
        <div class="top-relative__top-bar">
            <div class="top-relative__header">
                <h1 class="top-relative__title">Fastest Growing</h1>
                <p class="top-relative__subtitle">Ranked by percentage install count growth in the selected time period.</p>
            </div>

            <div class="top-relative__periods">
                <button
                    v-for="p in periods"
                    :key="p.value"
                    class="top-relative__period-btn"
                    :class="currentPeriod === p.value ? 'top-relative__period-btn--active' : 'top-relative__period-btn--inactive'"
                    @click="setPeriod(p.value)"
                >
                    {{ p.label }}
                </button>
            </div>
        </div>

        <div v-if="!sortedEntries.length" class="top-relative__empty">
            <p>Growth data is not yet available for this period. Check back soon.</p>
        </div>

        <div v-else class="top-relative__wrapper">
            <table class="top-relative__table">
                <thead class="top-relative__head">
                    <tr>
                        <th
                            scope="col"
                            class="top-relative__head-cell top-relative__head-cell--rank top-relative__head-cell--sortable"
                            @click="handleSort('rank')"
                        >
                            #
                            <span :class="sortField === 'rank' ? 'top-relative__sort--active' : 'top-relative__sort--inactive'">{{ sortIndicator('rank') }}</span>
                        </th>
                        <th
                            scope="col"
                            class="top-relative__head-cell top-relative__head-cell--sortable"
                            @click="handleSort('name')"
                        >
                            Plugin
                            <span :class="sortField === 'name' ? 'top-relative__sort--active' : 'top-relative__sort--inactive'">{{ sortIndicator('name') }}</span>
                        </th>
                        <th
                            scope="col"
                            class="top-relative__head-cell top-relative__head-cell--num top-relative__head-cell--sortable"
                            @click="handleSort('pct_growth')"
                        >
                            % Change
                            <span :class="sortField === 'pct_growth' ? 'top-relative__sort--active' : 'top-relative__sort--inactive'">{{ sortIndicator('pct_growth') }}</span>
                        </th>
                        <th
                            scope="col"
                            class="top-relative__head-cell top-relative__head-cell--num top-relative__head-cell--sortable"
                            @click="handleSort('absolute_growth')"
                        >
                            Installs
                            <span :class="sortField === 'absolute_growth' ? 'top-relative__sort--active' : 'top-relative__sort--inactive'">{{ sortIndicator('absolute_growth') }}</span>
                        </th>
                        <th scope="col" class="top-relative__head-cell"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(entry, index) in sortedEntries"
                        :key="entry.plugin.id"
                        class="top-relative__row"
                        :class="index % 2 === 0 ? 'top-relative__row--even' : 'top-relative__row--odd'"
                    >
                        <td class="top-relative__cell top-relative__cell--rank">{{ entry.rank }}</td>
                        <td class="top-relative__cell">
                            <div class="top-relative__plugin">
                                <a
                                    :href="`https://runelite.net/plugin-hub/show/${entry.plugin.name}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="top-relative__plugin-name"
                                >{{ entry.plugin.display || entry.plugin.name }}</a>
                                <span v-if="entry.plugin.author" class="top-relative__plugin-author">by {{ entry.plugin.author }}</span>
                            </div>
                        </td>
                        <td class="top-relative__cell top-relative__cell--pct">
                            {{ hasWindowBaseline(entry) ? (entry.pct_growth >= 0 ? '+' : '') + entry.pct_growth.toFixed(1) + '%' : '—' }}
                        </td>
                        <td class="top-relative__cell top-relative__cell--installs">
                            <div class="top-relative__installs">
                                <span class="top-relative__installs-total">{{ formatNumber(entry.plugin.current_installs) }}</span>
                                <span class="top-relative__installs-gain">+{{ formatNumber(entry.absolute_growth) }}</span>
                            </div>
                        </td>
                        <td class="top-relative__cell top-relative__cell--action">
                            <a :href="show.url(entry.plugin.name)" class="top-relative__stats-link">Stats</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.top-relative {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-3 px-4 pt-2 sm:gap-6 sm:pt-4;
}

/* ── Top bar ── */

.top-relative__top-bar {
    @apply flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3;
}

.top-relative__header {
    @apply flex flex-col gap-0.5;
}

.top-relative__title {
    @apply text-xl font-bold tracking-tight text-orange-500 sm:text-2xl;
}

.top-relative__subtitle {
    @apply hidden text-sm text-gray-400 sm:block;
}

/* ── Period selector ── */

.top-relative__periods {
    @apply flex flex-shrink-0 flex-wrap gap-1.5 sm:gap-2 sm:justify-end;
}

.top-relative__period-btn {
    @apply rounded-full px-3 py-1 text-sm font-medium transition-colors duration-100 sm:px-4;
}

.top-relative__period-btn--active {
    background: #c54704;
    color: #fff;
}

.top-relative__period-btn--inactive {
    @apply bg-neutral-800 text-gray-400;
    border: 1px solid #333;
}

.top-relative__period-btn--inactive:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}

/* ── Empty ── */

.top-relative__empty {
    @apply rounded-lg border border-neutral-700 bg-neutral-900 p-10 text-center text-gray-400;
}

/* ── Table wrapper ── */

.top-relative__wrapper {
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
    scrollbar-width: thin;
    scrollbar-color: #c54704 #1a1a1a;
}

.top-relative__wrapper::-webkit-scrollbar {
    height: 6px;
}

.top-relative__wrapper::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.top-relative__wrapper::-webkit-scrollbar-thumb {
    background-color: #c54704;
    border-radius: 4px;
}

/* ── Table ── */

.top-relative__table {
    @apply w-full text-left text-sm;
}

/* ── Head ── */

.top-relative__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.top-relative__head-cell {
    @apply px-2 py-2 text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-4 sm:py-3;
}

.top-relative__head-cell--rank {
    @apply w-8 text-right sm:w-12;
    padding-right: 0;
}

.top-relative__head-cell--num {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.top-relative__head-cell--sortable {
    @apply cursor-pointer select-none;
}

.top-relative__head-cell--sortable:hover {
    color: #ff6c21;
}

.top-relative__sort--active {
    color: #ff6c21;
}

.top-relative__sort--inactive {
    @apply text-gray-700;
}

/* ── Rows ── */

.top-relative__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.top-relative__row--even {
    @apply bg-neutral-900;
}

.top-relative__row--odd {
    background: #222222;
}

.top-relative__row:hover {
    background: #3a2010;
}

/* ── Cells ── */

.top-relative__cell {
    @apply px-2 py-1.5 sm:px-4 sm:py-2.5;
}

.top-relative__cell--rank {
    @apply w-8 text-right text-sm font-medium tabular-nums text-gray-400 sm:w-12;
    padding-right: 0;
}

.top-relative__cell--installs {
    @apply text-right;
    width: 1%;
}

.top-relative__installs {
    @apply flex flex-col items-end gap-0;
}

.top-relative__installs-total {
    @apply text-sm font-bold tabular-nums text-gray-200;
}

.top-relative__installs-gain {
    @apply text-xs tabular-nums;
    color: #4ade80;
}

.top-relative__cell--pct {
    @apply text-right tabular-nums font-semibold;
    color: #86efac;
    width: 1%;
    white-space: nowrap;
}

.top-relative__cell--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

/* ── Plugin name + author ── */

.top-relative__plugin {
    @apply flex flex-col gap-0 min-w-0;
}

.top-relative__plugin-name {
    @apply truncate text-sm font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.top-relative__plugin-author {
    @apply truncate text-xs text-gray-400;
}

/* ── Stats link ── */

.top-relative__stats-link {
    @apply shrink-0 rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.top-relative__stats-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}
</style>
