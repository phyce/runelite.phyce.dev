<script setup lang="ts">
import { show } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { RankedPlugin, Top100Metrics } from '@/types';
import { formatNumber } from '@/utils/formatting';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    metrics: Top100Metrics | null;
}>();

const podiumEntries = computed((): RankedPlugin[] => {
    if (!props.metrics?.rankings) {
        return [];
    }
    return props.metrics.rankings
        .filter((e) => e.rank <= 3)
        .sort((a, b) => a.rank - b.rank);
});

const listEntries = computed((): RankedPlugin[] => {
    if (!props.metrics?.rankings) {
        return [];
    }
    return props.metrics.rankings
        .filter((e) => e.rank > 3)
        .sort((a, b) => a.rank - b.rank);
});

function pluginDisplayName(entry: RankedPlugin): string {
    return entry.plugin.display || entry.plugin.name;
}
</script>

<template>
    <Head title="Best RuneLite Plugins - Top 100 Ranked | RuneLite Stats" />

    <div class="top100">
        <div class="top100__header">
            <h1 class="top100__title">Current Top Plugins</h1>
            <p class="top100__subtitle">Ranked by popularity, growth, player retention, and various other metrics.</p>
        </div>

        <div v-if="!metrics || !metrics.rankings?.length" class="top100__empty">
            <p>Rankings are not yet available. Check back soon.</p>
        </div>

        <div v-else class="top100__content">

            <!-- #1 -->
            <div v-if="podiumEntries[0]" class="top100__card top100__card--gold">
                <div class="top100__card-rank top100__card-rank--gold">1</div>
                <div class="top100__card-body">
                    <a
                        :href="`https://runelite.net/plugin-hub/show/${podiumEntries[0].plugin.name}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="top100__card-name top100__card-name--gold"
                    >{{ pluginDisplayName(podiumEntries[0]) }}</a>
                    <span v-if="podiumEntries[0].plugin.author" class="top100__card-author top100__card-author--gold">
                        by {{ podiumEntries[0].plugin.author }}
                    </span>
                    <div class="top100__card-installs top100__card-installs--gold">
                        {{ formatNumber(podiumEntries[0].plugin.current_installs) }}
                        <span class="top100__card-installs-label top100__card-installs-label--gold">installs</span>
                    </div>
                </div>
                <a :href="show.url(podiumEntries[0].plugin.name)" class="top100__card-stats top100__card-stats--gold">Stats</a>
            </div>

            <!-- #2 -->
            <div v-if="podiumEntries[1]" class="top100__card top100__card--silver">
                <div class="top100__card-rank top100__card-rank--silver">2</div>
                <div class="top100__card-body">
                    <a
                        :href="`https://runelite.net/plugin-hub/show/${podiumEntries[1].plugin.name}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="top100__card-name top100__card-name--silver"
                    >{{ pluginDisplayName(podiumEntries[1]) }}</a>
                    <span v-if="podiumEntries[1].plugin.author" class="top100__card-author top100__card-author--silver">
                        by {{ podiumEntries[1].plugin.author }}
                    </span>
                    <div class="top100__card-installs top100__card-installs--silver">
                        {{ formatNumber(podiumEntries[1].plugin.current_installs) }}
                        <span class="top100__card-installs-label top100__card-installs-label--silver">installs</span>
                    </div>
                </div>
                <a :href="show.url(podiumEntries[1].plugin.name)" class="top100__card-stats top100__card-stats--silver">Stats</a>
            </div>

            <!-- #3 -->
            <div v-if="podiumEntries[2]" class="top100__card top100__card--bronze">
                <div class="top100__card-rank top100__card-rank--bronze">3</div>
                <div class="top100__card-body">
                    <a
                        :href="`https://runelite.net/plugin-hub/show/${podiumEntries[2].plugin.name}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="top100__card-name top100__card-name--bronze"
                    >{{ pluginDisplayName(podiumEntries[2]) }}</a>
                    <span v-if="podiumEntries[2].plugin.author" class="top100__card-author top100__card-author--bronze">
                        by {{ podiumEntries[2].plugin.author }}
                    </span>
                    <div class="top100__card-installs top100__card-installs--bronze">
                        {{ formatNumber(podiumEntries[2].plugin.current_installs) }}
                        <span class="top100__card-installs-label top100__card-installs-label--bronze">installs</span>
                    </div>
                </div>
                <a :href="show.url(podiumEntries[2].plugin.name)" class="top100__card-stats top100__card-stats--bronze">Stats</a>
            </div>

            <!-- #4–#100 -->
            <div class="top100__list">
                <div
                    v-for="(entry, index) in listEntries"
                    :key="entry.plugin.id"
                    class="top100__list-row"
                    :class="index % 2 === 0 ? 'top100__list-row--even' : 'top100__list-row--odd'"
                >
                    <span class="top100__list-rank">{{ entry.rank }}</span>
                    <div class="top100__list-plugin">
                        <a
                            :href="`https://runelite.net/plugin-hub/show/${entry.plugin.name}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="top100__list-name"
                        >{{ pluginDisplayName(entry) }}</a>
                        <span v-if="entry.plugin.author" class="top100__list-author">by {{ entry.plugin.author }}</span>
                    </div>
                    <span class="top100__list-installs">{{ formatNumber(entry.plugin.current_installs) }} installs</span>
                    <a :href="show.url(entry.plugin.name)" class="top100__list-stats">Stats</a>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.top100 {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 pt-4;
}

/* ── Header ── */

.top100__header {
    @apply flex flex-col gap-1;
}

.top100__title {
    @apply text-2xl font-bold tracking-tight text-orange-500;
}

.top100__subtitle {
    @apply text-sm text-gray-400;
}

/* ── Empty state ── */

.top100__empty {
    @apply rounded-lg border border-neutral-700 bg-neutral-900 p-10 text-center text-gray-400;
}

/* ── Content wrapper ── */

.top100__content {
    @apply flex flex-col gap-3 -mx-4 sm:mx-0;
}

/* ══════════════════════════════════════════
   SHARED CARD STRUCTURE
══════════════════════════════════════════ */

.top100__card {
    @apply flex items-center gap-5 border-y sm:rounded-2xl sm:border;
}

.top100__card-body {
    @apply flex flex-1 flex-col gap-0.5 min-w-0;
}

/* ── Gold (#1) ── */

.top100__card--gold {
    @apply p-6;
    background: linear-gradient(135deg, #0f0d00, #1e1a00);
    border-color: #c8a800;
}

.top100__card-rank--gold {
    @apply shrink-0 text-7xl font-bold leading-none tabular-nums;
    color: #ffd700;
}

.top100__card-name--gold {
    @apply truncate text-2xl font-bold leading-tight;
    color: #fff8c0;
    transition: color 0.1s;
}

.top100__card-name--gold:hover {
    color: #ffd700;
}

.top100__card-author--gold {
    @apply truncate text-sm;
    color: #a08800;
}

.top100__card-installs--gold {
    @apply mt-1 flex items-baseline gap-1.5 text-2xl font-bold tabular-nums;
    color: #ffd700;
}

.top100__card-installs-label--gold {
    @apply text-sm font-normal;
    color: #a08800;
}

.top100__card-stats--gold {
    @apply shrink-0 self-center rounded-lg px-3 py-1.5 text-sm font-medium transition-colors duration-100;
    background: rgba(200, 168, 0, 0.22);
    border: 1px solid rgba(200, 168, 0, 0.65);
    color: #ffd700;
}

.top100__card-stats--gold:hover {
    background: #c8a800;
    border-color: #c8a800;
    color: #000;
}

/* ── Silver (#2) ── */

.top100__card--silver {
    @apply p-5;
    background: linear-gradient(135deg, #0d1117, #161f2c);
    border-color: #4b5563;
}

.top100__card-rank--silver {
    @apply shrink-0 text-5xl font-bold leading-none tabular-nums;
    color: #e2e8f0;
}

.top100__card-name--silver {
    @apply truncate text-xl font-bold leading-tight;
    color: #f1f5f9;
    transition: color 0.1s;
}

.top100__card-name--silver:hover {
    color: #fff;
}

.top100__card-author--silver {
    @apply truncate text-sm;
    color: #64748b;
}

.top100__card-installs--silver {
    @apply mt-0.5 flex items-baseline gap-1.5 text-lg font-bold tabular-nums;
    color: #e2e8f0;
}

.top100__card-installs-label--silver {
    @apply text-xs font-normal;
    color: #64748b;
}

.top100__card-stats--silver {
    @apply shrink-0 self-center rounded-lg px-3 py-1.5 text-sm font-medium transition-colors duration-100;
    background: rgba(148, 163, 184, 0.18);
    border: 1px solid rgba(148, 163, 184, 0.55);
    color: #cbd5e1;
}

.top100__card-stats--silver:hover {
    background: #475569;
    border-color: #475569;
    color: #fff;
}

/* ── Bronze (#3) ── */

.top100__card--bronze {
    @apply p-4;
    background: linear-gradient(135deg, #0e0505, #180808);
    border-color: #8b2500;
}

.top100__card-rank--bronze {
    @apply shrink-0 text-4xl font-bold leading-none tabular-nums;
    color: #e8724a;
}

.top100__card-name--bronze {
    @apply truncate text-lg font-semibold leading-tight;
    color: #f0a888;
    transition: color 0.1s;
}

.top100__card-name--bronze:hover {
    color: #ffc0a0;
}

.top100__card-author--bronze {
    @apply truncate text-xs;
    color: #8b4030;
}

.top100__card-installs--bronze {
    @apply mt-0.5 flex items-baseline gap-1.5 text-base font-bold tabular-nums;
    color: #e8724a;
}

.top100__card-installs-label--bronze {
    @apply text-xs font-normal;
    color: #8b4030;
}

.top100__card-stats--bronze {
    @apply shrink-0 self-center rounded-lg px-3 py-1.5 text-sm font-medium transition-colors duration-100;
    background: rgba(192, 87, 42, 0.22);
    border: 1px solid rgba(192, 87, 42, 0.6);
    color: #e8724a;
}

.top100__card-stats--bronze:hover {
    background: #8b2500;
    border-color: #8b2500;
    color: #fff;
}

/* ══════════════════════════════════════════
   LIST — #4–#100
══════════════════════════════════════════ */

.top100__list {
    @apply overflow-hidden border-y border-neutral-700 sm:rounded-xl sm:border;
}

.top100__list-row {
    @apply flex items-center gap-3 px-4 py-2.5 transition-colors duration-75;
}

.top100__list-row--even {
    @apply bg-neutral-900;
}

.top100__list-row--odd {
    background: #222222;
}

.top100__list-row:hover {
    background: #3a2010;
}

.top100__list-rank {
    @apply w-8 shrink-0 text-right text-sm font-medium tabular-nums text-gray-400;
}

.top100__list-plugin {
    @apply flex flex-1 flex-col gap-0 min-w-0;
}

.top100__list-name {
    @apply truncate text-sm font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.top100__list-author {
    @apply truncate text-xs text-gray-400;
}

.top100__list-installs {
    @apply shrink-0 text-right text-sm tabular-nums text-gray-300;
}

.top100__list-stats {
    @apply shrink-0 rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.top100__list-stats:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}
</style>
