<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, inject, onMounted, onUnmounted, ref } from 'vue';
import { show } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import { filterPlugins, pluginSearchKey } from '@/lib/pluginSearch';
import type { Plugin } from '@/types';
import { formatDate, truncateString } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    plugins: Plugin[];
}>();

const searchQuery = inject(pluginSearchKey, ref(''));

type SortField = keyof Plugin;
type SortDirection = 'asc' | 'desc';

const sortField = ref<SortField>('current_installs');
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

function ariaSort(field: SortField): 'ascending' | 'descending' | 'none' {
    if (sortField.value !== field) return 'none';
    return sortDirection.value === 'asc' ? 'ascending' : 'descending';
}

const sortedPlugins = computed(() => {
    return [...props.plugins].sort((a, b) => {
        const valueA = a[sortField.value];
        const valueB = b[sortField.value];
        if (typeof valueA === 'number' && typeof valueB === 'number') {
            return sortDirection.value === 'asc' ? valueA - valueB : valueB - valueA;
        }
        return sortDirection.value === 'asc'
            ? String(valueA).localeCompare(String(valueB))
            : String(valueB).localeCompare(String(valueA));
    });
});

const visiblePlugins = computed(() => filterPlugins(sortedPlugins.value, searchQuery.value));

const tableWrapper = ref<HTMLElement>();
const stickyScrollbar = ref<HTMLElement>();
const scrollWidth = ref(0);

let resizeObserver: ResizeObserver | null = null;

onMounted(() => {
    const wrapper = tableWrapper.value;
    const sticky = stickyScrollbar.value;
    if (!wrapper || !sticky) return;

    const updateWidth = () => {
        scrollWidth.value = wrapper.scrollWidth;
    };
    updateWidth();

    resizeObserver = new ResizeObserver(updateWidth);
    resizeObserver.observe(wrapper);

    let syncing = false;
    wrapper.addEventListener('scroll', () => {
        if (syncing) return;
        syncing = true;
        sticky.scrollLeft = wrapper.scrollLeft;
        syncing = false;
    });
    sticky.addEventListener('scroll', () => {
        if (syncing) return;
        syncing = true;
        wrapper.scrollLeft = sticky.scrollLeft;
        syncing = false;
    });
});

onUnmounted(() => {
    resizeObserver?.disconnect();
});

const columns: { field: SortField; label: string }[] = [
    { field: 'name', label: 'Plugin' },
    { field: 'current_installs', label: 'Installs' },
    { field: 'all_time_high', label: 'All-Time High' },
    { field: 'description', label: 'Description' },
    { field: 'updated_on', label: 'Updated' },
];
</script>

<template>
    <Head title="RuneLite Plugin Stats" />

    <div ref="tableWrapper" class="plugin-table__wrapper">
        <div class="plugin-table" role="table" aria-label="RuneLite plugins">
            <div class="plugin-table__head" role="rowgroup">
                <div class="plugin-table__head-row" role="row">
                    <div
                        v-for="col in columns"
                        :key="col.field"
                        role="columnheader"
                        tabindex="0"
                        class="plugin-table__head-cell plugin-table__head-cell--sortable"
                        :aria-sort="ariaSort(col.field)"
                        @click="handleSort(col.field)"
                        @keydown.enter.prevent="handleSort(col.field)"
                        @keydown.space.prevent="handleSort(col.field)"
                    >
                        {{ col.label }}
                        <span :class="sortField === col.field ? 'plugin-table__sort--active' : 'plugin-table__sort--inactive'">{{ sortIndicator(col.field) }}</span>
                    </div>
                    <div role="columnheader" class="plugin-table__head-cell"><span class="plugin-table__head-cell-label">Stats</span></div>
                </div>
            </div>
            <div class="plugin-table__body" role="rowgroup">
                <div
                    v-for="(plugin, index) in visiblePlugins"
                    :key="plugin.id"
                    role="row"
                    :title="plugin.warning"
                    class="plugin-table__row"
                    :class="index % 2 === 0 ? 'plugin-table__row--even' : 'plugin-table__row--odd'"
                >
                    <div role="cell" class="plugin-table__cell">
                        <a
                            class="plugin-table__name-link"
                            :href="`https://runelite.net/plugin-hub/show/${plugin.name}`"
                            target="_blank"
                            rel="noopener noreferrer"
                        >{{ plugin.display || plugin.name }}</a>
                        <span v-if="plugin.author" class="plugin-table__author">by {{ plugin.author }}</span>
                    </div>
                    <div role="cell" class="plugin-table__cell plugin-table__cell--num">{{ plugin.current_installs.toLocaleString('en-US') }}</div>
                    <div role="cell" class="plugin-table__cell plugin-table__cell--num plugin-table__cell--secondary">{{ plugin.all_time_high.toLocaleString('en-US') }}</div>
                    <div role="cell" class="plugin-table__cell plugin-table__cell--desc">{{ truncateString(plugin.description, 100) }}</div>
                    <div role="cell" class="plugin-table__cell plugin-table__cell--secondary plugin-table__cell--date">{{ formatDate(plugin.updated_on) }}</div>
                    <div role="cell" class="plugin-table__cell plugin-table__cell--action">
                        <a :href="show.url(plugin.name)" class="plugin-table__stats-link">Stats</a>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="visiblePlugins.length === 0" class="plugin-table__empty">
            No plugins match “{{ searchQuery.trim() }}”.
        </p>
    </div>

    <p class="plugin-table__count" aria-live="polite">
        <template v-if="searchQuery.trim()">
            Showing {{ visiblePlugins.length.toLocaleString('en-US') }} of
            {{ sortedPlugins.length.toLocaleString('en-US') }} plugins
        </template>
        <template v-else>{{ sortedPlugins.length.toLocaleString('en-US') }} plugins</template>
    </p>

    <div ref="stickyScrollbar" class="plugin-table__scrollbar">
        <div :style="{ width: scrollWidth + 'px' }" class="plugin-table__scrollbar-spacer" />
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.plugin-table__wrapper {
    @apply overflow-x-auto border-y border-neutral-700 sm:rounded-xl sm:border;
    scrollbar-width: none;
}

.plugin-table__wrapper::-webkit-scrollbar {
    display: none;
}

/*
 * Grid rather than <table>: content-visibility has no effect on internal table
 * elements, so rows have to be ordinary grid boxes for the browser to be able
 * to skip laying out the ones that are off screen.
 *
 * Each row carries the column track list itself rather than using `subgrid`.
 * content-visibility implies layout containment, and containment forces a
 * subgrid to become an independent grid - the rows would silently lose the
 * parent's tracks and every cell would take a full row. Repeating an explicit
 * template keeps the columns aligned precisely because it does not depend on
 * what any sibling row contains.
 */
.plugin-table {
    @apply w-full text-left text-sm;
    min-width: 57rem;
}

.plugin-table__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.plugin-table__head-row,
.plugin-table__row {
    @apply grid items-center;
    grid-template-columns: minmax(12rem, 2fr) 7rem 9rem minmax(14rem, 3fr) 9.5rem 5.5rem;
}

.plugin-table__head-cell {
    @apply px-4 py-3 text-xs font-medium uppercase tracking-wider text-gray-400;
    white-space: nowrap;
}

.plugin-table__head-cell--sortable {
    @apply cursor-pointer select-none;
}

.plugin-table__head-cell--sortable:focus-visible {
    @apply outline-none;
    box-shadow: inset 0 0 0 2px #ff6c21;
}

.plugin-table__head-cell-label {
    @apply sr-only;
}

.plugin-table__head-cell--sortable:hover {
    color: #ff6c21;
}

.plugin-table__sort--active {
    color: #ff6c21;
}

.plugin-table__sort--inactive {
    @apply text-gray-700;
}

/*
 * The list runs to ~2000 rows. `content-visibility: auto` lets the browser skip
 * style, layout and paint for rows outside the viewport; `contain-intrinsic-size`
 * supplies the placeholder height so the scrollbar stays honest. The `auto`
 * keyword means the real height is remembered once a row has been rendered, so
 * the estimate only has to be close for rows that have never been on screen.
 */
.plugin-table__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
    content-visibility: auto;
    contain-intrinsic-size: auto 41px;
}

/* Rows wrap to three description lines once the grid is horizontally scrolled. */
@media (max-width: 639px) {
    .plugin-table__row {
        contain-intrinsic-size: auto 61px;
    }
}

.plugin-table__row--even {
    @apply bg-neutral-900;
}

.plugin-table__row--odd {
    background: #222222;
}

.plugin-table__row:hover {
    background: #3a2010;
}

.plugin-table__cell {
    @apply px-4 py-2.5 text-gray-300;
}

.plugin-table__cell--num {
    @apply tabular-nums font-medium text-gray-200;
    white-space: nowrap;
}

.plugin-table__cell--secondary {
    @apply text-gray-400;
}

.plugin-table__cell--desc {
    @apply text-gray-400;
    max-width: 28rem;
}

.plugin-table__cell--date {
    white-space: nowrap;
}

.plugin-table__cell--action {
    @apply text-right;
    white-space: nowrap;
}

.plugin-table__name-link {
    @apply font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.plugin-table__author {
    @apply ml-1.5 text-xs text-gray-500;
}

.plugin-table__stats-link {
    @apply rounded px-2 py-1 text-xs font-medium transition-colors duration-100;
    background: rgba(197, 71, 4, 0.2);
    border: 1px solid rgba(255, 108, 33, 0.45);
    color: #fb923c;
}

.plugin-table__stats-link:hover {
    background: #c54704;
    border-color: #c54704;
    color: #fff;
}

.plugin-table__empty {
    @apply px-4 py-10 text-center text-sm text-gray-400;
}

.plugin-table__count {
    @apply px-4 py-3 text-xs text-gray-400;
}

.plugin-table__scrollbar {
    @apply sticky bottom-0 overflow-x-auto overflow-y-hidden;
    scrollbar-width: thin;
    scrollbar-color: #c54704 #1a1a1a;
}

.plugin-table__scrollbar::-webkit-scrollbar {
    height: 6px;
}

.plugin-table__scrollbar::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.plugin-table__scrollbar::-webkit-scrollbar-thumb {
    background-color: #c54704;
    border-radius: 4px;
}

.plugin-table__scrollbar-spacer {
    @apply h-px;
}
</style>
