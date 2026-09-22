<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { show as pluginShow } from '@/actions/App/Http/Controllers/PluginController';
import { index as tagsIndex, show as tagShow } from '@/actions/App/Http/Controllers/TagController';
import TagSphere from '@/components/TagSphere.vue';
import TagTabs from '@/components/TagTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TagCloudTag } from '@/types';
import { formatNumber } from '@/utils/formatting';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    allTags: TagCloudTag[];
}>();

const page = usePage<{ apiUrl: string }>();

interface SphereItem {
    id: string;
    name: string;
    weight: number;
}

const mode = ref<'tags' | 'plugins'>('tags');
const selectedTag = ref<{ slug: string; name: string } | null>(null);
const pluginItems = ref<SphereItem[]>([]);
const loadingTag = ref<string | null>(null);

const tagItems = computed((): SphereItem[] =>
    props.allTags.map((tag) => ({ id: tag.slug, name: tag.name, weight: tag.total_installs })),
);

const sphereItems = computed((): SphereItem[] => (mode.value === 'plugins' ? pluginItems.value : tagItems.value));

const caption = computed(() =>
    mode.value === 'plugins'
        ? `${formatNumber(pluginItems.value.length)} plugins, sized by active installs. Click one for its stats.`
        : `${formatNumber(props.allTags.length)} tags, sized by active installs. Click one to see its plugins.`,
);

/** A tag opens its plugins in the sphere; when the API cannot supply them, its own page does instead. */
async function onSelect(id: string): Promise<void> {
    if (mode.value === 'plugins') {
        router.visit(pluginShow.url(id));
        return;
    }

    if (loadingTag.value) return;
    loadingTag.value = id;

    try {
        const response = await fetch(`${page.props.apiUrl}/tags/${encodeURIComponent(id)}`);
        const json = await response.json();
        const entries: { plugin: { name: string; display?: string; current_installs: number } }[] =
            json.success ? (json.data?.plugins ?? []) : [];

        if (!entries.length) {
            router.visit(tagShow.url(id));
            return;
        }

        pluginItems.value = entries.map((entry) => ({
            id: entry.plugin.name,
            name: entry.plugin.display || entry.plugin.name,
            weight: entry.plugin.current_installs,
        }));
        selectedTag.value = { slug: id, name: json.data.name ?? id };
        mode.value = 'plugins';
    } catch {
        router.visit(tagShow.url(id));
    } finally {
        loadingTag.value = null;
    }
}

function backToTags(): void {
    mode.value = 'tags';
    selectedTag.value = null;
    pluginItems.value = [];
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && mode.value === 'plugins') {
        backToTags();
    }
}

onMounted(() => {
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Head title="Tag Cloud | RuneLite Plugin Stats" />

    <div class="tag-cloud">
        <div class="tag-cloud__topbar">
            <TagTabs />
            <div class="tag-cloud__actions">
                <a :href="tagsIndex.url()" class="tag-cloud__table-link">
                    <svg class="tag-cloud__table-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">
                        <rect x="2" y="2.5" width="12" height="11" rx="1.5" />
                        <path d="M2 6h12M2 9.5h12M6.5 6v7.5" />
                    </svg>
                    Table view
                </a>
            </div>
        </div>

        <div v-if="mode === 'plugins' && selectedTag" class="tag-cloud__focus">
            <span class="tag-cloud__focus-label">{{ selectedTag.name }}</span>
            <button class="tag-cloud__focus-back" title="Back to all tags (Esc)" @click="backToTags">✕</button>
        </div>
        <div v-else-if="loadingTag" class="tag-cloud__focus">
            <span class="tag-cloud__focus-label">Loading {{ loadingTag }}…</span>
        </div>

        <TagSphere :items="sphereItems" @select="onSelect" />

        <span class="tag-cloud__caption">{{ caption }}</span>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.tag-cloud {
    @apply relative w-full;
}

.tag-cloud__topbar {
    @apply absolute inset-x-0 top-0 z-10 flex items-start justify-between gap-3 px-3 pt-2 sm:px-4 sm:pt-3;
    pointer-events: none;
}

.tag-cloud__topbar > * {
    pointer-events: auto;
}

.tag-cloud__actions {
    @apply flex gap-1.5;
}

.tag-cloud__table-link {
    @apply inline-flex items-center gap-1.5 rounded-full bg-neutral-800 px-3 py-1 text-sm font-medium text-gray-400 no-underline transition-colors duration-100 sm:px-4;
    border: 1px solid #333;
}

.tag-cloud__table-link:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}

.tag-cloud__table-icon {
    @apply h-4 w-4;
}

.tag-cloud__focus {
    @apply absolute top-14 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2 rounded-full bg-neutral-800/90 py-1 pr-1.5 pl-4 text-sm font-medium text-orange-400;
    border: 1px solid rgba(255, 108, 33, 0.4);
}

.tag-cloud__focus-label {
    @apply capitalize;
}

.tag-cloud__focus-back {
    @apply flex h-6 w-6 items-center justify-center rounded-full text-xs text-gray-300 transition-colors duration-100;
    background: rgba(197, 71, 4, 0.35);
}

.tag-cloud__focus-back:hover {
    background: #c54704;
    color: #fff;
}

.tag-cloud__caption {
    @apply absolute bottom-2 left-3 z-10 text-xs text-gray-500 sm:left-4;
    pointer-events: none;
}
</style>
