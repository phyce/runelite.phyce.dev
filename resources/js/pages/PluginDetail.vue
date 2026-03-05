<script setup lang="ts">
import PluginHistoryController from '@/actions/App/Http/Controllers/Api/PluginHistoryController';
import { show } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Plugin, PluginHistoryData } from '@/types';
import { formatDate, formatNumber } from '@/utils/formatting';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { onMounted, onUnmounted, ref } from 'vue';
import { Line } from 'vue-chartjs';

ChartJS.register(Title, Tooltip, Legend, LineElement, LinearScale, CategoryScale, PointElement, Filler);

defineOptions({ layout: AppLayout });

const props = defineProps<{
    plugin: Plugin;
}>();

const page = usePage();
const historyData = ref<PluginHistoryData[]>([]);
const isLoading = ref(true);

const currentRange = ref<string>((page.props.ziggy as { query?: Record<string, string> })?.query?.range ?? 'all');

const chartData = ref({
    labels: [] as string[],
    datasets: [
        {
            label: 'Install Count Over Time',
            data: [] as number[],
            borderColor: 'rgb(255,108,33)',
            backgroundColor: 'rgba(197,72,4,0.3)',
            pointRadius: 4,
            pointHoverRadius: 7,
            fill: true,
        },
    ],
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: true,
    scales: {
        x: {
            ticks: { color: '#FF6C21' },
        },
        y: {
            ticks: { color: '#FF6C21' },
            beginAtZero: true,
        },
    },
};

async function fetchHistory(): Promise<void> {
    isLoading.value = true;
    try {
        const params: Record<string, string> = {};
        if (currentRange.value && currentRange.value !== 'all') {
            params['range'] = currentRange.value;
        }
        const url = PluginHistoryController.url(props.plugin.name, { query: params });
        const response = await fetch(url);
        const json = await response.json();
        historyData.value = json.data ?? [];
        chartData.value = {
            labels: historyData.value.map((d) => d.date),
            datasets: [
                {
                    label: 'Install Count Over Time',
                    data: historyData.value.map((d) => d.count),
                    borderColor: 'rgb(255,108,33)',
                    backgroundColor: 'rgba(197,72,4,0.3)',
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    fill: true,
                },
            ],
        };
    } finally {
        isLoading.value = false;
    }
}

function setRange(range: string): void {
    currentRange.value = range;
    router.get(
        show.url(props.plugin.name),
        { range },
        { preserveState: true, preserveScroll: true },
    );
    fetchHistory();
}

const ranges = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
    { value: 'month-to-date', label: 'Month to Date' },
    { value: 'all', label: 'All' },
];

let intervalId: ReturnType<typeof setInterval> | null = null;
const pageLoadTime = new Date();

onMounted(() => {
    fetchHistory();

    intervalId = setInterval(() => {
        const now = new Date();
        const elapsedMinutes = (now.getTime() - pageLoadTime.getTime()) / 60000;
        if (now.getMinutes() === 1 && elapsedMinutes > 1) {
            fetchHistory();
        }
    }, 10000);
});

onUnmounted(() => {
    if (intervalId !== null) {
        clearInterval(intervalId);
    }
});
</script>

<template>
    <Head :title="`${plugin.display || plugin.name} - Plugin Stats`" />

    <div class="flex w-full flex-col sm:flex-row">
        <!-- Left panel: plugin info -->
        <div class="w-full p-4 sm:w-1/3">
            <div class="rounded-lg border border-neutral-600 bg-neutral-700 shadow-sm">
                <div class="flex flex-col space-y-2 p-6">
                    <h3 class="text-4xl font-semibold tracking-tight whitespace-nowrap text-orange-600">
                        {{ plugin.display || plugin.name }}
                    </h3>
                    <p class="text-sm text-gray-300">{{ plugin.description }}</p>
                    <p class="break-words text-xs text-gray-400">{{ plugin.tags }}</p>
                </div>

                <div class="space-y-4 p-6">
                    <div class="flex flex-col space-y-0.5">
                        <div>
                            <div class="text-sm font-medium text-gray-500">Author</div>
                            <div>{{ plugin.author }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">Active Installs</div>
                            <div>{{ formatNumber(plugin.current_installs) }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">All-time High</div>
                            <div>{{ formatNumber(plugin.all_time_high) }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">Released On</div>
                            <div>{{ formatDate(plugin.created_on) }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">Last Update</div>
                            <div>{{ formatDate(plugin.updated_on) }}</div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-start gap-4 p-6">
                    <a class="text-orange-500 hover:underline" target="_blank" :href="plugin.git_repo">GitHub</a>
                    <a
                        class="text-orange-500 hover:underline"
                        target="_blank"
                        :href="`https://runelite.net/plugin-hub/show/${plugin.name}`"
                    >
                        Runelite
                    </a>
                    <a
                        v-if="plugin.support"
                        class="text-orange-500 hover:underline"
                        target="_blank"
                        :href="plugin.support"
                    >
                        Support
                    </a>
                </div>
            </div>
        </div>

        <!-- Right panel: chart -->
        <div class="w-full p-4 sm:w-2/3">
            <div class="flex flex-col">
                <nav class="flex flex-wrap justify-between bg-orange-800 p-3 text-white">
                    <button
                        v-for="range in ranges"
                        :key="range.value"
                        class="block px-4 hover:underline"
                        :class="{ 'font-bold underline': currentRange === range.value }"
                        @click="setRange(range.value)"
                    >
                        {{ range.label }}
                    </button>
                </nav>

                <div class="w-full">
                    <div v-if="isLoading" class="flex h-64 animate-pulse items-center justify-center rounded bg-neutral-700">
                        <span class="text-gray-400">Loading chart...</span>
                    </div>
                    <Line v-else :data="chartData" :options="chartOptions" />
                </div>
            </div>
        </div>
    </div>
</template>
