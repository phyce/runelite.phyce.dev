<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show as pluginShow } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Developer, DeveloperPluginEntry, Plugin } from '@/types';
import { formatDate, formatNumber } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    developer: Developer;
}>();

const sortedPlugins = computed((): DeveloperPluginEntry[] => {
    return [...(props.developer.plugins ?? [])].sort(
        (a, b) => b.plugin.current_installs - a.plugin.current_installs,
    );
});

const lastUpdatedPlugin = computed((): Plugin | null => {
    const entries = props.developer.plugins ?? [];
    if (!entries.length) {
        return null;
    }
    return entries.reduce(
        (latest, entry) => (new Date(entry.plugin.updated_on) > new Date(latest.updated_on) ? entry.plugin : latest),
        entries[0].plugin,
    );
});

function formatDateOrDash(value: string | null): string {
    return value ? formatDate(value) : '—';
}

function daysAgoLabel(days: number): string {
    if (days <= 0) {
        return 'today';
    }
    if (days === 1) {
        return 'yesterday';
    }
    return `${formatNumber(days)} days ago`;
}

function coauthorLabel(entry: DeveloperPluginEntry): string | null {
    if (!entry.coauthors?.length) {
        return null;
    }
    return `with ${entry.coauthors.join(', ')}`;
}
</script>

<template>
    <Head :title="`${developer.name} — RuneLite Plugin Developer Stats`" />

    <div class="dev-profile">
        <section class="dev-profile__hero">
            <div class="dev-profile__hero-top">
                <div class="dev-profile__identity">
                    <h1 class="dev-profile__name">{{ developer.name }}</h1>
                    <p v-if="developer.aliases?.length" class="dev-profile__aliases">
                        also listed as {{ developer.aliases.join(', ') }}
                    </p>
                </div>

                <div class="dev-profile__ranks">
                    <div class="dev-profile__rank">
                        <span class="dev-profile__rank-value">#{{ developer.ranks.installs }}</span>
                        <span class="dev-profile__rank-label">by installs</span>
                    </div>
                </div>
            </div>

            <div class="dev-profile__figures">
                <div class="dev-profile__figure" title="Peak installs yesterday across their plugins">
                    <span class="dev-profile__figure-label">Installs</span>
                    <span class="dev-profile__figure-value">{{ formatNumber(developer.total_installs) }}</span>
                </div>
                <div class="dev-profile__figure" title="Highest their plugins ever stood at once, combined">
                    <span class="dev-profile__figure-label">All-time high total</span>
                    <span class="dev-profile__figure-value dev-profile__figure-value--peak">
                        {{ formatNumber(developer.peak_installs) }}
                    </span>
                </div>
            </div>

            <div class="dev-profile__facts">
                <div class="dev-profile__fact">
                    <span class="dev-profile__fact-label" title="The date their oldest plugin was first listed on the hub — not their first commit">Publishing since</span>
                    <span class="dev-profile__fact-value">{{ formatDateOrDash(developer.contributing_since) }}</span>
                </div>
                <div class="dev-profile__fact">
                    <span class="dev-profile__fact-label">Last update</span>
                    <span class="dev-profile__fact-value">
                        {{ developer.last_updated ? daysAgoLabel(developer.days_since_update) : '—' }}
                    </span>
                    <span v-if="lastUpdatedPlugin" class="dev-profile__fact-note">
                        <a :href="pluginShow.url(lastUpdatedPlugin.name)" class="dev-profile__fact-link">
                            {{ lastUpdatedPlugin.display || lastUpdatedPlugin.name }}
                        </a>
                        · {{ formatDate(lastUpdatedPlugin.updated_on) }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Plugins -->
        <section class="dev-profile__card">
            <h2 class="dev-profile__card-title">
                Plugins
                <span class="dev-profile__card-count">{{ formatNumber(developer.plugin_count) }}</span>
            </h2>
            <div class="dev-profile__table-wrapper">
                <table class="dev-profile__table">
                    <thead class="dev-profile__table-head">
                        <tr>
                            <th scope="col" class="dev-profile__th">Plugin</th>
                            <th scope="col" class="dev-profile__th dev-profile__th--num">Installs</th>
                            <th scope="col" class="dev-profile__th dev-profile__th--num">All-time high</th>
                            <th scope="col" class="dev-profile__th dev-profile__th--num">Updated</th>
                            <th scope="col" class="dev-profile__th"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(entry, index) in sortedPlugins"
                            :key="entry.plugin.id"
                            class="dev-profile__tr"
                            :class="index % 2 === 0 ? 'dev-profile__tr--even' : 'dev-profile__tr--odd'"
                        >
                            <td class="dev-profile__td">
                                <div class="dev-profile__plugin">
                                    <a :href="pluginShow.url(entry.plugin.name)" class="dev-profile__plugin-name">
                                        {{ entry.plugin.display || entry.plugin.name }}
                                    </a>
                                    <span v-if="coauthorLabel(entry)" class="dev-profile__plugin-coauthors">
                                        {{ coauthorLabel(entry) }}
                                    </span>
                                </div>
                            </td>
                            <td class="dev-profile__td dev-profile__td--num">
                                {{ formatNumber(entry.plugin.current_installs) }}
                            </td>
                            <td class="dev-profile__td dev-profile__td--num dev-profile__td--muted">
                                {{ formatNumber(entry.plugin.all_time_high) }}
                            </td>
                            <td class="dev-profile__td dev-profile__td--num dev-profile__td--muted">
                                {{ formatDate(entry.plugin.updated_on) }}
                            </td>
                            <td class="dev-profile__td dev-profile__td--action">
                                <a :href="pluginShow.url(entry.plugin.name)" class="dev-profile__link-btn">Stats</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.dev-profile {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-3 px-4 py-3 sm:gap-4 sm:py-4;
}

.dev-profile__hero {
    @apply -mx-4 flex flex-col gap-4 border-y border-neutral-700 p-4 sm:mx-0 sm:gap-5 sm:rounded-xl sm:border sm:p-6;
    background: linear-gradient(135deg, #141414, #1c120b);
}

.dev-profile__hero-top {
    @apply flex items-start justify-between gap-4;
}

.dev-profile__identity {
    @apply flex min-w-0 flex-col gap-0.5;
}

.dev-profile__name {
    @apply truncate text-2xl font-bold tracking-tight text-orange-500 sm:text-3xl;
}

.dev-profile__aliases {
    @apply text-xs text-gray-500;
}

.dev-profile__figures {
    @apply flex flex-wrap items-end gap-x-8 gap-y-3 sm:gap-x-12;
}

.dev-profile__figure {
    @apply flex min-w-0 flex-col gap-0.5;
}

.dev-profile__figure-label {
    @apply text-xs font-medium uppercase tracking-wider text-gray-500;
}

.dev-profile__figure-value {
    @apply text-2xl font-bold tabular-nums text-gray-100 sm:text-3xl;
}

.dev-profile__figure-value--peak {
    @apply text-gray-500;
}

.dev-profile__facts {
    @apply grid grid-cols-1 gap-3 border-t pt-3 sm:grid-cols-2 sm:gap-4 sm:pt-4;
    border-color: #2a2a2a;
}

.dev-profile__fact {
    @apply flex min-w-0 flex-col gap-0.5;
}

.dev-profile__fact-label {
    @apply text-xs font-medium text-gray-500;
}

.dev-profile__fact-value {
    @apply text-base font-semibold text-gray-200;
}

.dev-profile__fact-note {
    @apply truncate text-xs text-gray-500;
}

.dev-profile__fact-link {
    @apply text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.dev-profile__ranks {
    @apply flex flex-shrink-0 gap-3;
}

.dev-profile__rank {
    @apply flex flex-col items-center rounded-lg px-4 py-2;
    background: rgba(197, 71, 4, 0.15);
    border: 1px solid rgba(255, 108, 33, 0.35);
}

.dev-profile__rank-value {
    @apply text-xl font-bold tabular-nums;
    color: #fb923c;
}

.dev-profile__rank-label {
    @apply text-xs text-gray-400;
}

.dev-profile__card {
    @apply -mx-4 flex flex-col gap-3 border-y border-neutral-700 p-4 sm:mx-0 sm:rounded-xl sm:border;
    background: #141414;
}

.dev-profile__card-title {
    @apply flex items-baseline gap-2 text-sm font-semibold uppercase tracking-widest text-gray-400;
}

.dev-profile__card-count {
    @apply text-xs font-medium tabular-nums text-gray-600;
}

.dev-profile__table-wrapper {
    @apply -mx-4 overflow-x-auto sm:mx-0 sm:rounded-lg sm:border sm:border-neutral-800;
    scrollbar-width: thin;
    scrollbar-color: #c54704 #1a1a1a;
}

.dev-profile__table-wrapper::-webkit-scrollbar {
    height: 6px;
}

.dev-profile__table-wrapper::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.dev-profile__table-wrapper::-webkit-scrollbar-thumb {
    background-color: #c54704;
    border-radius: 4px;
}

.dev-profile__table {
    @apply w-full text-left text-sm;
}

.dev-profile__table-head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.dev-profile__th {
    @apply px-3 py-2 text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-4;
    white-space: nowrap;
}

.dev-profile__th--num {
    @apply text-right;
    width: 1%;
}

.dev-profile__tr {
    border-bottom: 1px solid #1e1e1e;
}

.dev-profile__tr--even {
    background: #171717;
}

.dev-profile__tr--odd {
    background: #1f1f1f;
}

.dev-profile__td {
    @apply px-3 py-2 text-gray-300 sm:px-4;
}

.dev-profile__td--num {
    @apply text-right font-medium tabular-nums text-gray-200;
    width: 1%;
    white-space: nowrap;
}

.dev-profile__td--muted {
    @apply font-normal text-gray-500;
}

.dev-profile__td--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.dev-profile__plugin {
    @apply flex min-w-0 flex-col gap-0.5;
}

.dev-profile__plugin-name {
    @apply font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.dev-profile__plugin-coauthors {
    @apply text-xs text-gray-600;
}

.dev-profile__link-btn {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.dev-profile__link-btn:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}
</style>
