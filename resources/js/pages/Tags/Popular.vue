<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { popular, show } from '@/actions/App/Http/Controllers/TagController';
import TagTabs from '@/components/TagTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TagPeriod, TagRankEntry, TagRankings } from '@/types';
import { formatNumber, formatSignedNumber, formatSignedPercent } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    rankings: TagRankings | null;
    period: TagPeriod;
}>();

const currentPeriod = ref<TagPeriod>(props.period);

const periods: { value: TagPeriod; label: string }[] = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
    { value: 'year', label: 'Year' },
    { value: 'all', label: 'All time' },
];

function setPeriod(period: TagPeriod): void {
    currentPeriod.value = period;
    router.get(popular.url(), { period }, { preserveState: true, preserveScroll: true });
}

const isAllTime = computed(() => props.period === 'all');
const growthLabel = computed(() => (isAllTime.value ? 'Total installs' : 'Installs gained'));

const rows = computed((): TagRankEntry[] => props.rankings?.entries ?? []);
</script>

<template>
    <Head title="Most Popular Tags | RuneLite Plugin Stats" />

    <div class="tags-popular">
        <div class="tags-popular__top-bar">
            <div class="tags-popular__header">
                <h1 class="tags-popular__title">Most Popular Tags</h1>
                <p class="tags-popular__subtitle">
                    {{ isAllTime
                        ? 'Ranked by total install count across each tag\'s plugins.'
                        : 'Ranked by total install count gained in the selected time period.' }}
                </p>
            </div>

            <div class="tags-popular__periods">
                <button
                    v-for="p in periods"
                    :key="p.value"
                    class="tags-popular__period-btn"
                    :class="currentPeriod === p.value ? 'tags-popular__period-btn--active' : 'tags-popular__period-btn--inactive'"
                    @click="setPeriod(p.value)"
                >
                    {{ p.label }}
                </button>
            </div>
        </div>

        <TagTabs />

        <div v-if="!rows.length" class="tags-popular__empty">
            <p>Tag growth data is not yet available for this period. Check back soon.</p>
        </div>

        <div v-else class="tags-popular__wrapper">
            <table class="tags-popular__table">
                <thead class="tags-popular__head">
                    <tr>
                        <th scope="col" class="tags-popular__head-cell tags-popular__head-cell--rank">#</th>
                        <th scope="col" class="tags-popular__head-cell">Tag</th>
                        <th scope="col" class="tags-popular__head-cell tags-popular__head-cell--num">Plugins</th>
                        <th scope="col" class="tags-popular__head-cell tags-popular__head-cell--num">{{ growthLabel }}</th>
                        <th scope="col" class="tags-popular__head-cell tags-popular__head-cell--num">% Change</th>
                        <th scope="col" class="tags-popular__head-cell"><span class="tags-popular__head-cell-label">Plugins</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(entry, index) in rows"
                        :key="entry.slug"
                        class="tags-popular__row"
                        :class="index % 2 === 0 ? 'tags-popular__row--even' : 'tags-popular__row--odd'"
                    >
                        <td class="tags-popular__cell tags-popular__cell--rank">{{ entry.rank }}</td>
                        <td class="tags-popular__cell">
                            <a :href="show.url(entry.slug)" class="tags-popular__name">{{ entry.name }}</a>
                        </td>
                        <td class="tags-popular__cell tags-popular__cell--num">{{ formatNumber(entry.plugin_count) }}</td>
                        <td class="tags-popular__cell tags-popular__cell--num">
                            <div class="tags-popular__installs">
                                <span v-if="isAllTime" class="tags-popular__installs-total">{{ formatNumber(entry.absolute_growth) }}</span>
                                <template v-else>
                                    <span
                                        class="tags-popular__installs-gain"
                                        :class="entry.absolute_growth >= 0 ? 'tags-popular__installs-gain--up' : 'tags-popular__installs-gain--down'"
                                    >{{ formatSignedNumber(entry.absolute_growth) }}</span>
                                    <span class="tags-popular__installs-total-sub">of {{ formatNumber(entry.total_installs) }}</span>
                                </template>
                            </div>
                        </td>
                        <td class="tags-popular__cell tags-popular__cell--pct">
                            <div class="tags-popular__pct">
                                <span class="tags-popular__pct-value">{{ formatSignedPercent(entry.pct_growth) }}</span>
                                <span class="tags-popular__pct-base">from {{ formatNumber(entry.growth_base) }}</span>
                            </div>
                        </td>
                        <td class="tags-popular__cell tags-popular__cell--action">
                            <a :href="show.url(entry.slug)" class="tags-popular__view-link">View</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.tags-popular {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-3 px-4 pt-2 sm:gap-4 sm:pt-4;
}

.tags-popular__top-bar {
    @apply flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3;
}

.tags-popular__header {
    @apply flex flex-col gap-0.5;
}

.tags-popular__title {
    @apply text-xl font-bold tracking-tight text-orange-500 sm:text-2xl;
}

.tags-popular__subtitle {
    @apply hidden text-sm text-gray-400 sm:block;
}

.tags-popular__periods {
    @apply flex flex-shrink-0 flex-wrap gap-1.5 sm:justify-end sm:gap-2;
}

.tags-popular__period-btn {
    @apply rounded-full px-3 py-1 text-sm font-medium transition-colors duration-100 sm:px-4;
}

.tags-popular__period-btn--active {
    background: #c54704;
    color: #fff;
}

.tags-popular__period-btn--inactive {
    @apply bg-neutral-800 text-gray-400;
    border: 1px solid #333;
}

.tags-popular__period-btn--inactive:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}

.tags-popular__empty {
    @apply rounded-lg border border-neutral-700 bg-neutral-900 p-10 text-center text-gray-400;
}

.tags-popular__wrapper {
    position: relative;
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
}

.tags-popular__table {
    @apply w-full text-left text-sm;
}

.tags-popular__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.tags-popular__head-cell {
    @apply px-2 py-2 text-xs font-medium uppercase tracking-wider text-gray-400 sm:px-4 sm:py-3;
    white-space: nowrap;
}

.tags-popular__head-cell--rank {
    @apply w-8 text-right sm:w-12;
    padding-right: 0;
}

.tags-popular__head-cell--num {
    @apply text-right;
    width: 1%;
}

.tags-popular__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.tags-popular__row--even {
    @apply bg-neutral-900;
}

.tags-popular__row--odd {
    background: #222222;
}

.tags-popular__row:hover {
    background: #3a2010;
}

.tags-popular__cell {
    @apply px-2 py-1.5 text-gray-300 sm:px-4 sm:py-2.5;
}

.tags-popular__cell--rank {
    @apply w-8 text-right text-sm font-medium tabular-nums text-gray-400 sm:w-12;
    padding-right: 0;
}

.tags-popular__cell--num {
    @apply text-right font-medium tabular-nums text-gray-200;
    width: 1%;
    white-space: nowrap;
}

.tags-popular__cell--pct {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.tags-popular__cell--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.tags-popular__name {
    @apply font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.tags-popular__installs {
    @apply flex flex-col items-end gap-0 sm:flex-row sm:items-baseline sm:justify-end sm:gap-1.5;
}

.tags-popular__installs-total {
    @apply text-sm font-bold tabular-nums text-gray-200;
}

.tags-popular__installs-gain {
    @apply text-sm font-bold tabular-nums;
}

.tags-popular__installs-gain--up {
    color: #4ade80;
}

.tags-popular__installs-gain--down {
    @apply text-gray-300;
}

.tags-popular__installs-total-sub {
    @apply text-xs tabular-nums text-gray-400;
}

.tags-popular__pct {
    @apply flex flex-col items-end gap-0 sm:flex-row sm:items-baseline sm:justify-end sm:gap-1.5;
}

.tags-popular__pct-value {
    @apply text-sm font-medium tabular-nums text-gray-300;
}

.tags-popular__pct-base {
    @apply text-xs tabular-nums text-gray-400;
}

.tags-popular__view-link {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.tags-popular__view-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}

.tags-popular__head-cell-label {
    @apply sr-only;
}
</style>
