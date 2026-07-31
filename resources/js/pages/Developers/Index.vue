<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { show } from '@/actions/App/Http/Controllers/DeveloperController';
import DeveloperTabs from '@/components/DeveloperTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { DeveloperIndex, DeveloperSummary } from '@/types';
import { formatDate, formatNumber } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    developers: DeveloperIndex | null;
}>();

const searchInput = ref('');

type SortField = 'name' | 'plugin_count' | 'total_installs' | 'contributing_since';
type SortDirection = 'asc' | 'desc';

const sortField = ref<SortField>('total_installs');
const sortDirection = ref<SortDirection>('desc');

function handleSort(field: SortField): void {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = field === 'name' ? 'asc' : 'desc';
    }
}

function sortIndicator(field: SortField): string {
    if (sortField.value !== field) return '↕';
    return sortDirection.value === 'asc' ? '↑' : '↓';
}

function getValue(developer: DeveloperSummary, field: SortField): string | number {
    switch (field) {
        case 'name': return developer.name.toLowerCase();
        case 'plugin_count': return developer.plugin_count;
        case 'total_installs': return developer.total_installs;
        case 'contributing_since': return developer.contributing_since ? new Date(developer.contributing_since).getTime() : 0;
    }
}

const sortedDevelopers = computed((): DeveloperSummary[] => {
    return [...(props.developers?.developers ?? [])].sort((a, b) => {
        const va = getValue(a, sortField.value);
        const vb = getValue(b, sortField.value);
        const dir = sortDirection.value === 'asc' ? 1 : -1;
        if (typeof va === 'number' && typeof vb === 'number') {
            return (va - vb) * dir;
        }
        return String(va).localeCompare(String(vb)) * dir;
    });
});

const positionBySlug = computed((): Map<string, number> => {
    return new Map(sortedDevelopers.value.map((developer, index) => [developer.slug, index + 1]));
});

const visibleDevelopers = computed((): DeveloperSummary[] => {
    const query = searchInput.value.trim().toLowerCase();
    if (!query) {
        return sortedDevelopers.value;
    }
    return sortedDevelopers.value.filter(
        (developer) => developer.name.toLowerCase().includes(query) || developer.slug.includes(query),
    );
});

const columns: { field: SortField; label: string; numeric: boolean; title?: string }[] = [
    { field: 'name', label: 'Developer', numeric: false },
    { field: 'plugin_count', label: 'Plugins', numeric: true },
    { field: 'total_installs', label: 'Installs', numeric: true, title: 'Peak installs yesterday' },
    { field: 'contributing_since', label: 'Publishing since', numeric: true },
];
</script>

<template>
    <Head title="All RuneLite Plugin Developers | RuneLite Plugin Stats" />

    <div class="developers">
        <div class="developers__header">
            <h1 class="developers__title">All Developers</h1>
            <p class="developers__subtitle">Developer index, ranked by total install count.</p>
        </div>

        <DeveloperTabs />

        <div class="developers__controls">
            <input
                v-model="searchInput"
                class="developers__search"
                type="text"
                placeholder="Search developers by name…"
                autocomplete="off"
                spellcheck="false"
                aria-label="Search developers by name"
            />
            <span class="developers__count">
                {{ formatNumber(visibleDevelopers.length) }}
                {{ visibleDevelopers.length === 1 ? 'developer' : 'developers' }}
            </span>
        </div>

        <div v-if="!visibleDevelopers.length" class="developers__empty">
            <p v-if="searchInput.trim()">No developers match “{{ searchInput }}”.</p>
            <p v-else>Developer data is not yet available. Check back soon.</p>
        </div>

        <div v-else class="developers__wrapper">
            <table class="developers__table">
                <thead class="developers__head">
                    <tr>
                        <th scope="col" class="developers__head-cell developers__head-cell--rank" title="Position in the full list">#</th>
                        <th
                            v-for="col in columns"
                            :key="col.field"
                            scope="col"
                            class="developers__head-cell developers__head-cell--sortable"
                            :class="{ 'developers__head-cell--num': col.numeric }"
                            :title="col.title"
                            @click="handleSort(col.field)"
                        >
                            {{ col.label }}
                            <span :class="sortField === col.field ? 'developers__sort--active' : 'developers__sort--inactive'">{{ sortIndicator(col.field) }}</span>
                        </th>
                        <th scope="col" class="developers__head-cell"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(developer, index) in visibleDevelopers"
                        :key="developer.slug"
                        class="developers__row"
                        :class="index % 2 === 0 ? 'developers__row--even' : 'developers__row--odd'"
                    >
                        <td class="developers__cell developers__cell--rank">{{ positionBySlug.get(developer.slug) }}</td>
                        <td class="developers__cell">
                            <a :href="show.url(developer.slug)" class="developers__name">{{ developer.name }}</a>
                        </td>
                        <td class="developers__cell developers__cell--num">{{ formatNumber(developer.plugin_count) }}</td>
                        <td class="developers__cell developers__cell--num developers__cell--installs" title="Peak installs yesterday">
                            {{ formatNumber(developer.total_installs) }}
                        </td>
                        <td class="developers__cell developers__cell--date">
                            {{ developer.contributing_since ? formatDate(developer.contributing_since) : '—' }}
                        </td>
                        <td class="developers__cell developers__cell--action">
                            <a :href="show.url(developer.slug)" class="developers__profile-link">Profile</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.developers {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-3 px-4 pt-2 sm:gap-4 sm:pt-4;
}

.developers__header {
    @apply flex flex-col gap-0.5;
}

.developers__title {
    @apply text-xl font-bold tracking-tight text-orange-500 sm:text-2xl;
}

.developers__subtitle {
    @apply hidden text-sm text-gray-400 sm:block;
}

.developers__controls {
    @apply flex items-center gap-3;
}

.developers__search {
    @apply w-full max-w-sm rounded-md border border-neutral-600/70 bg-neutral-800 px-3 py-2 text-sm text-white placeholder:text-neutral-500 focus:border-orange-600/70 focus:outline-none focus:ring-1 focus:ring-orange-600/50;
}

.developers__count {
    @apply flex-shrink-0 text-xs tabular-nums text-gray-500;
}

.developers__empty {
    @apply rounded-lg border border-neutral-700 bg-neutral-900 p-10 text-center text-gray-400;
}

.developers__wrapper {
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
    scrollbar-width: thin;
    scrollbar-color: #c54704 #1a1a1a;
}

.developers__wrapper::-webkit-scrollbar {
    height: 6px;
}

.developers__wrapper::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.developers__wrapper::-webkit-scrollbar-thumb {
    background-color: #c54704;
    border-radius: 4px;
}

.developers__table {
    @apply w-full text-left text-sm;
}

.developers__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.developers__head-cell {
    @apply px-2 py-2 text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-4 sm:py-3;
    white-space: nowrap;
}

.developers__head-cell--rank {
    @apply w-8 text-right sm:w-12;
    padding-right: 0;
}

.developers__head-cell--num {
    @apply text-right;
    width: 1%;
}

.developers__head-cell--sortable {
    @apply cursor-pointer select-none;
}

.developers__head-cell--sortable:hover {
    color: #ff6c21;
}

.developers__sort--active {
    color: #ff6c21;
}

.developers__sort--inactive {
    @apply text-gray-700;
}

.developers__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.developers__row--even {
    @apply bg-neutral-900;
}

.developers__row--odd {
    background: #222222;
}

.developers__row:hover {
    background: #3a2010;
}

.developers__cell {
    @apply px-2 py-1.5 text-gray-300 sm:px-4 sm:py-2.5;
}

.developers__cell--rank {
    @apply w-8 text-right text-sm font-medium tabular-nums text-gray-400 sm:w-12;
    padding-right: 0;
}

.developers__cell--num {
    @apply text-right tabular-nums;
    width: 1%;
    white-space: nowrap;
}

.developers__cell--date {
    @apply text-right text-gray-400;
    width: 1%;
    white-space: nowrap;
}

.developers__cell--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.developers__cell--installs {
    @apply font-bold text-gray-200;
}

.developers__name {
    @apply font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.developers__profile-link {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.developers__profile-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}
</style>
