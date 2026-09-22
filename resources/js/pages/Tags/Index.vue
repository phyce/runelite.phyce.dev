<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { cloud, show as tagShow } from '@/actions/App/Http/Controllers/TagController';
import TagTabs from '@/components/TagTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TagSummary } from '@/types';
import { formatNumber } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    tags: TagSummary[];
}>();

const searchInput = ref('');

type SortField = 'name' | 'plugin_count' | 'total_installs';

const sortField = ref<SortField>('plugin_count');
const sortDirection = ref<'asc' | 'desc'>('desc');

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

const sortedTags = computed(() => {
    return [...props.tags].sort((a, b) => {
        const valueA = a[sortField.value] ?? '';
        const valueB = b[sortField.value] ?? '';
        if (typeof valueA === 'number' && typeof valueB === 'number') {
            return sortDirection.value === 'asc' ? valueA - valueB : valueB - valueA;
        }
        return sortDirection.value === 'asc'
            ? String(valueA).localeCompare(String(valueB))
            : String(valueB).localeCompare(String(valueA));
    });
});

const positionBySlug = computed(() => new Map(sortedTags.value.map((tag, index) => [tag.slug, index + 1])));

const visibleTags = computed(() => {
    const q = searchInput.value.trim().toLowerCase();
    if (!q) {
        return sortedTags.value;
    }
    return sortedTags.value.filter((tag) => tag.name.toLowerCase().includes(q));
});

const columns: { field: SortField; label: string; numeric: boolean }[] = [
    { field: 'name', label: 'Tag', numeric: false },
    { field: 'plugin_count', label: 'Plugins', numeric: true },
    { field: 'total_installs', label: 'Installs', numeric: true },
];
</script>

<template>
    <Head title="Tags | RuneLite Plugin Stats" />

    <div class="tags-index">
        <div class="tags-index__header">
            <div class="tags-index__title-row">
                <h1 class="tags-index__title">All Tags</h1>
                <a :href="cloud.url()" class="tags-index__cloud-link">
                    <svg class="tags-index__cloud-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">
                        <circle cx="8" cy="8" r="6.2" />
                        <ellipse cx="8" cy="8" rx="6.2" ry="2.6" />
                        <ellipse cx="8" cy="8" rx="2.6" ry="6.2" />
                    </svg>
                    3D Cloud
                </a>
            </div>
            <p class="tags-index__subtitle">Every tag used across RuneLite plugins, ranked by how many plugins use it.</p>
        </div>

        <TagTabs />

        <div class="tags-index__controls">
            <input
                v-model="searchInput"
                class="tags-index__search"
                type="text"
                placeholder="Search tags…"
                autocomplete="off"
                spellcheck="false"
                aria-label="Search tags"
            />
            <span class="tags-index__count">
                {{ formatNumber(visibleTags.length) }} {{ visibleTags.length === 1 ? 'tag' : 'tags' }}
            </span>
        </div>

        <div v-if="!visibleTags.length" class="tags-index__empty">
            <p>No tags match “{{ searchInput }}”.</p>
        </div>

        <div v-else class="tags-index__wrapper">
            <table class="tags-index__table">
                <thead class="tags-index__head">
                    <tr>
                        <th scope="col" class="tags-index__head-cell tags-index__head-cell--rank" title="Position in the current order">#</th>
                        <th
                            v-for="col in columns"
                            :key="col.field"
                            scope="col"
                            class="tags-index__head-cell tags-index__head-cell--sortable"
                            :class="{ 'tags-index__head-cell--num': col.numeric }"
                            @click="handleSort(col.field)"
                        >
                            {{ col.label }}
                            <span :class="sortField === col.field ? 'tags-index__sort--active' : 'tags-index__sort--inactive'">{{ sortIndicator(col.field) }}</span>
                        </th>
                        <th scope="col" class="tags-index__head-cell"><span class="tags-index__head-cell-label">Plugins</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(tag, index) in visibleTags"
                        :key="tag.slug"
                        class="tags-index__row"
                        :class="index % 2 === 0 ? 'tags-index__row--even' : 'tags-index__row--odd'"
                    >
                        <td class="tags-index__cell tags-index__cell--rank">{{ positionBySlug.get(tag.slug) }}</td>
                        <td class="tags-index__cell">
                            <a :href="tagShow.url(tag.slug)" class="tags-index__name">{{ tag.name }}</a>
                        </td>
                        <td class="tags-index__cell tags-index__cell--num">{{ formatNumber(tag.plugin_count) }}</td>
                        <td class="tags-index__cell tags-index__cell--num tags-index__cell--secondary" title="Combined current installs of plugins using this tag">
                            {{ formatNumber(tag.total_installs) }}
                        </td>
                        <td class="tags-index__cell tags-index__cell--action">
                            <a :href="tagShow.url(tag.slug)" class="tags-index__plugins-link">Plugins</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.tags-index {
    @apply mx-auto flex w-full max-w-5xl flex-col gap-3 px-4 pt-2 sm:gap-4 sm:pt-4;
}

.tags-index__header {
    @apply flex flex-col gap-0.5;
}

.tags-index__title-row {
    @apply flex flex-wrap items-center justify-between gap-3;
}

.tags-index__title {
    @apply text-xl font-bold tracking-tight text-orange-500 sm:text-2xl;
}

.tags-index__cloud-link {
    @apply inline-flex items-center gap-1.5 rounded-full px-3.5 py-1 text-sm font-semibold text-white no-underline transition-all duration-150;
    background: linear-gradient(135deg, #ff6c21, #c54704);
    box-shadow: 0 0 12px rgba(255, 108, 33, 0.35);
}

.tags-index__cloud-link:hover {
    box-shadow: 0 0 20px rgba(255, 108, 33, 0.6);
    transform: translateY(-1px);
}

.tags-index__cloud-icon {
    @apply h-4 w-4;
}

.tags-index__subtitle {
    @apply hidden text-sm text-gray-400 sm:block;
}

.tags-index__controls {
    @apply flex flex-wrap items-center gap-3;
}

.tags-index__search {
    @apply w-full max-w-sm rounded-md border border-neutral-600/70 bg-neutral-800 px-3 py-2 text-sm text-white placeholder:text-neutral-500 focus:border-orange-600/70 focus:outline-none focus:ring-1 focus:ring-orange-600/50;
}

.tags-index__count {
    @apply flex-shrink-0 text-xs tabular-nums text-gray-400;
}

.tags-index__empty {
    @apply rounded-xl border border-neutral-700 bg-neutral-900 p-6 text-sm text-gray-400;
}

.tags-index__wrapper {
    position: relative;
    @apply -mx-4 overflow-x-auto border-y border-neutral-700 sm:mx-0 sm:rounded-xl sm:border;
}

.tags-index__table {
    @apply w-full text-left text-sm;
}

.tags-index__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.tags-index__head-cell {
    @apply px-2 py-2 text-xs font-medium uppercase tracking-wider text-gray-400 sm:px-4 sm:py-3;
    white-space: nowrap;
}

.tags-index__head-cell--sortable {
    @apply cursor-pointer select-none;
}

.tags-index__head-cell--sortable:hover {
    color: #ff6c21;
}

.tags-index__head-cell--rank {
    @apply w-8 text-right sm:w-12;
    padding-right: 0;
}

.tags-index__head-cell--num {
    @apply text-right;
}

.tags-index__sort--active {
    color: #ff6c21;
}

.tags-index__sort--inactive {
    @apply text-gray-700;
}

.tags-index__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
}

.tags-index__row--even {
    @apply bg-neutral-900;
}

.tags-index__row--odd {
    background: #222222;
}

.tags-index__row:hover {
    background: #3a2010;
}

.tags-index__cell {
    @apply px-2 py-1.5 text-gray-300 sm:px-4 sm:py-2.5;
}

.tags-index__cell--rank {
    @apply w-8 text-right text-xs tabular-nums text-gray-500 sm:w-12;
    padding-right: 0;
}

.tags-index__cell--num {
    @apply text-right tabular-nums font-medium text-gray-200;
    white-space: nowrap;
}

.tags-index__cell--secondary {
    @apply text-gray-400;
}

.tags-index__cell--action {
    @apply text-right;
    width: 1%;
    white-space: nowrap;
}

.tags-index__name {
    @apply font-medium text-white hover:text-orange-500;
}

.tags-index__plugins-link {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.tags-index__plugins-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}

.tags-index__head-cell-label {
    @apply sr-only;
}

</style>
