export * from './auth';

export interface Plugin {
    id: number;
    name: string;
    git_repo: string;
    display: string;
    author: string;
    support: string;
    description: string;
    tags: string;
    warning: string;
    created_on: string;
    updated_on: string;
    all_time_high: number;
    current_installs: number;
}

export interface PluginHistoryData {
    count: number;
    date: string;
}

export interface GrowthEntry {
    rank: number;
    plugin: Plugin;
    absolute_growth: number;
    pct_growth: number;
    window_start: string;
    window_days: number;
}

export interface RankedPlugin {
    rank: number;
    plugin: Plugin;
    score: number;
    score_installs: number;
    score_growth_30d: number;
    score_retention: number;
    score_consistency: number;
    score_momentum_7d: number;
}

export interface Top100Metrics {
    computed_at: string;
    rankings: RankedPlugin[];
}

export type DeveloperPeriod = 'day' | 'week' | 'month' | 'year' | 'all';

export interface DeveloperRanks {
    installs: number;
}

/** Row shape for /developers/top, /developers/top/popular and /developers/top/growing. */
export interface DeveloperRankEntry {
    rank: number;
    slug: string;
    name: string;
    plugin_count: number;
    total_installs: number;
    contributing_since: string | null;
    absolute_growth: number;
    pct_growth: number;
    growth_base: number;
}

export interface DeveloperRankings {
    window?: string;
    computed_at: string;
    entries: DeveloperRankEntry[];
}

export interface DeveloperSummary {
    slug: string;
    name: string;
    plugin_count: number;
    total_installs: number;
    contributing_since: string | null;
}

export interface DeveloperIndex {
    computed_at: string;
    count: number;
    developers: DeveloperSummary[];
}

export interface PluginDeveloper {
    slug: string | null;
    name: string;
    plugin_count: number | null;
    total_installs: number | null;
    contributing_since: string | null;
}

export interface DeveloperPluginEntry {
    plugin: Plugin;
    coauthors: string[] | null;
}

export interface Developer {
    slug: string;
    name: string;
    aliases: string[] | null;

    plugin_count: number;
    contributing_since: string | null;
    days_contributing: number;
    last_updated: string | null;
    days_since_update: number;

    total_installs: number;
    peak_installs: number;

    ranks: DeveloperRanks;
    plugins: DeveloperPluginEntry[];
}

export interface RelatedEntry {
    plugin: Plugin;
    score: number;
    shared_tags: string[] | null;
    same_author: boolean;
    reason: 'tags' | 'author';
}

export interface RelatedPlugins {
    plugin: string;
    computed_at: string;
    entries: RelatedEntry[];
}
