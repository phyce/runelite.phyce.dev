<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { growing, show } from '@/actions/App/Http/Controllers/DeveloperController';
import DeveloperTabs from '@/components/DeveloperTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { DeveloperPeriod, DeveloperRankEntry, DeveloperRankings } from '@/types';
import { formatNumber, formatSignedNumber, formatSignedPercent } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    rankings: DeveloperRankings | null;
    period: DeveloperPeriod;
}>();

const currentPeriod = ref<DeveloperPeriod>(props.period);

const periods: { value: DeveloperPeriod; label: string }[] = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
    { value: 'year', label: 'Year' },
];

function setPeriod(period: DeveloperPeriod): void {
    currentPeriod.value = period;
    router.get(growing.url(), { period }, { preserveState: true, preserveScroll: true });
}

const rows = computed((): DeveloperRankEntry[] => props.rankings?.entries ?? []);
</script>

<template>
    <Head title="Fastest Growing RuneLite Plugin Developers | RuneLite Plugin Stats" />

    <div class="dev-growing">
        <div class="dev-growing__top-bar">
            <div class="dev-growing__header">
                <h1 class="dev-growing__title">Fastest Growing Developers</h1>
                <p class="dev-growing__subtitle">Ranked by percentage install growth over the selected time period.</p>
            </div>

            <div class="dev-growing__periods">
                <button
                    v-for="p in periods"
                    :key="p.value"
                    class="dev-growing__period-btn"
                    :class="currentPeriod === p.value ? 'dev-growing__period-btn--active' : 'dev-growing__period-btn--inactive'"
                    @click="setPeriod(p.value)"
                >
                    {{ p.label }}
                </button>
            </div>
        </div>

        <DeveloperTabs />

        <div v-if="!rows.length" class="dev-growing__empty">
            <p>Developer growth data is not yet available for this period. Check back soon.</p>
        </div>

        <div v-else class="dev-growing__wrapper">
            <table class="dev-growing__table">
                <thead class="dev-growing__head">
                    <tr>
                        <th scope="col" class="dev-growing__head-cell dev-growing__head-cell--rank">#</th>
                        <th scope="col" class="dev-growing__head-cell">Developer</th>
                        <th scope="col" class="dev-growing__head-cell dev-growing__head-cell--num">Plugins</th>
                        <th scope="col" class="dev-growing__head-cell dev-growing__head-cell--num">Growth</th>
                        <th scope="col" class="dev-growing__head-cell dev-growing__head-cell--num" title="Peak installs yesterday">Installs gained</th>
                        <th scope="col" class="dev-growing__head-cell"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(entry, index) in rows"
                        :key="entry.slug"
                        class="dev-growing__row"
                        :class="index % 2 === 0 ? 'dev-growing__row--even' : 'dev-growing__row--odd'"
                    >
                        <td class="dev-growing__cell dev-growing__cell--rank">{{ entry.rank }}</td>
                        <td class="dev-growing__cell">
                            <a :href="show.url(entry.slug)" class="dev-growing__name">{{ entry.name }}</a>
                        </td>
                        <td class="dev-growing__cell dev-growing__cell--num">{{ formatNumber(entry.plugin_count) }}</td>
                        <td class="dev-growing__cell dev-growing__cell--pct">
                            <div class="dev-growing__pct">
                                <span
                                    class="dev-growing__pct-value"
                                    :class="entry.pct_growth >= 0 ? 'dev-growing__pct-value--up' : 'dev-growing__pct-value--down'"
                                >{{ formatSignedPercent(entry.pct_growth) }}</span>
                                <span class="dev-growing__pct-base">from {{ formatNumber(entry.growth_base) }} installs</span>
                            </div>
                        </td>
                        <td class="dev-growing__cell dev-growing__cell--num" title="Peak installs yesterday">
                            <div class="dev-growing__installs">
                                <span class="dev-growing__installs-gain">{{ formatSignedNumber(entry.absolute_growth) }}</span>
                                <span class="dev-growing__installs-total">of {{ formatNumber(entry.total_installs) }}</span>
                            </div>
                        </td>
                        <td class="dev-growing__cell dev-growing__cell--action">
                            <a :href="show.url(entry.slug)" class="dev-growing__profile-link">Profile</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.dev-growing {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-3 px-4 pt-2 sm:gap-4 sm:pt-4;
}

.dev-growing__top-bar {
    @apply flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3;
}

.dev-growing__header {
    @apply flex flex-col gap-0.5;
}

.dev-growing__title {
    @apply text-xl font-bold tracking-tight text-orange-500 sm:text-2xl;
}

.dev-growing__subtitle {
    @apply hidden text-sm text-gray-400 sm:block;
}

.dev-growing__periods {
    @apply flex flex-shrink-0 flex-wrap gap-1.5 sm:justify-end sm:gap-2;
}

.dev-growing__period-btn {
    @apply rounded-full px-3 py-1 text-sm font-medium transition-colors duration-100 sm:px-4;
}

.dev-growing__period-btn--active {
    background: #c54704;
    color: #fff;
}

.dev-growing__period-btn--inactive {
    @apply bg-neutral-800 text-gray-400;
    border: 1px solid #333;
}

.dev-growing__period-btn--inactive:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}

.dev-growing__empty {
    @apply rounded-lg border border-neutral-700 bg-neutral-900 p-10 text-center text-gray-400;
}

.dev-growing__wrapper {
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
    scrollbar-width: thin;
    scrollbar-color: #c54704 #1a1a1a;
}

.dev-growing__wrapper::-webkit-scrollbar {
    height: 6px;
}

.dev-growing__wrapper::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.dev-growing__wrapper::-webkit-scrollbar-thumb {
    background-color: #c54704;
    border-radius: 4px;
}

.dev-growing__table {
    @apply w-full text-left text-sm;
}

.dev-growing__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.dev-growing__head-cell {
    @apply px-2 py-2 text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-4 sm:py-3;
    white-space: nowrap;
}

.dev-growing__head-cell--rank {
    @apply w-8 text-right sm:w-12;
    padding-right: 0;
}

.dev-growing__head-cell--num {
    @apply text-right;
    width: 1%;
}

.dev-growing__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.dev-growing__row--even {
    @apply bg-neutral-900;
}

.dev-growing__row--odd {
    background: #222222;
}

.dev-growing__row:hover {
    background: #3a2010;
}

.dev-growing__cell {
    @apply px-2 py-1.5 text-gray-300 sm:px-4 sm:py-2.5;
}

.dev-growing__cell--rank {
    @apply w-8 text-right text-sm font-medium tabular-nums text-gray-400 sm:w-12;
    padding-right: 0;
}

.dev-growing__cell--num {
    @apply text-right font-medium tabular-nums text-gray-200;
    width: 1%;
    white-space: nowrap;
}

.dev-growing__cell--pct {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.dev-growing__cell--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.dev-growing__name {
    @apply font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.dev-growing__pct {
    @apply flex flex-col items-end gap-0;
}

.dev-growing__pct-value {
    @apply text-sm font-bold tabular-nums;
}

.dev-growing__pct-value--up {
    color: #4ade80;
}

.dev-growing__pct-value--down {
    @apply text-gray-300;
}

.dev-growing__pct-base {
    @apply text-xs tabular-nums text-gray-500;
}

.dev-growing__installs {
    @apply flex flex-col items-end gap-0;
}

.dev-growing__installs-gain {
    @apply text-sm tabular-nums text-gray-200;
}

.dev-growing__installs-total {
    @apply text-xs tabular-nums text-gray-500;
}

.dev-growing__profile-link {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.dev-growing__profile-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}
</style>
