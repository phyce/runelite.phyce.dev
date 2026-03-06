<script setup lang="ts">
import { show } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Plugin, PluginHistoryData } from '@/types';
import { formatChartDate, formatDate, formatNumber } from '@/utils/formatting';
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

const page = usePage<{ apiUrl: string }>();
const historyData = ref<PluginHistoryData[]>([]);
const isLoading = ref(true);
const chartRef = ref();

const currentRange = ref<string>((page.props.ziggy as { query?: Record<string, string> })?.query?.range ?? 'all');

const chartData = ref({
    labels: [] as string[],
    datasets: [
        {
            label: 'Install Count Over Time',
            data: [] as number[],
            borderColor: 'rgb(255,108,33)',
            backgroundColor: 'rgba(197,72,4,0.3)',
            tension: 0.3,
            pointRadius: 3,
            pointHoverRadius: 6,
            fill: true,
        },
    ],
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: 'rgba(30, 30, 30, 0.9)',
            titleColor: '#FF6C21',
            bodyColor: '#d1d5db',
            borderColor: 'rgba(255, 108, 33, 0.3)',
            borderWidth: 1,
        },
    },
    scales: {
        x: {
            ticks: {
                color: '#FF6C21',
                maxTicksLimit: 8,
            },
            grid: {
                color: 'rgba(255, 108, 33, 0.08)',
            },
            border: {
                color: 'rgba(255, 108, 33, 0.2)',
            },
        },
        y: {
            ticks: { color: '#FF6C21' },
            grid: {
                color: 'rgba(255, 108, 33, 0.08)',
            },
            border: {
                color: 'rgba(255, 108, 33, 0.2)',
            },
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
        const queryString = new URLSearchParams(params).toString();
        const url = `${page.props.apiUrl}/plugin/${props.plugin.name}/history${queryString ? '?' + queryString : ''}`;
        const response = await fetch(url);
        const json = await response.json();
        historyData.value = json.data ?? [];
        const includeTime = currentRange.value === 'day';
        chartData.value = {
            labels: historyData.value.map((d) => formatChartDate(d.date, includeTime)),
            datasets: [
                {
                    label: 'Install Count Over Time',
                    data: historyData.value.map((d) => d.count),
                    borderColor: 'rgb(255,108,33)',
                    backgroundColor: 'rgba(197,72,4,0.3)',
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 6,
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

function onWindowResize(): void {
    chartRef.value?.chart?.resize();
}

onMounted(() => {
    fetchHistory();
    window.addEventListener('resize', onWindowResize);

    intervalId = setInterval(() => {
        const now = new Date();
        const elapsedMinutes = (now.getTime() - pageLoadTime.getTime()) / 60000;
        if (now.getMinutes() === 1 && elapsedMinutes > 1) {
            fetchHistory();
        }
    }, 10000);
});

onUnmounted(() => {
    window.removeEventListener('resize', onWindowResize);
    if (intervalId !== null) {
        clearInterval(intervalId);
    }
});
</script>

<template>
    <Head :title="`${plugin.display || plugin.name} - Plugin Stats`" />

    <div class="flex w-full flex-col gap-4 p-4">
        <!-- Info card -->
        <div class="rounded-lg bg-neutral-700 p-6 lg:w-1/2">
            <h3 class="mb-1 text-2xl font-semibold tracking-tight text-orange-500">
                {{ plugin.display || plugin.name }}
            </h3>
            <p class="mb-1 text-sm text-gray-200">{{ plugin.description }}</p>
            <p class="mb-4 break-words text-xs text-gray-400">{{ plugin.tags }}</p>

            <!-- Stats grid -->
            <div class="mb-6 grid grid-cols-2 gap-4">
                <div class="min-w-0">
                    <div class="text-xs font-medium text-gray-400">Author</div>
                    <div class="truncate text-sm text-gray-200" :title="plugin.author">{{ plugin.author }}</div>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium text-gray-400">Last Update</div>
                    <div class="truncate text-sm text-gray-200">{{ formatDate(plugin.updated_on) }}</div>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium text-gray-400">All-time High</div>
                    <div class="truncate text-sm text-gray-200">{{ formatNumber(plugin.all_time_high) }}</div>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium text-gray-400">Released On</div>
                    <div class="truncate text-sm text-gray-200">{{ formatDate(plugin.created_on) }}</div>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium text-gray-400">Active Installs</div>
                    <div class="truncate text-sm text-gray-200">{{ formatNumber(plugin.current_installs) }}</div>
                </div>
            </div>

            <!-- Links -->
            <div class="flex flex-wrap gap-2">
                <a
                    class="rounded-full border border-orange-600 px-4 py-1 text-sm text-orange-400 transition hover:bg-orange-600 hover:text-white"
                    target="_blank"
                    :href="plugin.git_repo"
                >
                    GitHub
                </a>
                <a
                    class="rounded-full border border-orange-600 px-4 py-1 text-sm text-orange-400 transition hover:bg-orange-600 hover:text-white"
                    target="_blank"
                    :href="`https://runelite.net/plugin-hub/show/${plugin.name}`"
                >
                    RuneLite
                </a>
                <a
                    v-if="plugin.support"
                    class="rounded-full border border-orange-600 px-4 py-1 text-sm text-orange-400 transition hover:bg-orange-600 hover:text-white"
                    target="_blank"
                    :href="plugin.support"
                >
                    Support
                </a>
            </div>
        </div>

        <!-- Chart card -->
        <div class="rounded-lg bg-neutral-700 p-4">
            <!-- Range buttons -->
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="range in ranges"
                    :key="range.value"
                    class="rounded-full px-4 py-1 text-sm transition"
                    :class="
                        currentRange === range.value
                            ? 'bg-orange-700 text-white'
                            : 'bg-neutral-600 text-gray-300 hover:bg-orange-800 hover:text-white'
                    "
                    @click="setRange(range.value)"
                >
                    {{ range.label }}
                </button>
            </div>

            <!-- Chart -->
            <div class="relative h-80">
                <div
                    v-if="isLoading"
                    class="h-80 animate-pulse rounded-lg bg-neutral-600"
                />
                <Line ref="chartRef" v-else :data="chartData" :options="chartOptions" />
            </div>
        </div>
    </div>
</template>
