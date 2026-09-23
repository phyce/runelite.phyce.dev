<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowReactive, watch } from 'vue';
import { show as pluginShow } from '@/actions/App/Http/Controllers/PluginController';
import { index as tagsIndex, show as tagShow } from '@/actions/App/Http/Controllers/TagController';
import TagMap from '@/components/TagMap.vue';
import TagTabs from '@/components/TagTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TagDetail } from '@/types';
import { formatNumber } from '@/utils/formatting';
import { tagSlug } from '@/utils/tagMap';
import type { MapLink, MapNode, MapPlugin, MapTagInput, SizeMetric } from '@/utils/tagMap';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    nodes: MapNode[];
    links: MapLink[];
}>();

const page = usePage<{ apiUrl: string }>();

const PANEL_WIDTH = 360;
const SHEET_SHARE = 0.46;

const metric = ref<SizeMetric>('plugin_count');
const tags = computed((): MapTagInput[] => props.nodes.map(([slug, name, plugin_count, total_installs]) => ({ slug, name, plugin_count, total_installs })));
const onMap = computed(() => new Map(tags.value.map((tag) => [tag.slug, tag])));

const focused = ref<string | null>(null);
const selectedPlugin = ref<string | null>(null);
const hoveredPlugin = ref<string | null>(null);
const hoveredRelated = ref<string | null>(null);

const profiles = shallowReactive(new Map<string, TagDetail | 'error'>());
const pending = new Set<string>();

async function loadProfile(slug: string): Promise<void> {
    if (profiles.has(slug) || pending.has(slug)) return;
    pending.add(slug);
    try {
        const response = await fetch(`${page.props.apiUrl}/tags/${encodeURIComponent(slug)}`);
        const json = await response.json();
        profiles.set(slug, json.success ? json.data : 'error');
    } catch {
        profiles.set(slug, 'error');
    } finally {
        pending.delete(slug);
    }
}

const profile = computed((): TagDetail | null => {
    const loaded = focused.value ? profiles.get(focused.value) : undefined;
    return loaded && loaded !== 'error' ? loaded : null;
});

const profileFailed = computed(() => focused.value !== null && profiles.get(focused.value) === 'error');

const plugins = computed((): MapPlugin[] | null => {
    if (profileFailed.value) return [];
    if (!profile.value) return null;
    return [...profile.value.plugins]
        .sort((a, b) => b.plugin.current_installs - a.plugin.current_installs)
        .map((entry) => ({ name: entry.plugin.name, label: entry.plugin.display || entry.plugin.name, installs: entry.plugin.current_installs }));
});

const mostInstalls = computed(() => Math.max(...(plugins.value ?? []).map((plugin) => plugin.installs), 1));

const LIST_START = 40;
const listLimit = ref(LIST_START);
const listedPlugins = computed(() => plugins.value?.slice(0, listLimit.value) ?? null);
watch(focused, () => (listLimit.value = LIST_START));

const focusedInfo = computed((): { slug: string; name: string; plugins: string; installs: string } | null => {
    const tag = focused.value ? onMap.value.get(focused.value) : undefined;
    return tag ? { slug: tag.slug, name: tag.name, plugins: formatNumber(tag.plugin_count), installs: formatNumber(tag.total_installs) } : null;
});

const relatedTags = computed(() => {
    const current = profile.value;
    if (!current) return [];
    const counts = new Map<string, { slug: string; name: string; count: number }>();
    for (const entry of current.plugins) {
        for (const name of entry.other_tags ?? []) {
            const slug = tagSlug(name);
            const tag = onMap.value.get(slug);
            if (!tag || slug === current.slug) continue;
            const tally = counts.get(slug) ?? { slug, name: tag.name, count: 0 };
            tally.count++;
            counts.set(slug, tally);
        }
    }
    return [...counts.values()].sort((a, b) => b.count - a.count || a.name.localeCompare(b.name)).slice(0, 10);
});

function focusTag(slug: string): void {
    if (!onMap.value.has(slug)) return;
    focused.value = slug;
    selectedPlugin.value = null;
    hoveredRelated.value = null;
    loadProfile(slug);
}

function clearFocus(): void {
    focused.value = null;
    selectedPlugin.value = null;
    hoveredRelated.value = null;
}

const pluginList = ref<HTMLElement | null>(null);
const panelBody = ref<HTMLElement | null>(null);

function revealPlugin(name: string, atTop: boolean): void {
    nextTick(() => {
        const body = panelBody.value;
        const row = pluginList.value?.querySelector<HTMLElement>(`[data-plugin="${CSS.escape(name)}"]`);
        if (!body || !row) return;
        const bodyBox = body.getBoundingClientRect();
        const rowBox = row.getBoundingClientRect();
        if (!atTop && rowBox.top >= bodyBox.top && rowBox.bottom <= bodyBox.bottom) return;
        const offset = atTop ? 8 : (bodyBox.height - rowBox.height) / 2;
        body.scrollTo({ top: body.scrollTop + rowBox.top - bodyBox.top - offset, behavior: 'smooth' });
    });
}

function onSelectPlugin(name: string, byMouse: boolean): void {
    if (byMouse) {
        router.visit(pluginShow.url(name));
        return;
    }
    selectedPlugin.value = name;
    const position = plugins.value?.findIndex((plugin) => plugin.name === name) ?? -1;
    if (position >= listLimit.value) listLimit.value = plugins.value?.length ?? listLimit.value;
    revealPlugin(name, false);
}

function onMore(firstName: string): void {
    listLimit.value = plugins.value?.length ?? listLimit.value;
    revealPlugin(firstName, true);
}

let hoverTimer: ReturnType<typeof setTimeout> | undefined;

function onHoverTag(slug: string | null): void {
    clearTimeout(hoverTimer);
    if (slug) hoverTimer = setTimeout(() => loadProfile(slug), 120);
}

const root = ref<HTMLElement | null>(null);
const toolbar = ref<HTMLElement | null>(null);
const map = ref<InstanceType<typeof TagMap> | null>(null);
const height = ref<number | null>(null);
const isDesktop = ref(true);
const toolbarHeight = ref(64);

const insets = computed(() => ({
    top: toolbarHeight.value,
    right: focused.value && isDesktop.value ? PANEL_WIDTH + 32 : 0,
    bottom: focused.value && !isDesktop.value ? Math.round((height.value ?? 600) * SHEET_SHARE) : 0,
}));

function measure(): void {
    const el = root.value;
    if (!el) return;
    height.value = Math.max(window.innerHeight - el.getBoundingClientRect().top, 420);
    isDesktop.value = window.matchMedia('(min-width: 768px)').matches;
    toolbarHeight.value = (toolbar.value?.offsetHeight ?? 48) + 12;
}

const canFullscreen = ref(false);
const isFullscreen = ref(false);

function toggleFullscreen(): void {
    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    } else {
        root.value?.requestFullscreen().catch(() => {});
    }
}

function onFullscreenChange(): void {
    isFullscreen.value = document.fullscreenElement !== null && document.fullscreenElement === root.value;
    measure();
}

const query = ref('');
const searchOpen = ref(false);
const activeSuggestion = ref(0);

const suggestions = computed((): MapTagInput[] => {
    const needle = query.value.trim().toLowerCase();
    if (!needle) return [];
    const starts: MapTagInput[] = [];
    const contains: MapTagInput[] = [];
    for (const tag of tags.value) {
        const name = tag.name.toLowerCase();
        if (name.startsWith(needle)) {
            starts.push(tag);
        } else if (name.includes(needle)) {
            contains.push(tag);
        }
    }
    const byPlugins = (a: MapTagInput, b: MapTagInput): number => b.plugin_count - a.plugin_count;
    return [...starts.sort(byPlugins), ...contains.sort(byPlugins)].slice(0, 8);
});

watch(query, () => {
    activeSuggestion.value = 0;
    searchOpen.value = true;
});

function chooseSuggestion(tag: MapTagInput): void {
    query.value = '';
    searchOpen.value = false;
    (document.activeElement as HTMLElement | null)?.blur();
    focusTag(tag.slug);
}

function onSearchKey(event: KeyboardEvent): void {
    const count = suggestions.value.length;
    if (event.key === 'ArrowDown' && count) {
        event.preventDefault();
        activeSuggestion.value = (activeSuggestion.value + 1) % count;
    } else if (event.key === 'ArrowUp' && count) {
        event.preventDefault();
        activeSuggestion.value = (activeSuggestion.value - 1 + count) % count;
    } else if (event.key === 'Enter' && count) {
        event.preventDefault();
        chooseSuggestion(suggestions.value[activeSuggestion.value]);
    } else if (event.key === 'Escape') {
        event.stopPropagation();
        query.value = '';
        searchOpen.value = false;
        (event.target as HTMLElement).blur();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || !focused.value) return;
    if (!map.value?.collapse()) clearFocus();
}

onMounted(() => {
    measure();
    canFullscreen.value = document.fullscreenEnabled === true;
    window.addEventListener('resize', measure);
    window.addEventListener('keydown', onKeydown);
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.body.style.overflow = 'hidden';
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', measure);
    window.removeEventListener('keydown', onKeydown);
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    if (isFullscreen.value) document.exitFullscreen().catch(() => {});
    document.body.style.overflow = '';
    clearTimeout(hoverTimer);
});
</script>

<template>
    <Head title="Tag Cloud | RuneLite Plugin Stats" />

    <div ref="root" class="tag-cloud" :style="height ? { height: `${height}px` } : undefined">
        <TagMap
            ref="map"
            :nodes="tags"
            :links="links"
            :metric="metric"
            :focused="focused"
            :plugins="plugins"
            :highlighted="hoveredPlugin ?? selectedPlugin"
            :linked="hoveredRelated"
            :insets="insets"
            @select="focusTag"
            @select-plugin="onSelectPlugin"
            @more="onMore"
            @clear="clearFocus"
            @hover-tag="onHoverTag"
        />

        <div ref="toolbar" class="tag-cloud__toolbar">
            <TagTabs class="tag-cloud__tabs" />

            <div class="tag-cloud__search">
                <svg class="tag-cloud__search-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <circle cx="7" cy="7" r="4.5" />
                    <path d="m10.5 10.5 3 3" stroke-linecap="round" />
                </svg>
                <input
                    v-model="query"
                    class="tag-cloud__search-input"
                    type="search"
                    placeholder="Find a tag…"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="Find a tag"
                    role="combobox"
                    :aria-expanded="searchOpen && suggestions.length > 0"
                    aria-controls="tag-cloud-suggestions"
                    @focus="searchOpen = true"
                    @blur="searchOpen = false"
                    @keydown="onSearchKey"
                />
                <ul v-if="searchOpen && suggestions.length" id="tag-cloud-suggestions" class="tag-cloud__suggestions" role="listbox">
                    <li
                        v-for="(tag, index) in suggestions"
                        :key="tag.slug"
                        role="option"
                        class="tag-cloud__suggestion"
                        :class="{ 'tag-cloud__suggestion--active': index === activeSuggestion }"
                        :aria-selected="index === activeSuggestion"
                        @mousedown.prevent="chooseSuggestion(tag)"
                        @mouseenter="activeSuggestion = index"
                    >
                        <span class="tag-cloud__suggestion-name">{{ tag.name }}</span>
                        <span class="tag-cloud__suggestion-count">{{ formatNumber(tag.plugin_count) }} {{ tag.plugin_count === 1 ? 'plugin' : 'plugins' }}</span>
                    </li>
                </ul>
                <p v-else-if="searchOpen && query.trim()" class="tag-cloud__suggestions tag-cloud__suggestions--empty">No tags match “{{ query.trim() }}”.</p>
            </div>

            <div class="tag-cloud__metric" role="group" aria-label="Size bubbles by">
                <button
                    class="tag-cloud__metric-option"
                    :class="{ 'tag-cloud__metric-option--active': metric === 'plugin_count' }"
                    :aria-pressed="metric === 'plugin_count'"
                    title="Size bubbles by how many plugins use the tag"
                    @click="metric = 'plugin_count'"
                >
                    Plugins
                </button>
                <button
                    class="tag-cloud__metric-option"
                    :class="{ 'tag-cloud__metric-option--active': metric === 'total_installs' }"
                    :aria-pressed="metric === 'total_installs'"
                    title="Size bubbles by the combined installs of the tag's plugins"
                    @click="metric = 'total_installs'"
                >
                    Installs
                </button>
            </div>

            <a :href="tagsIndex.url()" class="tag-cloud__table-link" title="Table view">
                <svg class="tag-cloud__table-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">
                    <rect x="2" y="2.5" width="12" height="11" rx="1.5" />
                    <path d="M2 6h12M2 9.5h12M6.5 6v7.5" />
                </svg>
                <span class="tag-cloud__table-text">Table view</span>
            </a>
        </div>

        <p class="tag-cloud__caption" :class="{ 'tag-cloud__caption--hidden': focused }">
            {{ metric === 'plugin_count' ? 'Bubble size: plugins using the tag.' : 'Bubble size: combined installs.' }}
            <span class="tag-cloud__caption-extra">Tags close together are often used together.</span>
        </p>

        <div
            class="tag-cloud__zoom"
            :class="{ 'tag-cloud__zoom--hidden': focused && !isDesktop }"
            :style="{ right: `${insets.right + 12}px`, bottom: `${insets.bottom + 12}px` }"
        >
            <button class="tag-cloud__zoom-button tag-cloud__zoom-button--step" aria-label="Zoom in" @click="map?.zoomBy(1.6)">+</button>
            <button class="tag-cloud__zoom-button tag-cloud__zoom-button--step" aria-label="Zoom out" @click="map?.zoomBy(1 / 1.6)">−</button>
            <button class="tag-cloud__zoom-button" aria-label="Show the whole map" title="Show the whole map" @click="map?.resetView()">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path d="M2.5 7.5 8 3l5.5 4.5M4 6.5V13h3v-3.5h2V13h3V6.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
            <button
                v-if="canFullscreen"
                class="tag-cloud__zoom-button"
                :aria-label="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                @click="toggleFullscreen"
            >
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path
                        v-if="isFullscreen"
                        d="M6 2.5V6H2.5M10 2.5V6h3.5M13.5 10H10v3.5M6 13.5V10H2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <path v-else d="M2.5 6V2.5H6M10 2.5h3.5V6M13.5 10v3.5H10M6 13.5H2.5V10" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </div>

        <Transition name="tag-panel">
            <aside v-if="focusedInfo" class="tag-panel" :aria-label="`${focusedInfo.name} tag`">
                <header class="tag-panel__header">
                    <div class="tag-panel__heading">
                        <span class="tag-panel__eyebrow">Tag</span>
                        <h2 class="tag-panel__title">{{ focusedInfo.name }}</h2>
                    </div>
                    <button class="tag-panel__close" aria-label="Back to all tags" title="Back to all tags (Esc)" @click="clearFocus">✕</button>
                </header>

                <div ref="panelBody" class="tag-panel__body">
                    <dl class="tag-panel__stats">
                        <div class="tag-panel__stat">
                            <dt class="tag-panel__stat-label">Plugins</dt>
                            <dd class="tag-panel__stat-value">{{ focusedInfo.plugins }}</dd>
                        </div>
                        <div class="tag-panel__stat">
                            <dt class="tag-panel__stat-label">Installs</dt>
                            <dd class="tag-panel__stat-value">{{ focusedInfo.installs }}</dd>
                        </div>
                        <div class="tag-panel__stat">
                            <dt class="tag-panel__stat-label">Install rank</dt>
                            <dd class="tag-panel__stat-value">{{ profile ? `#${formatNumber(profile.ranks.installs)}` : '…' }}</dd>
                        </div>
                    </dl>

                    <a :href="tagShow.url(focusedInfo.slug)" class="tag-panel__page-link">Full tag page →</a>

                    <section v-if="relatedTags.length" class="tag-panel__section">
                        <h3 class="tag-panel__section-title">Often tagged with</h3>
                        <div class="tag-panel__chips">
                            <button
                                v-for="tag in relatedTags"
                                :key="tag.slug"
                                class="tag-panel__chip"
                                :title="`${tag.count} shared ${tag.count === 1 ? 'plugin' : 'plugins'}`"
                                @mouseenter="hoveredRelated = tag.slug"
                                @mouseleave="hoveredRelated = null"
                                @focus="hoveredRelated = tag.slug"
                                @blur="hoveredRelated = null"
                                @click="focusTag(tag.slug)"
                            >
                                {{ tag.name }}
                                <span class="tag-panel__chip-count">{{ tag.count }}</span>
                            </button>
                        </div>
                    </section>

                    <section class="tag-panel__section">
                        <h3 class="tag-panel__section-title">Plugins by installs</h3>
                        <p v-if="profileFailed" class="tag-panel__error">
                            Couldn't load this tag's plugins.
                            <a :href="tagShow.url(focusedInfo.slug)" class="tag-panel__error-link">Open the tag page</a>
                        </p>
                        <ol v-else-if="listedPlugins" ref="pluginList" class="tag-panel__plugins">
                            <li
                                v-for="(plugin, index) in listedPlugins"
                                :key="plugin.name"
                                :data-plugin="plugin.name"
                                class="tag-panel__plugin"
                                :class="{ 'tag-panel__plugin--active': plugin.name === selectedPlugin || plugin.name === hoveredPlugin }"
                                @mouseenter="hoveredPlugin = plugin.name"
                                @mouseleave="hoveredPlugin = null"
                            >
                                <a :href="pluginShow.url(plugin.name)" class="tag-panel__plugin-link">
                                    <span class="tag-panel__plugin-rank">{{ index + 1 }}</span>
                                    <span class="tag-panel__plugin-name">{{ plugin.label }}</span>
                                    <span class="tag-panel__plugin-installs">{{ formatNumber(plugin.installs) }}</span>
                                    <span class="tag-panel__plugin-bar" :style="{ width: `${(plugin.installs / mostInstalls) * 100}%` }" aria-hidden="true" />
                                </a>
                            </li>
                        </ol>
                        <button v-if="plugins && plugins.length > listLimit" class="tag-panel__more" @click="listLimit = plugins.length">
                            Show all {{ formatNumber(plugins.length) }} plugins
                        </button>
                        <ol v-if="!profileFailed && !listedPlugins" class="tag-panel__plugins" aria-busy="true">
                            <li v-for="row in 6" :key="row" class="tag-panel__skeleton" />
                        </ol>
                    </section>
                </div>
            </aside>
        </Transition>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.tag-cloud {
    @apply relative w-full overflow-hidden;
    height: calc(100dvh - 8rem);
    background: #121212;
}

.tag-cloud__toolbar {
    @apply pointer-events-none absolute inset-x-0 top-0 z-20 flex items-center gap-2 px-3 pt-2 pb-6 sm:px-4 sm:pt-3;
    background: linear-gradient(to bottom, rgba(18, 18, 18, 0.92) 0%, rgba(18, 18, 18, 0.6) 55%, transparent 100%);
}

.tag-cloud__toolbar > * {
    pointer-events: auto;
}

.tag-cloud__tabs {
    @apply hidden md:flex;
}

.tag-cloud__search {
    @apply relative min-w-0 flex-1 md:ml-auto md:max-w-xs md:flex-none md:basis-64;
}

.tag-cloud__search-icon {
    @apply pointer-events-none absolute top-1/2 left-3 z-10 h-4 w-4 -translate-y-1/2 text-gray-500;
}

.tag-cloud__search-input {
    @apply w-full rounded-full border border-neutral-700/80 bg-neutral-900/90 py-1.5 pr-3 pl-9 text-sm text-white shadow-lg placeholder:text-neutral-500 focus:border-orange-600/70 focus:ring-1 focus:ring-orange-600/50 focus:outline-none;
}

.tag-cloud__suggestions {
    @apply absolute inset-x-0 top-full mt-1.5 overflow-hidden rounded-xl border border-neutral-700 py-1 shadow-2xl;
    background: rgba(13, 13, 13, 0.97);
}

.tag-cloud__suggestions--empty {
    @apply px-3 py-2 text-sm text-gray-400;
}

.tag-cloud__suggestion {
    @apply flex cursor-pointer items-baseline gap-2 px-3 py-1.5 text-sm;
}

.tag-cloud__suggestion--active {
    background: #3a2010;
}

.tag-cloud__suggestion-name {
    @apply min-w-0 flex-1 truncate font-medium text-gray-100;
}

.tag-cloud__suggestion-count {
    @apply flex-shrink-0 text-xs tabular-nums text-gray-500;
}


.tag-cloud__metric {
    @apply inline-flex flex-shrink-0 rounded-full border border-neutral-700/80 p-0.5 text-xs font-medium shadow-lg;
    background: rgba(17, 17, 17, 0.95);
}

.tag-cloud__metric-option {
    @apply rounded-full px-2.5 py-1 text-gray-400 transition-colors duration-150 hover:text-gray-200 sm:px-3;
}

.tag-cloud__metric-option--active {
    background: #c54704;
    color: #fff;
}

.tag-cloud__metric-option--active:hover {
    color: #fff;
}

.tag-cloud__table-link {
    @apply inline-flex flex-shrink-0 items-center gap-1.5 rounded-full border border-neutral-700/80 bg-neutral-900/90 px-2.5 py-1.5 text-sm font-medium text-gray-400 no-underline shadow-lg transition-colors duration-100 sm:px-3.5;
}

.tag-cloud__table-link:hover {
    background: #3a2010;
    color: #ff6c21;
    border-color: rgba(255, 108, 33, 0.3);
}

.tag-cloud__table-icon {
    @apply h-4 w-4;
}

.tag-cloud__table-text {
    @apply hidden lg:inline;
}

.tag-cloud__caption {
    @apply pointer-events-none absolute bottom-4 left-4 z-10 hidden rounded-full border border-neutral-800 px-3 py-1 text-xs text-gray-400 shadow-lg transition-opacity duration-200 md:block;
    background: rgba(17, 17, 17, 0.88);
}

.tag-cloud__caption--hidden {
    @apply opacity-0;
}

.tag-cloud__caption-extra {
    @apply text-gray-500;
}

.tag-cloud__zoom {
    @apply absolute z-10 flex flex-col overflow-hidden rounded-xl border border-neutral-700/80 shadow-lg transition-[right,bottom,opacity] duration-300;
    background: rgba(17, 17, 17, 0.9);
}

.tag-cloud__zoom--hidden {
    @apply pointer-events-none opacity-0;
}

.tag-cloud__zoom-button {
    @apply flex h-9 w-9 items-center justify-center text-lg leading-none text-gray-300 transition-colors duration-100;
}

.tag-cloud__zoom-button + .tag-cloud__zoom-button {
    border-top: 1px solid #262626;
}

.tag-cloud__zoom-button--step {
    @apply hidden md:flex;
}

.tag-cloud__zoom-button:hover {
    background: #3a2010;
    color: #ff6c21;
}

.tag-cloud__zoom-button svg {
    @apply h-4 w-4;
}

.tag-panel {
    @apply absolute inset-x-0 bottom-0 z-30 flex flex-col overflow-hidden rounded-t-2xl border-t border-neutral-700/80 md:inset-x-auto md:top-16 md:right-4 md:bottom-4 md:rounded-2xl md:border;
    height: 46%;
    background: rgba(17, 17, 17, 0.97);
    box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.5);
}

@media (min-width: 768px) {
    .tag-panel {
        width: 360px;
        height: auto;
    }
}

.tag-panel__header {
    @apply flex items-start justify-between gap-3 border-b border-neutral-800 px-4 pt-3 pb-2.5;
}

.tag-panel__heading {
    @apply flex min-w-0 flex-col;
}

.tag-panel__eyebrow {
    @apply text-[10px] font-semibold tracking-widest text-orange-500/80 uppercase;
}

.tag-panel__title {
    @apply truncate text-xl font-bold text-white capitalize;
}

.tag-panel__close {
    @apply flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-sm text-gray-300 transition-colors duration-100;
    background: rgba(197, 71, 4, 0.25);
}

.tag-panel__close:hover {
    background: #c54704;
    color: #fff;
}

.tag-panel__body {
    @apply flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto px-4 py-3;
    scrollbar-width: thin;
    scrollbar-color: #c54704 transparent;
}

.tag-panel__stats {
    @apply grid grid-cols-3 gap-2;
}

.tag-panel__stat {
    @apply flex flex-col rounded-lg border border-neutral-800 bg-neutral-900 px-2.5 py-1.5;
}

.tag-panel__stat-label {
    @apply text-[11px] text-gray-500;
}

.tag-panel__stat-value {
    @apply text-sm font-semibold text-gray-100;
}

.tag-panel__page-link {
    @apply self-start text-sm font-medium text-orange-400 hover:text-orange-300;
}

.tag-panel__section {
    @apply flex flex-col gap-1.5;
}

.tag-panel__section-title {
    @apply text-xs font-semibold tracking-wider text-gray-500 uppercase;
}

.tag-panel__chips {
    @apply flex flex-wrap gap-1.5;
}

.tag-panel__chip {
    @apply inline-flex items-center gap-1.5 rounded-full border border-neutral-700 bg-neutral-800 px-2.5 py-1 text-xs font-medium text-gray-200 transition-colors duration-100;
}

.tag-panel__chip:hover {
    background: #3a2010;
    border-color: rgba(255, 108, 33, 0.45);
    color: #ff6c21;
}

.tag-panel__chip-count {
    @apply tabular-nums text-gray-500;
}

.tag-panel__plugins {
    @apply flex flex-col;
}

.tag-panel__plugin {
    @apply rounded-md transition-colors duration-100;
}

.tag-panel__plugin--active {
    background: #3a2010;
}

.tag-panel__plugin-link {
    @apply relative flex items-baseline gap-2 px-2 py-1.5 text-sm no-underline;
}

.tag-panel__plugin-rank {
    @apply w-6 flex-shrink-0 text-right text-xs tabular-nums text-gray-600;
}

.tag-panel__plugin-name {
    @apply min-w-0 flex-1 truncate text-gray-200;
}

.tag-panel__plugin--active .tag-panel__plugin-name {
    color: #ff6c21;
}

.tag-panel__plugin-installs {
    @apply flex-shrink-0 text-xs tabular-nums text-gray-400;
}

.tag-panel__plugin-bar {
    @apply absolute bottom-0.5 left-10 h-0.5 rounded-full;
    max-width: calc(100% - 2.75rem);
    background: rgba(255, 108, 33, 0.35);
}

.tag-panel__plugin--active .tag-panel__plugin-bar {
    background: #ff6c21;
}

.tag-panel__more {
    @apply self-start rounded-full border border-neutral-700 px-3 py-1 text-xs font-medium text-gray-300 transition-colors duration-100;
}

.tag-panel__more:hover {
    background: #3a2010;
    border-color: rgba(255, 108, 33, 0.45);
    color: #ff6c21;
}

.tag-panel__skeleton {
    @apply my-1 h-6 animate-pulse rounded-md bg-neutral-800;
}

.tag-panel__error {
    @apply text-sm text-gray-400;
}

.tag-panel__error-link {
    @apply text-orange-400 hover:text-orange-300;
}

.tag-panel-enter-active,
.tag-panel-leave-active {
    transition:
        transform 320ms cubic-bezier(0.22, 1, 0.36, 1),
        opacity 220ms ease;
}

.tag-panel-enter-from,
.tag-panel-leave-to {
    transform: translateY(100%);
    opacity: 0;
}

@media (min-width: 768px) {
    .tag-panel-enter-from,
    .tag-panel-leave-to {
        transform: translateX(calc(100% + 1rem));
    }
}
</style>
