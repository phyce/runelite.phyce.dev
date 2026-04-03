<script setup lang="ts">
import { show } from '@/actions/App/Http/Controllers/PluginController';
import { scoreSearchResult } from '@/utils/formatting';
import type { Plugin } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const page = usePage<{ plugins?: Plugin[]; apiUrl: string }>();
const plugins = computed(() => page.props.plugins ?? []);

const searchInput = ref('');
const searchVisible = ref(false);
const menuOpen = ref(false);
const searchInputRef = ref<HTMLInputElement | null>(null);

const links = [
    { href: '/', label: 'All Plugins', exact: true },
    { href: '/top', label: 'Top', exact: true },
    { href: '/top/absolute', label: 'Most Popular', exact: false },
    { href: '/top/relative', label: 'Fastest Growing', exact: false },
];

const currentPath = computed(() => new URL(page.url, 'http://x').pathname);

function isActive(href: string, exact: boolean): boolean {
    if (exact) return currentPath.value === href;
    return currentPath.value.startsWith(href);
}

const searchResults = computed(() => {
    const q = searchInput.value.trim();
    if (!q) return [];
    return plugins.value
        .map((p) => ({ ...p, score: scoreSearchResult(p, q) }))
        .filter((p) => p.score > 0)
        .sort((a, b) => b.score - a.score);
});

// Lock body scroll when drawer is open
watch(menuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

async function navigateRandom(): Promise<void> {
    const response = await fetch(`${page.props.apiUrl}/plugins/random`);
    const json = await response.json();
    if (json.success && json.data?.name) {
        router.visit(show.url(json.data.name));
    }
}

function navigateToPlugin(name: string): void {
    router.visit(show.url(name));
    searchInput.value = '';
    searchVisible.value = false;
    menuOpen.value = false;
}

function onBlur(): void {
    setTimeout(() => {
        searchVisible.value = false;
    }, 200);
}

function closeMenu(): void {
    menuOpen.value = false;
}

async function openMenuForSearch(): Promise<void> {
    menuOpen.value = true;
    await nextTick();
    searchInputRef.value?.focus();
}
</script>

<template>
    <header class="app-header">
        <div class="app-header__inner">
            <a class="app-header__brand" href="/" title="RuneLite Plugin Stats">
                <img
                    class="app-header__logo"
                    width="44"
                    height="44"
                    src="/img/runelite.png"
                    alt="RuneLite logo"
                />
                <div class="app-header__title-group">
                    <span class="app-header__title">RuneLite Plugin Stats</span>
                    <span class="app-header__version">v0.3.1</span>
                </div>
            </a>

            <div class="app-header__controls">
                <button class="app-header__random-btn" title="Jump to a random plugin" @click="navigateRandom">
                    <svg class="app-header__random-icon" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" aria-hidden="true">
                        <path fill="currentColor" d="M274.9 34.3c-28.1-28.1-73.7-28.1-101.8 0L34.3 173.1c-28.1 28.1-28.1 73.7 0 101.8L173.1 413.7c28.1 28.1 73.7 28.1 101.8 0L413.7 274.9c28.1-28.1 28.1-73.7 0-101.8L274.9 34.3zM200 224a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zM96 200a24 24 0 1 1 0 48 24 24 0 1 1 0-48zM224 376a24 24 0 1 1 0-48 24 24 0 1 1 0 48zM352 200a24 24 0 1 1 0 48 24 24 0 1 1 0-48zM224 120a24 24 0 1 1 0-48 24 24 0 1 1 0 48zm96 328c0 35.3 28.7 64 64 64H576c35.3 0 64-28.7 64-64V256c0-35.3-28.7-64-64-64H461.7c11.6 36 3.1 77-25.4 105.5L320 413.8V448zM480 328a24 24 0 1 1 0 48 24 24 0 1 1 0-48z" />
                    </svg>
                    <span>Random</span>
                </button>

                <div class="app-header__search">
                    <svg class="app-header__search-icon" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true">
                        <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" />
                    </svg>
                    <input
                        v-model="searchInput"
                        class="app-header__search-input"
                        placeholder="Search plugins by name, author, tags..."
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        @focus="searchVisible = true"
                        @blur="onBlur"
                    />
                    <div v-if="searchVisible && searchResults.length > 0" class="app-header__search-results">
                        <a
                            v-for="plugin in searchResults"
                            :key="plugin.id"
                            :href="show.url(plugin.name)"
                            class="app-header__search-result"
                            @click.prevent="navigateToPlugin(plugin.name)"
                        >
                            <span class="app-header__search-result-name">{{ plugin.display || plugin.name }}</span>
                            <span v-if="plugin.author" class="app-header__search-result-author">{{ plugin.author }}</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="app-header__mobile-actions">
                <div class="app-header__mobile-icon-row">
                    <button class="app-header__mobile-icon-btn" title="Search plugins" aria-label="Search plugins" @click="openMenuForSearch">
                        <svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true">
                            <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" />
                        </svg>
                    </button>

                    <button class="app-header__mobile-icon-btn" :aria-label="menuOpen ? 'Close menu' : 'Open menu'" @click="menuOpen = !menuOpen">
                        <svg v-if="!menuOpen" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <button class="app-header__random-btn app-header__random-btn--mobile" title="Jump to a random plugin" @click="navigateRandom">
                    <svg class="app-header__random-icon" width="14" height="14" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" aria-hidden="true">
                        <path fill="currentColor" d="M274.9 34.3c-28.1-28.1-73.7-28.1-101.8 0L34.3 173.1c-28.1 28.1-28.1 73.7 0 101.8L173.1 413.7c28.1 28.1 73.7 28.1 101.8 0L413.7 274.9c28.1-28.1 28.1-73.7 0-101.8L274.9 34.3zM200 224a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zM96 200a24 24 0 1 1 0 48 24 24 0 1 1 0-48zM224 376a24 24 0 1 1 0-48 24 24 0 1 1 0 48zM352 200a24 24 0 1 1 0 48 24 24 0 1 1 0-48zM224 120a24 24 0 1 1 0-48 24 24 0 1 1 0 48zm96 328c0 35.3 28.7 64 64 64H576c35.3 0 64-28.7 64-64V256c0-35.3-28.7-64-64-64H461.7c11.6 36 3.1 77-25.4 105.5L320 413.8V448zM480 328a24 24 0 1 1 0 48 24 24 0 1 1 0-48z" />
                    </svg>
                    <span>Random</span>
                </button>
            </div>
        </div>
        <Transition name="app-header-fade">
            <div v-if="menuOpen" class="app-header__backdrop" @click="closeMenu" />
        </Transition>

        <Transition name="app-header-slide">
            <div v-if="menuOpen" class="app-header__drawer">
                <div class="app-header__drawer-header">
                    <span class="app-header__drawer-title">Menu</span>
                    <button class="app-header__drawer-close" aria-label="Close menu" @click="closeMenu">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <nav class="app-header__drawer-nav">
                    <a
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        class="app-header__drawer-link"
                        :class="isActive(link.href, link.exact) ? 'app-header__drawer-link--active' : 'app-header__drawer-link--inactive'"
                        @click="closeMenu"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="app-header__drawer-search">
                    <div class="app-header__search">
                        <svg class="app-header__search-icon" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true">
                            <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" />
                        </svg>
                        <input
                            ref="searchInputRef"
                            v-model="searchInput"
                            class="app-header__search-input"
                            placeholder="Search plugins..."
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            @focus="searchVisible = true"
                            @blur="onBlur"
                        />
                    </div>
                    <div v-if="searchResults.length > 0" class="app-header__drawer-results">
                        <a
                            v-for="plugin in searchResults"
                            :key="plugin.id"
                            :href="show.url(plugin.name)"
                            class="app-header__search-result"
                            @click.prevent="navigateToPlugin(plugin.name)"
                        >
                            <span class="app-header__search-result-name">{{ plugin.display || plugin.name }}</span>
                            <span v-if="plugin.author" class="app-header__search-result-author">{{ plugin.author }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </Transition>

        <a href="https://github.com/phyce/runelite.phyce.dev" class="app-header__github-corner" aria-label="View source on GitHub" title="View source on GitHub" target="_blank" rel="noopener noreferrer">
            <svg width="80" height="80" viewBox="0 0 250 250" class="app-header__github-corner-svg" aria-hidden="true">
                <path d="M0,0 L115,115 L130,115 L142,142 L250,250 L250,0 Z"></path>
                <path d="M128.3,109.0 C113.8,99.7 119.0,89.6 119.0,89.6 C122.0,82.7 120.5,78.6 120.5,78.6 C119.2,72.0 123.4,76.3 123.4,76.3 C127.3,80.9 125.5,87.3 125.5,87.3 C122.9,97.6 130.6,101.9 134.4,103.2" fill="currentColor" style="transform-origin: 130px 106px" class="octo-arm"></path>
                <path d="M115.0,115.0 C114.9,115.1 118.7,116.5 119.8,115.4 L133.7,101.6 C136.9,99.2 139.9,98.4 142.2,98.6 C133.8,88.0 127.5,74.4 143.8,58.0 C148.5,53.4 154.0,51.2 159.7,51.0 C160.3,49.4 163.2,43.6 171.4,40.1 C171.4,40.1 176.1,42.5 178.8,56.2 C183.1,58.6 187.2,61.8 190.9,65.4 C194.5,69.0 197.7,73.2 200.1,77.6 C213.8,80.2 216.3,84.9 216.3,84.9 C212.7,93.1 206.9,96.0 205.4,96.6 C205.1,102.4 203.0,107.8 198.3,112.5 C181.9,128.9 168.3,122.5 157.7,114.1 C157.9,116.9 156.7,120.9 152.7,124.9 L141.0,136.5 C139.8,137.7 141.6,141.9 141.8,141.8 Z" fill="currentColor" class="octo-body"></path>
            </svg>
        </a>
    </header>
</template>

<style scoped>
@reference "tailwindcss";

.app-header {
    @apply relative w-full border-b border-neutral-700/60;
    background: linear-gradient(180deg, #1a1a1a 0%, #1c1917 100%);
}

.app-header__inner {
    @apply mx-auto flex w-full max-w-screen-2xl flex-row items-center gap-3 px-4 py-3;
}

.app-header__brand {
    @apply flex min-w-0 flex-1 items-center gap-3 no-underline sm:flex-none sm:flex-shrink-0;
}

.app-header__logo {
    @apply block flex-shrink-0 rounded;
    filter: drop-shadow(0 0 8px rgba(255, 108, 33, 0.35));
}

.app-header__title-group {
    @apply flex flex-col leading-tight;
}

.app-header__title {
    @apply truncate text-lg font-light tracking-wide text-white;
}

.app-header__version {
    @apply text-xs font-medium text-neutral-500;
}

.app-header__controls {
    @apply hidden flex-1 items-center gap-2 sm:flex;
}

.app-header__random-btn {
    @apply flex flex-shrink-0 items-center gap-2 rounded-md border border-orange-700/60 bg-orange-800/40 px-3 py-2 text-sm font-medium text-orange-200 transition-colors hover:border-orange-600 hover:bg-orange-700/60 hover:text-white active:bg-orange-800;
}

.app-header__random-btn--mobile {
    @apply w-full justify-center px-2.5 py-2 text-xs;
}

.app-header__random-icon {
    @apply flex-shrink-0 opacity-90;
}

.app-header__search {
    @apply relative flex-1;
}

.app-header__search-icon {
    @apply pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-neutral-500;
}

.app-header__search-input {
    @apply w-full rounded-md border border-neutral-600/70 bg-neutral-800 py-2 pr-3 pl-9 text-sm text-white placeholder:text-neutral-500 focus:border-orange-600/70 focus:outline-none focus:ring-1 focus:ring-orange-600/50;
}

.app-header__search-results {
    @apply absolute top-full left-0 z-50 mt-1.5 max-h-96 w-full overflow-y-auto rounded-md border border-neutral-700 bg-neutral-900 shadow-xl;
    scrollbar-width: thin;
    scrollbar-color: #c2410c #262626;
}

.app-header__search-results::-webkit-scrollbar { width: 6px; }
.app-header__search-results::-webkit-scrollbar-track { background: #262626; }
.app-header__search-results::-webkit-scrollbar-thumb { background-color: #c2410c; border-radius: 3px; }

.app-header__search-result {
    @apply flex items-baseline justify-between gap-3 px-3 py-2.5 text-sm text-white transition-colors hover:bg-neutral-800;
}

.app-header__search-result + .app-header__search-result {
    @apply border-t border-neutral-800;
}

.app-header__search-result-name {
    @apply truncate font-medium;
}

.app-header__search-result-author {
    @apply flex-shrink-0 text-xs text-neutral-500;
}

.app-header__mobile-actions {
    @apply flex flex-shrink-0 flex-col gap-1.5 sm:hidden;
}

.app-header__mobile-icon-row {
    @apply flex gap-1.5;
}

.app-header__mobile-icon-row .app-header__mobile-icon-btn {
    @apply flex-1 justify-center;
}

.app-header__mobile-icon-btn {
    @apply flex items-center justify-center rounded-md border border-neutral-600/70 bg-neutral-800/60 p-2 text-gray-400 transition-colors hover:border-neutral-500 hover:text-white;
}

.app-header__backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    z-index: 40;
}

.app-header__drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 100%; /* full width on small phones */
    z-index: 50;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    /* no background — content sections carry their own, leaving bottom area translucent */
}

@media (min-width: 640px) {
    .app-header__drawer {
        width: min(300px, 85vw);
        border-left: 1px solid #2a2a2a;
    }
}

.app-header__drawer-header {
    @apply flex items-center justify-between border-b border-neutral-700/60 px-4 py-4;
    background: #141414;
}

.app-header__drawer-title {
    @apply text-sm font-semibold uppercase tracking-widest text-neutral-400;
}

.app-header__drawer-close {
    @apply flex items-center justify-center rounded-md p-1.5 text-neutral-400 transition-colors hover:bg-neutral-800 hover:text-white;
}

.app-header__drawer-nav {
    @apply flex flex-col gap-1 p-3;
    background: #141414;
}

.app-header__drawer-link {
    @apply rounded-md px-3 py-3 text-base font-medium transition-colors;
}

.app-header__drawer-link--active {
    background: rgba(194, 65, 12, 0.2);
    color: #fb923c;
}

.app-header__drawer-link--inactive {
    @apply text-gray-300 hover:bg-neutral-800 hover:text-white;
}

.app-header__drawer-search {
    @apply border-t border-neutral-700/60 p-3;
    background: #141414;
}

.app-header__drawer-search .app-header__search {
    @apply flex-none;
}

.app-header__drawer-results {
    @apply mt-2 overflow-y-auto rounded-md border border-neutral-700;
    max-height: 50vh;
    scrollbar-width: thin;
    scrollbar-color: #c2410c #262626;
}

.app-header__github-corner {
    @apply hidden sm:block;
    position: absolute;
    top: 0;
    right: 0;
}

.app-header__github-corner-svg {
    fill: rgba(154, 52, 18, 0.65);
    color: rgba(255, 255, 255, 0.6);
    display: block;
}

.app-header__github-corner:hover .octo-arm {
    animation: octocat-wave 560ms ease-in-out;
}

@keyframes octocat-wave {
    0%, 100% { transform: rotate(0); }
    20%, 60% { transform: rotate(-25deg); }
    40%, 80% { transform: rotate(10deg); }
}

.app-header-fade-enter-active,
.app-header-fade-leave-active {
    transition: opacity 0.2s ease;
}

.app-header-fade-enter-from,
.app-header-fade-leave-to {
    opacity: 0;
}

.app-header-slide-enter-active,
.app-header-slide-leave-active {
    transition: transform 0.25s ease;
}

.app-header-slide-enter-from,
.app-header-slide-leave-to {
    transform: translateX(100%);
}
</style>
