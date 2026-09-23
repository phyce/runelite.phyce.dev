import { layoutTags } from './tagMap';
import type { MapLayout, MapLink, MapTagInput, SizeMetric } from './tagMap';

export type LayoutRequest = { nodes: MapTagInput[]; links: MapLink[] } | { id: number; metric: SizeMetric };

export interface SettledLayout {
    id: number;
    positions: Float64Array;
    bounds: MapLayout['bounds'];
}

let nodes: MapTagInput[] = [];
let links: MapLink[] = [];
const layouts = new Map<SizeMetric, MapLayout>();

function layoutFor(metric: SizeMetric): MapLayout {
    let layout = layouts.get(metric);
    if (!layout) {
        const from = metric === 'plugin_count' ? undefined : layoutFor('plugin_count');
        layout = layoutTags(nodes, links, metric, { from, labels: false });
        layouts.set(metric, layout);
    }
    return layout;
}

self.onmessage = (event: MessageEvent<LayoutRequest>) => {
    const request = event.data;
    if ('nodes' in request) {
        ({ nodes, links } = request);
        layouts.clear();
        return;
    }

    const layout = layoutFor(request.metric);
    const positions = new Float64Array(layout.tags.length * 3);
    layout.tags.forEach((tag, index) => positions.set([tag.x, tag.y, tag.r], index * 3));
    const reply: SettledLayout = { id: request.id, positions, bounds: layout.bounds };
    self.postMessage(reply, { transfer: [positions.buffer] });
};
