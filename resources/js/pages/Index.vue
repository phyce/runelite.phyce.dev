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

    <div ref="tableWrapper" class="scrollbar-none overflow-x-auto rounded-lg shadow-md">
        <table class="w-full text-left text-sm text-gray-300">
            <thead class="bg-orange-800 text-xs uppercase text-orange-100">
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.field"
                        scope="col"
                        class="cursor-pointer px-3 py-3 hover:underline"
                        @click="handleSort(col.field)"
                    >
                        <span>{{ col.label }}</span>
                        <span
                            :class="sortField === col.field ? 'text-white' : 'text-orange-300/50'"
                        >{{ sortIndicator(col.field) }}</span>
                    </th>
                    <th scope="col" class="px-3 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(plugin, index) in sortedPlugins"
                    :key="plugin.id"
                    :title="plugin.warning"
                    class="border-b border-gray-700 text-gray-300 hover:bg-neutral-700"
                    :class="index % 2 === 0 ? 'bg-neutral-800' : 'bg-neutral-700/30'"
                >
                    <td class="px-3 py-2">
                        <a
                            class="text-orange-500 hover:underline"
                            :href="`https://runelite.net/plugin-hub/show/${plugin.name}`"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ plugin.display || plugin.name }}
                        </a>
                    </td>
                    <td class="px-3 py-2">{{ plugin.current_installs.toLocaleString('en-US') }}</td>
                    <td class="px-3 py-2">{{ plugin.all_time_high.toLocaleString('en-US') }}</td>
                    <td class="px-3 py-2">{{ truncateString(plugin.description, 100) }}</td>
                    <td class="px-3 py-2">{{ formatDate(plugin.updated_on) }}</td>
                    <td class="px-3 py-2">
                        <a
                            :href="show.url(plugin.name)"
                            class="whitespace-nowrap rounded bg-orange-700/30 px-2 py-0.5 text-orange-400 hover:bg-orange-700 hover:text-white"
                        >
                            View Stats
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Sticky scrollbar pinned to bottom of viewport -->
    <div
        ref="stickyScrollbar"
        class="scrollbar-custom sticky bottom-0 overflow-x-auto overflow-y-hidden"
    >
        <div :style="{ width: scrollWidth + 'px' }" class="h-px" />
    </div>
</template>
