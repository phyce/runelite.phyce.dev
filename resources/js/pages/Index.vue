<script setup lang="ts">
import { show } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Plugin } from '@/types';
import { formatDate, truncateString } from '@/utils/formatting';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    plugins: Plugin[];
}>();

const page = usePage<{ appUrl: string }>();
const appUrl = page.props.appUrl;

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
    <Head title="RuneLite Plugin Stats">
        <meta name="description" content="Browse and compare install statistics for all RuneLite plugins." />
        <meta property="og:title" content="RuneLite Plugin Stats" />
        <meta property="og:description" content="Browse and compare install statistics for all RuneLite plugins." />
        <meta property="og:type" content="website" />
        <link rel="canonical" :href="appUrl" />
    </Head>

    <div ref="tableWrapper" class="plugin-table__wrapper">
        <table class="plugin-table">
            <thead class="plugin-table__head">
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.field"
                        scope="col"
                        class="plugin-table__head-cell plugin-table__head-cell--sortable"
                        @click="handleSort(col.field)"
                    >
                        {{ col.label }}
                        <span :class="sortField === col.field ? 'plugin-table__sort--active' : 'plugin-table__sort--inactive'">{{ sortIndicator(col.field) }}</span>
                    </th>
                    <th scope="col" class="plugin-table__head-cell"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(plugin, index) in sortedPlugins"
                    :key="plugin.id"
                    :title="plugin.warning"
                    class="plugin-table__row"
                    :class="index % 2 === 0 ? 'plugin-table__row--even' : 'plugin-table__row--odd'"
                >
                    <td class="plugin-table__cell">
                        <a
                            class="plugin-table__name-link"
                            :href="`https://runelite.net/plugin-hub/show/${plugin.name}`"
                            target="_blank"
                            rel="noopener noreferrer"
                        >{{ plugin.display || plugin.name }}</a>
                        <span v-if="plugin.author" class="plugin-table__author">by {{ plugin.author }}</span>
                    </td>
                    <td class="plugin-table__cell plugin-table__cell--num">{{ plugin.current_installs.toLocaleString('en-US') }}</td>
                    <td class="plugin-table__cell plugin-table__cell--num plugin-table__cell--secondary">{{ plugin.all_time_high.toLocaleString('en-US') }}</td>
                    <td class="plugin-table__cell plugin-table__cell--desc">{{ truncateString(plugin.description, 100) }}</td>
                    <td class="plugin-table__cell plugin-table__cell--secondary plugin-table__cell--date">{{ formatDate(plugin.updated_on) }}</td>
                    <td class="plugin-table__cell plugin-table__cell--action">
                        <a :href="show.url(plugin.name)" class="plugin-table__stats-link">Stats</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

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

.plugin-table {
    @apply w-full text-left text-sm;
}

.plugin-table__head {
    background: #0d0d0d;
    border-bottom: 1px solid #2a2a2a;
}

.plugin-table__head-cell {
    @apply px-4 py-3 text-xs font-medium uppercase tracking-wider text-gray-500;
    white-space: nowrap;
}

.plugin-table__head-cell--sortable {
    @apply cursor-pointer select-none;
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

.plugin-table__row {
    @apply transition-colors duration-75;
    border-bottom: 1px solid #1e1e1e;
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
