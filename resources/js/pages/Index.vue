<script setup lang="ts">
import { show } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Plugin } from '@/types';
import { formatDate, truncateString } from '@/utils/formatting';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    plugins: Plugin[];
}>();

type SortField = keyof Plugin;
type SortDirection = 'asc' | 'desc';

const sortField = ref<SortField>('name');
const sortDirection = ref<SortDirection>('asc');

function handleSort(field: SortField): void {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
}

function sortIndicator(field: SortField): string {
    if (sortField.value !== field) {
        return ' ↕';
    }
    return sortDirection.value === 'asc' ? ' ↑' : ' ↓';
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
    { field: 'name', label: 'Name' },
    { field: 'current_installs', label: 'Current Installs' },
    { field: 'all_time_high', label: 'All-Time High' },
    { field: 'description', label: 'Description' },
    { field: 'updated_on', label: 'Updated On' },
];
</script>

<template>
    <Head title="Runelite Plugin Stats" />

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
                        <span>{{ col.label }}</span>
                        <span
                            :class="sortField === col.field ? 'plugin-table__head-sort--active' : 'plugin-table__head-sort--inactive'"
                        >{{ sortIndicator(col.field) }}</span>
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
                        >
                            {{ plugin.display || plugin.name }}
                        </a>
                    </td>
                    <td class="plugin-table__cell">{{ plugin.current_installs.toLocaleString('en-US') }}</td>
                    <td class="plugin-table__cell">{{ plugin.all_time_high.toLocaleString('en-US') }}</td>
                    <td class="plugin-table__cell">{{ truncateString(plugin.description, 100) }}</td>
                    <td class="plugin-table__cell">{{ formatDate(plugin.updated_on) }}</td>
                    <td class="plugin-table__cell">
                        <a
                            :href="show.url(plugin.name)"
                            class="plugin-table__action-link"
                        >
                            View Stats
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div
        ref="stickyScrollbar"
        class="plugin-table__scrollbar"
    >
        <div :style="{ width: scrollWidth + 'px' }" class="plugin-table__scrollbar-spacer" />
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.plugin-table__wrapper {
    @apply overflow-x-auto rounded-lg shadow-md;
    scrollbar-width: none;
}

.plugin-table__wrapper::-webkit-scrollbar {
    display: none;
}

.plugin-table {
    @apply w-full text-left text-sm text-gray-300;
}

.plugin-table__head {
    @apply bg-orange-800 text-xs uppercase text-orange-100;
}

.plugin-table__head-cell {
    @apply px-3 py-3;
}

.plugin-table__head-cell--sortable {
    @apply cursor-pointer hover:underline;
}

.plugin-table__head-sort--active {
    @apply text-white;
}

.plugin-table__head-sort--inactive {
    @apply text-orange-300/50;
}

.plugin-table__row {
    @apply border-b border-gray-700 text-gray-300 hover:bg-neutral-700;
}

.plugin-table__row--even {
    @apply bg-neutral-800;
}

.plugin-table__row--odd {
    @apply bg-neutral-700/30;
}

.plugin-table__cell {
    @apply px-3 py-2;
}

.plugin-table__name-link {
    @apply text-orange-500 hover:underline;
}

.plugin-table__action-link {
    @apply whitespace-nowrap rounded bg-orange-700/30 px-2 py-0.5 text-orange-400 hover:bg-orange-700 hover:text-white;
}

.plugin-table__scrollbar {
    @apply sticky bottom-0 overflow-x-auto overflow-y-hidden;
    scrollbar-width: thin;
    scrollbar-color: #c2410c #33363a;
}

.plugin-table__scrollbar::-webkit-scrollbar {
    width: 8px;
}

.plugin-table__scrollbar::-webkit-scrollbar-track {
    background: #33363a;
}

.plugin-table__scrollbar::-webkit-scrollbar-thumb {
    background-color: #c2410c;
    border-radius: 4px;
}

.plugin-table__scrollbar-spacer {
    @apply h-px;
}
</style>
