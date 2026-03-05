<script setup lang="ts">
import { show } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Plugin } from '@/types';
import { formatDate, truncateString } from '@/utils/formatting';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
        return '';
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

    <div class="overflow-x-auto rounded-lg p-2 shadow-md">
        <table class="w-full text-left text-sm text-gray-300">
            <thead class="rounded bg-orange-800 text-xs uppercase text-orange-100">
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.field"
                        scope="col"
                        class="cursor-pointer px-3 py-3 hover:underline"
                        @click="handleSort(col.field)"
                    >
                        {{ col.label }}{{ sortIndicator(col.field) }}
                    </th>
                    <th scope="col" class="px-3 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="plugin in sortedPlugins"
                    :key="plugin.id"
                    :title="plugin.warning"
                    class="border-b border-gray-700 bg-neutral-800 text-gray-300 hover:bg-neutral-700"
                >
                    <td class="px-3 py-1">
                        <a
                            class="text-orange-500 hover:underline"
                            :href="`https://runelite.net/plugin-hub/show/${plugin.name}`"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ plugin.display || plugin.name }}
                        </a>
                    </td>
                    <td class="px-3 py-1">{{ plugin.current_installs.toLocaleString('en-US') }}</td>
                    <td class="px-3 py-1">{{ plugin.all_time_high.toLocaleString('en-US') }}</td>
                    <td class="px-3 py-1">{{ truncateString(plugin.description, 100) }}</td>
                    <td class="px-3 py-1">{{ formatDate(plugin.updated_on) }}</td>
                    <td class="px-3 py-1">
                        <a :href="show.url(plugin.name)" class="text-orange-500 hover:underline">View Stats</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
