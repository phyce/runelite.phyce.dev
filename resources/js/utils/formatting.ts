import type { Plugin } from '@/types';

export function formatDate(dateString: string): string {
    const options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    };
    return new Intl.DateTimeFormat('en-US', options).format(new Date(dateString));
}

export function formatNumber(num: number): string {
    return num.toLocaleString('en-US');
}

export function truncateString(str: string, num: number): string {
    if (str.length <= num) {
        return str;
    }
    return str.slice(0, num) + '…';
}

export function scoreSearchResult(plugin: Plugin, query: string): number {
    if (!query.trim()) {
        return 0;
    }

    const searchTerms = query.toLowerCase().split(' ').filter(Boolean);
    let score = 0;

    const wordsInFields = new Set<string>();
    const fieldsToSearch = [plugin.name, plugin.display, plugin.author, plugin.description, plugin.tags];

    fieldsToSearch.forEach((field) => {
        if (field) {
            field
                .toLowerCase()
                .split(' ')
                .forEach((word) => wordsInFields.add(word));
        }
    });

    searchTerms.forEach((term) => {
        if (wordsInFields.has(term)) {
            score += 2;
        } else {
            wordsInFields.forEach((word) => {
                if (word.includes(term)) {
                    score += 1;
                }
            });
        }
    });

    return score;
}
