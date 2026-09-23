
export type SizeMetric = 'plugin_count' | 'total_installs';

export interface MapTagInput {
    slug: string;
    name: string;
    plugin_count: number;
    total_installs: number;
}

export type MapNode = [slug: string, name: string, plugin_count: number, total_installs: number];

export type MapLink = [number, number, number];

export type TextMeasure = (text: string) => number;

export interface MapLabel {
    lines: string[];
    fontSize: number;
}

export interface MapTag extends MapTagInput {
    index: number;
    x: number;
    y: number;
    r: number;
    label: MapLabel | null;
}

export interface MapLayout {
    tags: MapTag[];
    bounds: { minX: number; minY: number; maxX: number; maxY: number };
}

export interface MapPlugin {
    name: string;
    label: string;
    installs: number;
}

export interface PackedCircle {
    x: number;
    y: number;
    r: number;
}

const WORLD_RADIUS = 500;
const FILL = 0.58;
const GAP = 0.35;
const GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5));
const STRETCH = 3.2;
const SIZE_EXPONENT = 0.45;
const MIN_RADIUS_SHARE = 1 / 15;
const CORE_TAGS = 180;
const GRAVITY = 0.005;
const COMPACT_PASSES = 60;
const SETTLE_PASSES = 40;

export const estimateWidth: TextMeasure = (text) => text.length * 0.57;

export function layoutTags(
    input: MapTagInput[],
    links: MapLink[],
    metric: SizeMetric,
    options: { from?: MapLayout; measure?: TextMeasure; labels?: boolean } = {},
): MapLayout {
    const { from, measure = estimateWidth, labels = true } = options;
    const count = input.length;
    const sizes = input.map((tag) => Math.pow(Math.max(tag[metric], 1), SIZE_EXPONENT));
    const floor = Math.max(...sizes, 1) * MIN_RADIUS_SHARE;
    for (let i = 0; i < count; i++) sizes[i] = Math.max(sizes[i], floor);
    const unit = Math.sqrt((FILL * WORLD_RADIUS * WORLD_RADIUS) / sizes.reduce((sum, size) => sum + size * size, 0));

    const tags: MapTag[] = input.map((tag, index) => {
        const r = sizes[index] * unit;
        return { ...tag, index, r, x: 0, y: 0, label: labels ? fitLabel(tag.name, r, measure) : null };
    });

    const anchors = anchorsOf(input, links, Math.min(CORE_TAGS, count));
    const roots = [...new Set(anchors)].sort((a, b) => a - b);
    const slot = new Map(roots.map((root, position) => [root, position]));
    const satellites: number[][] = roots.map(() => []);
    for (let i = 0; i < count; i++) {
        if (anchors[i] !== i) satellites[slot.get(anchors[i])!].push(i);
    }

    const extents = roots.map((root, position) => packColony(tags[root], satellites[position].map((i) => tags[i]), position));
    const colonies: PackedCircle[] = roots.map((root, position) => {
        const previous = from?.tags[root];
        const angle = position * GOLDEN_ANGLE;
        const distance = WORLD_RADIUS * 0.92 * Math.sqrt((position + 0.5) / roots.length);
        return {
            r: extents[position],
            x: previous ? previous.x : distance * Math.cos(angle) * Math.sqrt(STRETCH),
            y: previous ? previous.y : (distance * Math.sin(angle)) / Math.sqrt(STRETCH),
        };
    });
    const coreLinks = links.flatMap(([a, b, strength]): MapLink[] => (slot.has(a) && slot.has(b) ? [[slot.get(a)!, slot.get(b)!, strength]] : []));
    settle(colonies, coreLinks, from ? 140 : 300, from ? 0.4 : 1);

    roots.forEach((root, position) => {
        const { x, y } = colonies[position];
        for (const i of [root, ...satellites[position]]) {
            tags[i].x += x;
            tags[i].y += y;
        }
    });
    compact(tags, anchors);
    resolveOverlaps(tags, anchors);

    return { tags, bounds: boundsOf(tags) };
}

function anchorsOf(input: MapTagInput[], links: MapLink[], coreCount: number): Int32Array {
    const linked: Array<Array<[number, number]>> = input.map(() => []);
    for (const [a, b, strength] of links) {
        linked[a]?.push([b, strength]);
        linked[b]?.push([a, strength]);
    }
    for (const neighbours of linked) neighbours.sort((x, y) => y[1] - x[1]);

    const anchors = new Int32Array(input.length).fill(-1);
    for (let i = 0; i < coreCount; i++) anchors[i] = i;

    for (let i = coreCount; i < input.length; i++) {
        if (anchors[i] >= 0) continue;
        const seen = new Set([i]);
        const queue = [i];
        let found = -1;
        for (let head = 0; head < queue.length && found < 0; head++) {
            for (const [j] of linked[queue[head]]) {
                if (j < coreCount) {
                    found = j;
                    break;
                }
                if (!seen.has(j)) {
                    seen.add(j);
                    queue.push(j);
                }
            }
        }
        if (found >= 0) {
            anchors[i] = found;
        } else {
            const root = Math.min(...queue);
            for (const member of queue) anchors[member] = root;
        }
    }
    return anchors;
}

function packColony(hub: MapTag, members: MapTag[], seed: number): number {
    hub.x = 0;
    hub.y = 0;
    if (!members.length) return hub.r;

    members.sort((a, b) => b.r - a.r);
    let area = Math.PI * (hub.r + GAP) ** 2;
    members.forEach((member, k) => {
        area += (Math.PI * (member.r + GAP) ** 2) / 0.8;
        const distance = Math.max(Math.sqrt(area / Math.PI) - member.r, hub.r + member.r + GAP);
        const angle = k * GOLDEN_ANGLE + seed;
        member.x = Math.cos(angle) * distance;
        member.y = Math.sin(angle) * distance;
    });

    const circles = [hub, ...members];
    const fixed = new Uint8Array(circles.length);
    fixed[0] = 1;
    const order = sweepOrder(circles.length);
    for (let pass = 0; pass < 80; pass++) {
        if (pass < 60) {
            for (const member of members) pullToward(member, hub, 0.1);
        }
        separate(circles, GAP, 1, order, fixed);
    }
    return members.reduce((reach, member) => Math.max(reach, Math.hypot(member.x, member.y) + member.r), hub.r);
}

function pullToward(circle: PackedCircle, target: PackedCircle, share: number): void {
    const dx = target.x - circle.x;
    const dy = target.y - circle.y;
    const distance = Math.sqrt(dx * dx + dy * dy) || 0.001;
    const slack = distance - (target.r + circle.r + GAP);
    if (slack > 0) {
        circle.x += (dx / distance) * slack * share;
        circle.y += (dy / distance) * slack * share;
    }
}

function compact(tags: MapTag[], anchors: Int32Array): void {
    const count = tags.length;
    const xs = Float64Array.from(tags, (tag) => tag.x);
    const ys = Float64Array.from(tags, (tag) => tag.y);
    const rs = Float64Array.from(tags, (tag) => tag.r);
    const order = sweepOrder(count);
    for (let pass = 0; pass < COMPACT_PASSES + SETTLE_PASSES; pass++) {
        if (pass < COMPACT_PASSES) {
            const gravity = GRAVITY * (1 - pass / COMPACT_PASSES);
            for (let i = 0; i < count; i++) {
                xs[i] -= xs[i] * gravity;
                ys[i] -= ys[i] * gravity;
                const hub = anchors[i];
                if (hub === i) continue;
                const dx = xs[hub] - xs[i];
                const dy = ys[hub] - ys[i];
                const distance = Math.sqrt(dx * dx + dy * dy) || 0.001;
                const slack = distance - (rs[hub] + rs[i] + GAP);
                if (slack > 0) {
                    xs[i] += (dx / distance) * slack * 0.05;
                    ys[i] += (dy / distance) * slack * 0.05;
                }
            }
        }
        separateArrays(xs, ys, rs, order);
        if (pass < COMPACT_PASSES) separateArrays(xs, ys, rs, order);
    }
    tags.forEach((tag, i) => {
        tag.x = xs[i];
        tag.y = ys[i];
    });
}

function separateArrays(xs: Float64Array, ys: Float64Array, rs: Float64Array, order: Int32Array): void {
    const count = order.length;
    for (let i = 1; i < count; i++) {
        const index = order[i];
        const left = xs[index] - rs[index];
        let j = i - 1;
        while (j >= 0 && xs[order[j]] - rs[order[j]] > left) {
            order[j + 1] = order[j];
            j--;
        }
        order[j + 1] = index;
    }

    for (let p = 0; p < count; p++) {
        const a = order[p];
        const reach = xs[a] + rs[a] + GAP;
        for (let q = p + 1; q < count; q++) {
            const b = order[q];
            if (xs[b] - rs[b] > reach) break;
            let dx = xs[b] - xs[a];
            let dy = ys[b] - ys[a];
            const min = rs[a] + rs[b] + GAP;
            let distanceSquared = dx * dx + dy * dy;
            if (distanceSquared >= min * min) continue;
            if (distanceSquared === 0) {
                dx = (q - p) * 0.001;
                dy = 0.001;
                distanceSquared = dx * dx + dy * dy;
            }
            const distance = Math.sqrt(distanceSquared);
            const push = (min - distance) / distance;
            const share = rs[a] / (rs[a] + rs[b]);
            xs[a] -= dx * push * (1 - share);
            ys[a] -= dy * push * (1 - share);
            xs[b] += dx * push * share;
            ys[b] += dy * push * share;
        }
    }
}

function resolveOverlaps(tags: MapTag[], anchors: Int32Array): void {
    const size = 8;
    const grid = new Map<number, number[]>();
    const key = (cx: number, cy: number): number => cx * 100003 + cy;
    const cellsOf = (x: number, y: number, r: number, visit: (key: number) => void): void => {
        for (let cx = Math.floor((x - r) / size); cx <= Math.floor((x + r) / size); cx++) {
            for (let cy = Math.floor((y - r) / size); cy <= Math.floor((y + r) / size); cy++) visit(key(cx, cy));
        }
    };
    const insert = (i: number): void => cellsOf(tags[i].x, tags[i].y, tags[i].r, (k) => (grid.get(k) ?? grid.set(k, []).get(k)!).push(i));
    const remove = (i: number): void => cellsOf(tags[i].x, tags[i].y, tags[i].r, (k) => {
        const list = grid.get(k)!;
        list.splice(list.indexOf(i), 1);
    });
    const blocker = (i: number, x: number, y: number, r: number, tolerance: number): number => {
        let found = -1;
        cellsOf(x, y, r + GAP, (k) => {
            if (found >= 0) return;
            for (const j of grid.get(k) ?? []) {
                if (j === i) continue;
                const other = tags[j];
                const reach = r + other.r + GAP - tolerance;
                if ((other.x - x) ** 2 + (other.y - y) ** 2 < reach * reach) {
                    found = j;
                    return;
                }
            }
        });
        return found;
    };

    tags.forEach((_, i) => insert(i));
    const bySize = tags.map((_, i) => i).sort((a, b) => tags[a].r - tags[b].r);
    for (const i of bySize) {
        const tag = tags[i];
        if (blocker(i, tag.x, tag.y, tag.r, GAP + 0.05) < 0) continue;
        remove(i);
        const hub = tags[anchors[i]];
        const facing = hub === tag ? 0 : Math.atan2(hub.y - tag.y, hub.x - tag.x);
        let placed = false;
        for (let ring = 1; ring < 400 && !placed; ring++) {
            const distance = ring * Math.min(tag.r, 2) * 0.5;
            const steps = Math.max(8, Math.ceil((Math.PI * 2 * distance) / Math.min(tag.r, 2)));
            for (let step = 0; step < steps; step++) {
                const angle = facing + (step % 2 ? 1 : -1) * Math.ceil(step / 2) * ((Math.PI * 2) / steps);
                const x = tag.x + Math.cos(angle) * distance;
                const y = tag.y + Math.sin(angle) * distance;
                if (blocker(i, x, y, tag.r, 0) < 0) {
                    tag.x = x;
                    tag.y = y;
                    placed = true;
                    break;
                }
            }
        }
        insert(i);
    }
}

function settle(tags: PackedCircle[], links: MapLink[], ticks: number, startAlpha: number): void {
    const vx = new Float64Array(tags.length);
    const vy = new Float64Array(tags.length);
    const strongest = links.reduce((max, link) => Math.max(max, link[2]), 0.0001);
    const decay = 1 - Math.pow(0.001, 1 / ticks);
    const order = sweepOrder(tags.length);
    let alpha = startAlpha;

    for (let tick = 0; tick < ticks; tick++) {
        alpha -= alpha * decay;

        for (const [a, b, strength] of links) {
            const from = tags[a];
            const to = tags[b];
            const dx = to.x + vx[b] - from.x - vx[a];
            const dy = to.y + vy[b] - from.y - vy[a];
            const distance = Math.sqrt(dx * dx + dy * dy) || 0.001;
            const pull = ((distance - (from.r + to.r + GAP * 2)) / distance) * alpha * 0.3 * Math.sqrt(strength / strongest);
            const share = (from.r * from.r) / (from.r * from.r + to.r * to.r);

            vx[b] -= dx * pull * share;
            vy[b] -= dy * pull * share;
            vx[a] += dx * pull * (1 - share);
            vy[a] += dy * pull * (1 - share);
        }

        for (let i = 0; i < tags.length; i++) {
            vx[i] -= (tags[i].x * 0.035 * alpha) / STRETCH;
            vy[i] -= tags[i].y * 0.035 * alpha * STRETCH;
            vx[i] *= 0.6;
            vy[i] *= 0.6;
            tags[i].x += vx[i];
            tags[i].y += vy[i];
        }

        separate(tags, GAP, 0.7, order);
    }

    for (let pass = 0; pass < 50; pass++) {
        separate(tags, GAP, 1, order);
    }
}

function separate(circles: PackedCircle[], gap: number, strength: number, order: Int32Array, fixed?: Uint8Array): void {
    for (let i = 1; i < order.length; i++) {
        const index = order[i];
        const left = circles[index].x - circles[index].r;
        let j = i - 1;
        while (j >= 0 && circles[order[j]].x - circles[order[j]].r > left) {
            order[j + 1] = order[j];
            j--;
        }
        order[j + 1] = index;
    }

    for (let p = 0; p < order.length; p++) {
        const a = circles[order[p]];
        const aFixed = fixed?.[order[p]] === 1;

        for (let q = p + 1; q < order.length; q++) {
            const b = circles[order[q]];
            if (b.x - b.r > a.x + a.r + gap) break;
            const bFixed = fixed?.[order[q]] === 1;
            if (aFixed && bFixed) continue;

            let dx = b.x - a.x;
            let dy = b.y - a.y;
            const min = a.r + b.r + gap;
            let distanceSquared = dx * dx + dy * dy;

            if (distanceSquared >= min * min) continue;

            if (distanceSquared === 0) {
                dx = (q - p) * 0.001;
                dy = 0.001;
                distanceSquared = dx * dx + dy * dy;
            }

            const distance = Math.sqrt(distanceSquared);
            const push = ((min - distance) / distance) * strength;
            const share = aFixed ? 1 : bFixed ? 0 : a.r / (a.r + b.r);

            a.x -= dx * push * (1 - share);
            a.y -= dy * push * (1 - share);
            b.x += dx * push * share;
            b.y += dy * push * share;
        }
    }
}

function sweepOrder(count: number): Int32Array {
    return Int32Array.from({ length: count }, (_, index) => index);
}

function boundsOf(circles: PackedCircle[]): MapLayout['bounds'] {
    return circles.reduce(
        (bounds, c) => ({
            minX: Math.min(bounds.minX, c.x - c.r),
            minY: Math.min(bounds.minY, c.y - c.r),
            maxX: Math.max(bounds.maxX, c.x + c.r),
            maxY: Math.max(bounds.maxY, c.y + c.r),
        }),
        { minX: Infinity, minY: Infinity, maxX: -Infinity, maxY: -Infinity },
    );
}

export function fitLabel(name: string, r: number, measure: TextMeasure = estimateWidth): MapLabel | null {
    const lines = name.length > 11 && name.includes(' ') ? balancedLines(name) : [name];
    const widest = Math.max(...lines.map((line) => measure(line)), 0.5);
    const byWidth = (r * 1.5) / widest;
    const byHeight = (r * (lines.length > 1 ? 0.58 : 0.42)) / lines.length;
    const fontSize = Math.min(byWidth, byHeight);

    return fontSize < r * 0.13 ? null : { lines, fontSize };
}

function balancedLines(name: string): string[] {
    const words = name.split(' ');
    let best = [name];
    let bestLength = Infinity;

    for (let split = 1; split < words.length; split++) {
        const first = words.slice(0, split).join(' ');
        const second = words.slice(split).join(' ');
        const length = Math.max(first.length, second.length);

        if (length < bestLength) {
            best = [first, second];
            bestLength = length;
        }
    }

    return best;
}

export interface Point {
    x: number;
    y: number;
}

export interface CellShape {
    polygon: Point[];
    x: number;
    y: number;
    inner: number;
}

function polygonArea(polygon: Point[]): number {
    let area = 0;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        area += (polygon[j].x + polygon[i].x) * (polygon[j].y - polygon[i].y);
    }
    return Math.abs(area) / 2;
}

function polygonCentroid(polygon: Point[]): Point {
    let area = 0;
    let x = 0;
    let y = 0;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const cross = polygon[j].x * polygon[i].y - polygon[i].x * polygon[j].y;
        area += cross;
        x += (polygon[j].x + polygon[i].x) * cross;
        y += (polygon[j].y + polygon[i].y) * cross;
    }
    return Math.abs(area) < 1e-12 ? { ...polygon[0] } : { x: x / (3 * area), y: y / (3 * area) };
}

function clipToHalfPlane(polygon: Point[], ax: number, ay: number, c: number): Point[] {
    if (polygon.every((point) => ax * point.x + ay * point.y <= c)) return polygon;
    const clipped: Point[] = [];
    for (let i = 0; i < polygon.length; i++) {
        const a = polygon[i];
        const b = polygon[(i + 1) % polygon.length];
        const da = ax * a.x + ay * a.y - c;
        const db = ax * b.x + ay * b.y - c;
        if (da <= 0) clipped.push(a);
        if (da <= 0 !== db <= 0) {
            const t = da / (da - db);
            clipped.push({ x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t });
        }
    }
    return clipped;
}

function powerCells(sites: Point[], weights: Float64Array, boundary: Point[]): Point[][] {
    return sites.map((site, i) => {
        let cell = boundary;
        for (let j = 0; j < sites.length && cell.length > 2; j++) {
            if (j === i) continue;
            const other = sites[j];
            cell = clipToHalfPlane(
                cell,
                2 * (other.x - site.x),
                2 * (other.y - site.y),
                other.x * other.x + other.y * other.y - site.x * site.x - site.y * site.y - weights[j] + weights[i],
            );
        }
        return cell;
    });
}

function distanceToEdges(polygon: Point[], point: Point): number {
    let nearest = Infinity;
    for (let i = 0; i < polygon.length; i++) {
        const a = polygon[i];
        const b = polygon[(i + 1) % polygon.length];
        const length = Math.hypot(b.x - a.x, b.y - a.y) || 1e-9;
        nearest = Math.min(nearest, Math.abs((point.x - a.x) * (b.y - a.y) - (point.y - a.y) * (b.x - a.x)) / length);
    }
    return nearest;
}

const UNIT_CIRCLE: Point[] = Array.from({ length: 96 }, (_, i) => ({
    x: Math.cos((i / 96) * Math.PI * 2),
    y: Math.sin((i / 96) * Math.PI * 2),
}));

export function cellsIn(values: number[], boundary: Point[] = UNIT_CIRCLE): CellShape[] {
    const centre = polygonCentroid(boundary);
    if (values.length <= 1) {
        return values.length ? [{ polygon: boundary, x: centre.x, y: centre.y, inner: distanceToEdges(boundary, centre) }] : [];
    }

    const count = values.length;
    const boundaryArea = polygonArea(boundary);
    const scale = boundaryArea / Math.PI;
    const reach = distanceToEdges(boundary, centre) * 0.85;
    const total = values.reduce((sum, value) => sum + Math.max(value, 1e-9), 0);
    const targets = values.map((value) => (Math.max(value, 1e-9) / total) * boundaryArea);
    const sites: Point[] = values.map((_, index) => {
        const radius = reach * Math.sqrt((index + 0.5) / count);
        return { x: centre.x + radius * Math.cos(index * GOLDEN_ANGLE), y: centre.y + radius * Math.sin(index * GOLDEN_ANGLE) };
    });
    const weights = new Float64Array(count).fill(1e-4 * scale);
    let cells = powerCells(sites, weights, boundary);

    for (let round = 0; round < 120; round++) {
        const areas = cells.map((cell) => (cell.length > 2 ? polygonArea(cell) : 0));
        const error = areas.reduce((sum, area, index) => sum + Math.abs(area - targets[index]), 0) / (2 * boundaryArea);
        if (error < 0.01) break;

        for (let i = 0; i < count; i++) {
            if (cells[i].length > 2) {
                const centroid = polygonCentroid(cells[i]);
                sites[i].x = centroid.x;
                sites[i].y = centroid.y;
            }
            const ratio = areas[i] > 0 ? targets[i] / areas[i] : 2;
            weights[i] = Math.max(weights[i] * Math.min(Math.max(ratio, 0.5), 2), 1e-7 * scale);
        }
        for (let i = 0; i < count; i++) {
            let nearest = Infinity;
            for (let j = 0; j < count; j++) {
                if (j !== i) nearest = Math.min(nearest, (sites[i].x - sites[j].x) ** 2 + (sites[i].y - sites[j].y) ** 2);
            }
            weights[i] = Math.min(weights[i], nearest);
        }
        cells = powerCells(sites, weights, boundary);
    }

    return cells.map((polygon) => {
        if (polygon.length < 3) return { polygon: [], x: 0, y: 0, inner: 0 };
        const centroid = polygonCentroid(polygon);
        return { polygon, x: centroid.x, y: centroid.y, inner: distanceToEdges(polygon, centroid) };
    });
}

export function insidePolygon(polygon: Point[], x: number, y: number): boolean {
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const a = polygon[i];
        const b = polygon[j];
        if (a.y > y !== b.y > y && x < ((b.x - a.x) * (y - a.y)) / (b.y - a.y) + a.x) inside = !inside;
    }
    return inside;
}

export interface ZoomView {
    x: number;
    y: number;
    w: number;
}

export function interpolateZoom(from: ZoomView, to: ZoomView): { at: (t: number) => ZoomView; duration: number } {
    const rho = Math.SQRT2;
    const dx = to.x - from.x;
    const dy = to.y - from.y;
    const distanceSquared = dx * dx + dy * dy;

    if (distanceSquared < 1e-12) {
        const s = Math.log(to.w / from.w) / rho;

        return {
            at: (t) => ({ x: from.x + t * dx, y: from.y + t * dy, w: from.w * Math.exp(rho * t * s) }),
            duration: Math.abs(s) * 1000,
        };
    }

    const distance = Math.sqrt(distanceSquared);
    const b0 = (to.w * to.w - from.w * from.w + 4 * distanceSquared) / (2 * from.w * 2 * distance);
    const b1 = (to.w * to.w - from.w * from.w - 4 * distanceSquared) / (2 * to.w * 2 * distance);
    const r0 = Math.log(Math.sqrt(b0 * b0 + 1) - b0);
    const r1 = Math.log(Math.sqrt(b1 * b1 + 1) - b1);
    const s = (r1 - r0) / rho;

    return {
        at: (t) => {
            const step = t * s;
            const coshR0 = Math.cosh(r0);
            const u = (from.w / (2 * distance)) * (coshR0 * Math.tanh(rho * step + r0) - Math.sinh(r0));

            return { x: from.x + u * dx, y: from.y + u * dy, w: (from.w * coshR0) / Math.cosh(rho * step + r0) };
        },
        duration: Math.abs(s) * 1000,
    };
}

export function tagSlug(name: string): string {
    return name
        .trim()
        .toLowerCase()
        .replace(/[^\p{L}\p{Nd}]+/gu, '-')
        .replace(/^-+|-+$/g, '');
}
