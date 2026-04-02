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
