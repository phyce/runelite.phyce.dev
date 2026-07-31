<script setup lang="ts">
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
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Line } from 'vue-chartjs';
import { show as developerShow } from '@/actions/App/Http/Controllers/DeveloperController';
import { show } from '@/actions/App/Http/Controllers/PluginController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Plugin, PluginDeveloper, PluginHistoryData, RelatedPlugins } from '@/types';
import { formatChartDate, formatDate, formatNumber } from '@/utils/formatting';

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    LineElement,
    LinearScale,
    CategoryScale,
    PointElement,
    Filler,
);

defineOptions({ layout: AppLayout });

const props = defineProps<{
    plugin: Plugin;
    related: RelatedPlugins | null;
    developers: PluginDeveloper[];
}>();

const relatedEntries = computed(() => props.related?.entries ?? []);

const mainDeveloper = computed((): PluginDeveloper | null => props.developers[0] ?? null);
const contributors = computed((): PluginDeveloper[] => props.developers.slice(1));

const relatedHeading = computed(() =>
    relatedEntries.value.every((entry) => entry.reason === 'author')
        ? 'More from this developer'
        : 'Related plugins',
);

const page = usePage<{ apiUrl: string }>();
const historyData = ref<PluginHistoryData[]>([]);
const isLoading = ref(true);
const chartRef = ref();

function toDateInputValue(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const initialQuery = new URLSearchParams(page.url.split('?')[1] ?? '');
const currentRange = ref<string>(initialQuery.get('range') ?? 'custom');

const today = new Date();
const monthAgo = new Date(today);
monthAgo.setMonth(monthAgo.getMonth() - 1);

const customFrom = ref<string>(
    initialQuery.get('fromdate') ?? toDateInputValue(monthAgo),
);
const customTo = ref<string>(
    initialQuery.get('todate') ?? toDateInputValue(today),
);

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
        if (currentRange.value === 'custom') {
            params['fromdate'] = customFrom.value;
            params['todate'] = customTo.value;
        } else if (currentRange.value && currentRange.value !== 'all') {
            params['range'] = currentRange.value;
        }
        const queryString = new URLSearchParams(params).toString();
        const url = `${page.props.apiUrl}/plugin/${props.plugin.name}/history${queryString ? '?' + queryString : ''}`;
        const response = await fetch(url);
        const json = await response.json();
        historyData.value = json.data ?? [];
        const includeTime = currentRange.value === 'day';
        chartData.value = {
            labels: historyData.value.map((d) =>
                formatChartDate(d.date, includeTime),
            ),
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

function selectCustomRange(): void {
    if (currentRange.value === 'custom') {
        return;
    }
    applyCustomRange();
}

function applyCustomRange(): void {
    if (!customFrom.value || !customTo.value) {
        return;
    }
    if (customFrom.value > customTo.value) {
        [customFrom.value, customTo.value] = [customTo.value, customFrom.value];
    }
    currentRange.value = 'custom';
    router.get(
        show.url(props.plugin.name),
        { range: 'custom', fromdate: customFrom.value, todate: customTo.value },
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
    <Head :title="`${plugin.display || plugin.name} — RuneLite Plugin Stats`" />

    <div class="plugin-detail">
        <!-- Info card -->
        <div class="plugin-detail__info">
            <h3 class="plugin-detail__title">
                {{ plugin.display || plugin.name }}
            </h3>
            <p class="plugin-detail__description">{{ plugin.description }}</p>
            <p class="plugin-detail__tags" data-nosnippet>{{ plugin.tags }}</p>

            <!-- Stats grid -->
            <div class="plugin-detail__stats" data-nosnippet>
                <div class="plugin-detail__stat">
                    <div class="plugin-detail__stat-label">Author</div>
                    <div
                        class="plugin-detail__stat-value"
                        :title="plugin.author"
                    >
                        {{ plugin.author }}
                    </div>
                </div>
                <div class="plugin-detail__stat">
                    <div class="plugin-detail__stat-label">Last Update</div>
                    <div class="plugin-detail__stat-value">
                        {{ formatDate(plugin.updated_on) }}
                    </div>
                </div>
                <div class="plugin-detail__stat">
                    <div class="plugin-detail__stat-label">All-time High</div>
                    <div class="plugin-detail__stat-value">
                        {{ formatNumber(plugin.all_time_high) }}
                    </div>
                </div>
                <div class="plugin-detail__stat">
                    <div class="plugin-detail__stat-label">Released On</div>
                    <div class="plugin-detail__stat-value">
                        {{ formatDate(plugin.created_on) }}
                    </div>
                </div>
                <div class="plugin-detail__stat">
                    <div class="plugin-detail__stat-label">Active Installs</div>
                    <div class="plugin-detail__stat-value">
                        {{ formatNumber(plugin.current_installs) }}
                    </div>
                </div>
            </div>

            <!-- Links -->
            <div class="plugin-detail__links">
                <a
                    class="plugin-detail__link"
                    target="_blank"
                    :href="plugin.git_repo"
                >
                    GitHub
                </a>
                <a
                    class="plugin-detail__link"
                    target="_blank"
                    :href="`https://runelite.net/plugin-hub/show/${plugin.name}`"
                >
                    RuneLite
                </a>
                <a
                    v-if="plugin.support"
                    class="plugin-detail__link"
                    target="_blank"
                    :href="plugin.support"
                >
                    Support
                </a>
            </div>
        </div>

        <!-- Chart card -->
        <div class="plugin-detail__chart-card">
            <!-- Range buttons -->
            <div class="plugin-detail__ranges">
                <button
                    v-for="range in ranges"
                    :key="range.value"
                    class="plugin-detail__range-btn"
                    :class="
                        currentRange === range.value
                            ? 'plugin-detail__range-btn--active'
                            : 'plugin-detail__range-btn--inactive'
                    "
                    @click="setRange(range.value)"
                >
                    {{ range.label }}
                </button>

                <!-- Custom date range -->
                <div
                    class="plugin-detail__date-btn"
                    :class="
                        currentRange === 'custom'
                            ? 'plugin-detail__date-btn--active'
                            : 'plugin-detail__date-btn--inactive'
                    "
                    @click="selectCustomRange"
                >
                    <input
                        v-model="customFrom"
                        class="plugin-detail__date-input"
                        type="date"
                        :max="customTo"
                        aria-label="History start date"
                        autocomplete="off"
                        data-1p-ignore
                        data-lpignore="true"
                        data-bwignore
                        data-form-type="other"
                        @change="applyCustomRange"
                    />
                </div>
                <div
                    class="plugin-detail__date-btn"
                    :class="
                        currentRange === 'custom'
                            ? 'plugin-detail__date-btn--active'
                            : 'plugin-detail__date-btn--inactive'
                    "
                    @click="selectCustomRange"
                >
                    <input
                        v-model="customTo"
                        class="plugin-detail__date-input"
                        type="date"
                        :min="customFrom"
                        aria-label="History end date"
                        autocomplete="off"
                        data-1p-ignore
                        data-lpignore="true"
                        data-bwignore
                        data-form-type="other"
                        @change="applyCustomRange"
                    />
                </div>
            </div>

            <!-- Chart -->
            <div class="plugin-detail__chart-area">
                <div v-if="isLoading" class="plugin-detail__chart-skeleton">
                    <span
                        class="plugin-detail__chart-spinner"
                        aria-hidden="true"
                    />
                    <span class="plugin-detail__chart-loading-text"
                        >Loading chart data…</span
                    >
                </div>
                <Line
                    ref="chartRef"
                    v-else
                    :data="chartData"
                    :options="chartOptions"
                />
            </div>
        </div>

        <div v-if="relatedEntries.length" class="plugin-detail__related">
            <h2 class="plugin-detail__related-title">{{ relatedHeading }}</h2>
            <div class="plugin-detail__related-grid">
                <a
                    v-for="entry in relatedEntries"
                    :key="entry.plugin.id"
                    :href="show.url(entry.plugin.name)"
                    class="plugin-detail__related-card"
                >
                    <span class="plugin-detail__related-name">{{ entry.plugin.display || entry.plugin.name }}</span>
                    <span class="plugin-detail__related-meta">
                        {{ formatNumber(entry.plugin.current_installs) }} installs
                        <template v-if="entry.plugin.author">· by {{ entry.plugin.author }}</template>
                    </span>
                    <span v-if="entry.shared_tags?.length" class="plugin-detail__related-tags">
                        <span v-for="tag in entry.shared_tags" :key="tag" class="plugin-detail__related-tag">{{ tag }}</span>
                    </span>
                    <span v-else-if="entry.reason === 'author'" class="plugin-detail__related-reason">same developer</span>
                </a>
            </div>
        </div>

        <aside v-if="mainDeveloper" class="plugin-detail__developer">
            <h2 class="plugin-detail__developer-title">Developer</h2>

            <div class="plugin-detail__dev-head">
                <a
                    v-if="mainDeveloper.slug"
                    :href="developerShow.url(mainDeveloper.slug)"
                    class="plugin-detail__dev-name"
                >{{ mainDeveloper.name }}</a>
                <span v-else class="plugin-detail__dev-name plugin-detail__dev-name--plain">{{ mainDeveloper.name }}</span>
            </div>

            <div v-if="mainDeveloper.total_installs !== null" class="plugin-detail__dev-stats">
                <div class="plugin-detail__dev-stat" title="Peak installs yesterday across their plugins">
                    <span class="plugin-detail__dev-stat-value">{{ formatNumber(mainDeveloper.total_installs) }}</span>
                    <span class="plugin-detail__dev-stat-label">installs</span>
                </div>
                <div class="plugin-detail__dev-stat">
                    <span class="plugin-detail__dev-stat-value">{{ formatNumber(mainDeveloper.plugin_count ?? 0) }}</span>
                    <span class="plugin-detail__dev-stat-label">{{ mainDeveloper.plugin_count === 1 ? 'plugin' : 'plugins' }}</span>
                </div>
                <div v-if="mainDeveloper.contributing_since" class="plugin-detail__dev-stat">
                    <span class="plugin-detail__dev-stat-value plugin-detail__dev-stat-value--date">
                        {{ formatDate(mainDeveloper.contributing_since) }}
                    </span>
                    <span class="plugin-detail__dev-stat-label">publishing since</span>
                </div>
            </div>

            <div v-if="contributors.length" class="plugin-detail__contributors">
                <h3 class="plugin-detail__contributors-title">
                    {{ contributors.length === 1 ? 'Contributor' : 'Contributors' }}
                </h3>
                <ul class="plugin-detail__contributor-list">
                    <li v-for="contributor in contributors" :key="contributor.name" class="plugin-detail__contributor">
                        <a
                            v-if="contributor.slug"
                            :href="developerShow.url(contributor.slug)"
                            class="plugin-detail__contributor-name"
                        >{{ contributor.name }}</a>
                        <span v-else class="plugin-detail__contributor-name plugin-detail__contributor-name--plain">
                            {{ contributor.name }}
                        </span>
                        <span v-if="contributor.total_installs !== null" class="plugin-detail__contributor-meta">
                            {{ formatNumber(contributor.total_installs) }} installs ·
                            {{ formatNumber(contributor.plugin_count ?? 0) }}
                            {{ contributor.plugin_count === 1 ? 'plugin' : 'plugins' }}
                        </span>
                    </li>
                </ul>
            </div>

            <a
                v-if="mainDeveloper.slug"
                :href="developerShow.url(mainDeveloper.slug)"
                class="plugin-detail__dev-link"
            >View profile</a>
        </aside>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.plugin-detail {
    @apply flex w-full flex-col gap-3 py-4 sm:px-4;
}

.plugin-detail__info {
    @apply p-4 sm:rounded-lg sm:p-6;
    background: #141414;
    border-top: 1px solid #2a2a2a;
    border-bottom: 1px solid #2a2a2a;
}

@media (min-width: 640px) {
    .plugin-detail__info {
        border: 1px solid #2a2a2a;
    }
}

.plugin-detail__title {
    @apply mb-1 text-xl font-semibold tracking-tight text-orange-500 sm:text-2xl;
}

.plugin-detail__description {
    @apply mb-1 text-sm text-gray-200;
}

.plugin-detail__tags {
    @apply mb-3 text-xs break-words text-gray-400;
}

.plugin-detail__stats {
    @apply mb-4 grid grid-cols-2 gap-3 sm:gap-4;
}

.plugin-detail__stat {
    @apply min-w-0;
}

.plugin-detail__stat-label {
    @apply text-xs font-medium text-gray-300;
}

.plugin-detail__stat-value {
    @apply truncate text-sm text-gray-100;
}

.plugin-detail__links {
    @apply flex flex-wrap gap-2;
}

.plugin-detail__link {
    @apply rounded-full border border-orange-600 px-4 py-1 text-sm text-orange-400 transition hover:bg-orange-600 hover:text-white;
}

.plugin-detail__chart-card {
    @apply p-3 sm:rounded-lg sm:p-4;
    background: #141414;
    border-top: 1px solid #2a2a2a;
    border-bottom: 1px solid #2a2a2a;
}

@media (min-width: 640px) {
    .plugin-detail__chart-card {
        border: 1px solid #2a2a2a;
    }
}

.plugin-detail__ranges {
    @apply mb-3 flex flex-wrap gap-1.5 sm:gap-2;
}

.plugin-detail__range-btn {
    @apply rounded-full px-4 py-1 text-sm transition;
}

.plugin-detail__range-btn--active {
    @apply text-white;
    background: #c54704;
}

.plugin-detail__range-btn--inactive {
    @apply bg-neutral-800 text-gray-400;
    border: 1px solid #333;
}

.plugin-detail__range-btn--inactive:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}

.plugin-detail__date-btn {
    @apply flex cursor-pointer items-center rounded-full px-3 py-1 transition;
}

.plugin-detail__date-btn--active {
    @apply text-white;
    background: #c54704;
    border: 1px solid #c54704;
}

.plugin-detail__date-btn--inactive {
    @apply bg-neutral-800 text-gray-400;
    border: 1px solid #333;
}

.plugin-detail__date-btn--inactive:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}

.plugin-detail__date-input {
    @apply cursor-pointer bg-transparent text-sm;
    border: none;
    outline: none;
    padding: 0;
    color: inherit;
    width: 7.5rem;
    color-scheme: dark;
}

.plugin-detail__date-input::-webkit-calendar-picker-indicator {
    cursor: pointer;
}

.plugin-detail__chart-area {
    @apply relative h-56 sm:h-80;
}

.plugin-detail__chart-skeleton {
    @apply flex h-56 flex-col items-center justify-center gap-3 rounded-lg sm:h-80;
    background: #222222;
}

.plugin-detail__chart-spinner {
    @apply h-8 w-8 animate-spin rounded-full;
    border: 3px solid rgba(255, 108, 33, 0.2);
    border-top-color: #ff6c21;
}

.plugin-detail__chart-loading-text {
    @apply text-sm text-gray-400;
}

.plugin-detail__related {
    @apply flex flex-col gap-3 p-4 sm:rounded-lg sm:p-4;
    background: #141414;
    border-top: 1px solid #2a2a2a;
    border-bottom: 1px solid #2a2a2a;
}

@media (min-width: 640px) {
    .plugin-detail__related {
        border: 1px solid #2a2a2a;
    }
}

.plugin-detail__related-title {
    @apply text-sm font-semibold uppercase tracking-widest text-gray-400;
}

.plugin-detail__related-grid {
    @apply grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3;
}

.plugin-detail__related-card {
    @apply flex flex-col gap-1 rounded-lg p-3 transition-colors duration-100;
    background: #1c1c1c;
    border: 1px solid #2a2a2a;
}

.plugin-detail__related-card:hover {
    background: #3a2010;
    border-color: rgba(255, 108, 33, 0.4);
}

.plugin-detail__related-name {
    @apply truncate text-sm font-medium text-orange-400;
}

.plugin-detail__related-meta {
    @apply truncate text-xs text-gray-400;
}

.plugin-detail__related-tags {
    @apply mt-0.5 flex flex-wrap gap-1;
}

.plugin-detail__related-tag {
    @apply rounded-full px-2 py-0.5 text-xs;
    background: rgba(197, 71, 4, 0.18);
    border: 1px solid rgba(255, 108, 33, 0.3);
    color: #fb923c;
}

.plugin-detail__related-reason {
    @apply mt-0.5 text-xs text-gray-400;
}

.plugin-detail__developer {
    @apply flex flex-col gap-4 p-4 sm:rounded-lg sm:p-6;
    background: #141414;
    border-top: 1px solid #2a2a2a;
    border-bottom: 1px solid #2a2a2a;
}

@media (min-width: 640px) {
    .plugin-detail__developer {
        border: 1px solid #2a2a2a;
    }
}

.plugin-detail__developer-title {
    @apply text-sm font-semibold uppercase tracking-widest text-gray-400;
}

.plugin-detail__dev-head {
    @apply flex flex-wrap items-baseline gap-x-3 gap-y-1;
}

.plugin-detail__dev-name {
    @apply text-xl font-semibold tracking-tight text-orange-500 transition-colors duration-100 hover:text-orange-400 sm:text-2xl;
}

.plugin-detail__dev-name--plain {
    @apply text-gray-200;
}

.plugin-detail__dev-stats {
    @apply flex flex-wrap gap-x-8 gap-y-3;
}

.plugin-detail__dev-stat {
    @apply flex min-w-0 flex-col;
}

.plugin-detail__dev-stat-value {
    @apply text-lg font-bold tabular-nums text-gray-100;
}

.plugin-detail__dev-stat-value--date {
    @apply text-base font-semibold;
}

.plugin-detail__dev-stat-label {
    @apply text-xs text-gray-400;
}

.plugin-detail__contributors {
    @apply flex flex-col gap-2 border-t pt-3;
    border-color: #2a2a2a;
}

.plugin-detail__contributors-title {
    @apply text-xs font-medium uppercase tracking-wider text-gray-400;
}

.plugin-detail__contributor-list {
    @apply flex flex-col gap-1.5;
}

.plugin-detail__contributor {
    @apply flex flex-wrap items-baseline gap-x-2;
}

.plugin-detail__contributor-name {
    @apply text-sm font-medium text-orange-400 transition-colors duration-100 hover:text-orange-300;
}

.plugin-detail__contributor-name--plain {
    @apply text-gray-300;
}

.plugin-detail__contributor-meta {
    @apply text-xs tabular-nums text-gray-400;
}

.plugin-detail__dev-link {
    @apply mt-auto self-start rounded-full border border-orange-600 px-4 py-1 text-sm text-orange-400 transition hover:bg-orange-600 hover:text-white;
}

/* ── Desktop layout: developer card fills the space beside the info card ── */

@media (min-width: 1024px) {
    .plugin-detail {
        @apply grid grid-cols-2;
    }

    .plugin-detail__info {
        grid-column: 1;
        grid-row: 1;
    }

    .plugin-detail__developer {
        grid-column: 2;
        grid-row: 1;
    }

    .plugin-detail__chart-card {
        grid-column: 1 / -1;
        grid-row: 2;
    }

    .plugin-detail__related {
        grid-column: 1 / -1;
        grid-row: 3;
    }
}
</style>
