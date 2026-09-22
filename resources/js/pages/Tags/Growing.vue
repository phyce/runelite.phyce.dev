<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { growing, show } from '@/actions/App/Http/Controllers/TagController';
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
];

function setPeriod(period: TagPeriod): void {
    currentPeriod.value = period;
    router.get(growing.url(), { period }, { preserveState: true, preserveScroll: true });
}

const rows = computed((): TagRankEntry[] => props.rankings?.entries ?? []);
</script>

<template>
    <Head title="Fastest Growing Tags | RuneLite Plugin Stats" />

    <div class="tags-growing">
        <div class="tags-growing__top-bar">
            <div class="tags-growing__header">
                <h1 class="tags-growing__title">Fastest Growing Tags</h1>
                <p class="tags-growing__subtitle">Ranked by percentage install growth in the selected time period.</p>
            </div>

            <div class="tags-growing__periods">
                <button
                    v-for="p in periods"
                    :key="p.value"
                    class="tags-growing__period-btn"
                    :class="currentPeriod === p.value ? 'tags-growing__period-btn--active' : 'tags-growing__period-btn--inactive'"
                    @click="setPeriod(p.value)"
                >
                    {{ p.label }}
                </button>
            </div>
        </div>

        <TagTabs />

        <div v-if="!rows.length" class="tags-growing__empty">
            <p>Tag growth data is not yet available for this period. Check back soon.</p>
        </div>

        <div v-else class="tags-growing__wrapper">
            <table class="tags-growing__table">
                <thead class="tags-growing__head">
                    <tr>
                        <th scope="col" class="tags-growing__head-cell tags-growing__head-cell--rank">#</th>
                        <th scope="col" class="tags-growing__head-cell">Tag</th>
                        <th scope="col" class="tags-growing__head-cell tags-growing__head-cell--num">Plugins</th>
                        <th scope="col" class="tags-growing__head-cell tags-growing__head-cell--num">% Growth</th>
                        <th scope="col" class="tags-growing__head-cell tags-growing__head-cell--num">Installs gained</th>
                        <th scope="col" class="tags-growing__head-cell"><span class="tags-growing__head-cell-label">Plugins</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(entry, index) in rows"
                        :key="entry.slug"
                        class="tags-growing__row"
                        :class="index % 2 === 0 ? 'tags-growing__row--even' : 'tags-growing__row--odd'"
                    >
                        <td class="tags-growing__cell tags-growing__cell--rank">{{ entry.rank }}</td>
                        <td class="tags-growing__cell">
                            <a :href="show.url(entry.slug)" class="tags-growing__name">{{ entry.name }}</a>
                        </td>
                        <td class="tags-growing__cell tags-growing__cell--num">{{ formatNumber(entry.plugin_count) }}</td>
                        <td class="tags-growing__cell tags-growing__cell--pct">
                            <div class="tags-growing__pct">
                                <span class="tags-growing__pct-value" :class="entry.pct_growth >= 0 ? 'tags-growing__pct-value--up' : ''">
                                    {{ formatSignedPercent(entry.pct_growth) }}
                                </span>
                                <span class="tags-growing__pct-base">from {{ formatNumber(entry.growth_base) }}</span>
                            </div>
                        </td>
                        <td class="tags-growing__cell tags-growing__cell--num">
                            <div class="tags-growing__installs">
                                <span class="tags-growing__installs-gain">{{ formatSignedNumber(entry.absolute_growth) }}</span>
                                <span class="tags-growing__installs-total-sub">of {{ formatNumber(entry.total_installs) }}</span>
                            </div>
                        </td>
                        <td class="tags-growing__cell tags-growing__cell--action">
                            <a :href="show.url(entry.slug)" class="tags-growing__view-link">View</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.tags-growing {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-3 px-4 pt-2 sm:gap-4 sm:pt-4;
}

.tags-growing__top-bar {
    @apply flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3;
}

.tags-growing__header {
    @apply flex flex-col gap-0.5;
}

.tags-growing__title {
    @apply text-xl font-bold tracking-tight text-orange-500 sm:text-2xl;
}

.tags-growing__subtitle {
    @apply hidden text-sm text-gray-400 sm:block;
}

.tags-growing__periods {
    @apply flex flex-shrink-0 flex-wrap gap-1.5 sm:justify-end sm:gap-2;
}

.tags-growing__period-btn {
    @apply rounded-full px-3 py-1 text-sm font-medium transition-colors duration-100 sm:px-4;
}

.tags-growing__period-btn--active {
    background: #c54704;
    color: #fff;
}

.tags-growing__period-btn--inactive {
    @apply bg-neutral-800 text-gray-400;
    border: 1px solid #333;
}

.tags-growing__period-btn--inactive:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}

.tags-growing__empty {
    @apply rounded-lg border border-neutral-700 bg-neutral-900 p-10 text-center text-gray-400;
}

.tags-growing__wrapper {
    position: relative;
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
}

.tags-growing__table {
    @apply w-full text-left text-sm;
}

.tags-growing__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.tags-growing__head-cell {
    @apply px-2 py-2 text-xs font-medium uppercase tracking-wider text-gray-400 sm:px-4 sm:py-3;
    white-space: nowrap;
}

.tags-growing__head-cell--rank {
    @apply w-8 text-right sm:w-12;
    padding-right: 0;
}

.tags-growing__head-cell--num {
    @apply text-right;
    width: 1%;
}

.tags-growing__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.tags-growing__row--even {
    @apply bg-neutral-900;
}

.tags-growing__row--odd {
    background: #222222;
}

.tags-growing__row:hover {
    background: #3a2010;
}

.tags-growing__cell {
    @apply px-2 py-1.5 text-gray-300 sm:px-4 sm:py-2.5;
}

.tags-growing__cell--rank {
    @apply w-8 text-right text-sm font-medium tabular-nums text-gray-400 sm:w-12;
    padding-right: 0;
}

.tags-growing__cell--num {
    @apply text-right font-medium tabular-nums text-gray-200;
    width: 1%;
    white-space: nowrap;
}

.tags-growing__cell--pct {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.tags-growing__cell--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.tags-growing__name {
    @apply font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.tags-growing__pct {
    @apply flex flex-col items-end gap-0 sm:flex-row sm:items-baseline sm:justify-end sm:gap-1.5;
}

.tags-growing__pct-value {
    @apply text-sm font-bold tabular-nums text-gray-300;
}

.tags-growing__pct-value--up {
    color: #4ade80;
}

.tags-growing__pct-base {
    @apply text-xs tabular-nums text-gray-400;
}

.tags-growing__installs {
    @apply flex flex-col items-end gap-0 sm:flex-row sm:items-baseline sm:justify-end sm:gap-1.5;
}

.tags-growing__installs-gain {
    @apply text-sm font-medium tabular-nums text-gray-200;
}

.tags-growing__installs-total-sub {
    @apply text-xs tabular-nums text-gray-400;
}

.tags-growing__view-link {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.tags-growing__view-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}

.tags-growing__head-cell-label {
    @apply sr-only;
}
</style>
