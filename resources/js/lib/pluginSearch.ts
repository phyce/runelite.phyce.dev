import type { InjectionKey, Ref } from 'vue';
import type { Plugin } from '@/types';
import { scoreSearchResult } from '@/utils/formatting';

/**
 * The header search box and the homepage plugin list share one query string.
 *
 * On the homepage the query filters the list in place; everywhere else the
 * header keeps showing its own results dropdown. The ref is provided by
 * AppLayout rather than living at module scope so that it is created per app
 * instance — module state would be shared across requests under SSR.
 */
export const pluginSearchKey: InjectionKey<Ref<string>> =
    Symbol('pluginSearch');

/**
 * Whether a plugin matches the query, using the same scoring the header
 * dropdown uses so that filtering and searching never disagree.
 */
export function matchesQuery(plugin: Plugin, query: string): boolean {
    return scoreSearchResult(plugin, query) > 0;
}

/**
 * Filters while preserving the caller's ordering — the homepage sorts by the
 * column the user picked, which must survive filtering.
 */
export function filterPlugins(plugins: Plugin[], query: string): Plugin[] {
    const trimmed = query.trim();

    if (!trimmed) {
        return plugins;
    }

    return plugins.filter((plugin) => matchesQuery(plugin, trimmed));
}
