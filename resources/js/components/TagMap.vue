<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, toRaw, watch } from 'vue';
import { show as tagShow } from '@/actions/App/Http/Controllers/TagController';
import { formatNumber } from '@/utils/formatting';
import { cellsIn, estimateWidth, fitLabel, insidePolygon, interpolateZoom, layoutTags } from '@/utils/tagMap';
import type { CellShape, MapLabel, MapLayout, MapLink, MapPlugin, MapTag, MapTagInput, Point, SizeMetric, TextMeasure, ZoomView } from '@/utils/tagMap';
import type { LayoutRequest, SettledLayout } from '@/utils/tagMapLayout.worker';

const props = defineProps<{
    nodes: MapTagInput[];
    links: MapLink[];
    metric: SizeMetric;
    focused: string | null;
    plugins: MapPlugin[] | null;
    highlighted: string | null;
    linked: string | null;
    insets: { top: number; right: number; bottom: number };
}>();

const emit = defineEmits<{
    select: [slug: string];
    selectPlugin: [name: string, byMouse: boolean];
    more: [firstName: string];
    clear: [];
    hoverTag: [slug: string | null];
}>();

const MIN_ZOOM = 0.7;
const MAX_ZOOM = 45;
const HOME_MIN_K = 1;
const HOME_ZOOM = 1.3;
const PHONE_WIDTH_PX = 640;
const TAP_TOLERANCE_PX = 16;
const DRAG_THRESHOLD_PX = 6;
const MIN_LABEL_PX = 8;
const MAX_LABEL_PX = 40;
const MAX_CELL_LABEL_PX = 44;
const SHORT_WIDTH = 1.6;
const SHORT_MIN_RADIUS = 1.5;
const MIN_SHORT_CHARS = 3;
const SPRITE_CELLS = [8, 16, 32, 64, 128];
const DIM_STEPS = 8;
const MAX_RELATED = 8;
const LISTED_TAGS = 180;
const TAU = Math.PI * 2;
const CELL_MIN_SHARE = 0.003;
const MAX_CELLS = 60;
const CELL_FILL = 0.97;
const MEMBRANE = 'rgb(27, 20, 17)';

type Tone = readonly number[];
const TONE = {
    base: [78, 46, 30, 44, 27, 19, 255, 120, 60, 0.14, 1, 243, 244, 246],
    quiet: [52, 34, 25, 36, 24, 18, 255, 120, 60, 0.06, 1, 243, 244, 246],
    dim: [42, 39, 37, 31, 29, 28, 255, 255, 255, 0.04, 1, 120, 120, 120],
    related: [120, 62, 32, 66, 35, 20, 255, 108, 33, 0.7, 1.5, 255, 255, 255],
    active: [166, 80, 36, 94, 44, 21, 255, 140, 80, 1, 2, 255, 255, 255],
    focus: [162, 84, 42, 85, 41, 26, 255, 108, 33, 1, 2.5, 255, 255, 255],
    plugin: [162, 84, 42, 85, 41, 26, 255, 170, 120, 0.22, 1, 255, 241, 232],
    pluginActive: [220, 116, 58, 132, 62, 30, 255, 205, 170, 0.9, 1.5, 255, 255, 255],
    rest: [74, 59, 52, 42, 34, 30, 255, 255, 255, 0.1, 1, 214, 211, 209],
    restActive: [104, 84, 74, 62, 50, 44, 255, 255, 255, 0.5, 1.5, 255, 255, 255],
} satisfies Record<string, Tone>;

const SPRITE_ROWS = SPRITE_CELLS.map((_, level) => SPRITE_CELLS.slice(0, level).reduce((top, cell) => top + cell, 0));

interface ShortName {
    chars: string[] | null;
    widths: Float32Array | null;
    shortLength: number;
    shortText: string;
}

const UNMEASURED: ShortName = { chars: null, widths: null, shortLength: -1, shortText: '' };

interface Bubble extends ShortName {
    tag: MapTag;
    x: number;
    y: number;
    r: number;
    fromX: number;
    fromY: number;
    fromR: number;
    active: number;
    related: number;
    dim: number;
    focus: number;
    linked: number;
}

interface BloomCell extends CellShape, ShortName {
    name: string;
    label: string;
    installs: number;
    rest: MapPlugin[];
    text: MapLabel | null;
    delay: number;
}

interface CellLevel {
    source: BloomCell | null;
    cells: BloomCell[];
    start: number;
    leaving: number | null;
}

interface Bloom {
    slug: string;
    levels: CellLevel[];
    leaving: number | null;
}

interface PendingLabel {
    lines: string[];
    x: number;
    y: number;
    px: number;
    fill: string;
}

const root = ref<HTMLElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const ready = ref(false);
const hovered = ref<{ kind: 'tag' | 'plugin' | 'rest'; id: string } | null>(null);
const keyboardFocus = ref<string | null>(null);
const tooltip = ref<{ x: number; y: number } | null>(null);

let context: CanvasRenderingContext2D | null = null;
let width = 0;
let height = 0;
let pixelRatio = 1;
let camera: ZoomView = { x: 0, y: 0, w: 1000 };
let portrait = false;
let reducedMotion = false;
let measure: TextMeasure = estimateWidth;
let ellipsisWidth = estimateWidth('…');
let bubbles: Bubble[] = [];
let bySlug = new Map<string, Bubble>();
let linkedBubbles: Bubble[] = [];
let bounds = { minX: -500, minY: -300, maxX: 500, maxY: 300 };
let smallestRadius = 1;
let blooms: Bloom[] = [];
let sprites: HTMLCanvasElement | null = null;
let entranceStart = 0;
let morphStart = -Infinity;
let linkStart = 0;
let linkOwner: string | null = null;
let flight: { at: (t: number) => ZoomView; start: number; duration: number } | null = null;
let bloomWaiting = false;
let wholeMap = false;
let peeking = false;
let peekTimer: ReturnType<typeof setTimeout> | undefined;
let frameId = 0;
let lastFrame = 0;
let resizeObserver: ResizeObserver | null = null;
let homeCentre: { x: number; y: number } | null = null;
const layouts = new Map<SizeMetric, Promise<MapLayout>>();
let shownLayout: MapLayout | null = null;
let measured: Promise<void> = Promise.resolve();
let layoutWorker: Worker | null | undefined;
let nextRequest = 0;
const waitingLayouts = new Map<number, (settled: SettledLayout | null) => void>();

let adjacency: number[][] = [];

function buildAdjacency(): void {
    const linked: Array<Array<[number, number]>> = props.nodes.map(() => []);
    for (const [a, b, strength] of props.links) {
        linked[a]?.push([b, strength]);
        linked[b]?.push([a, strength]);
    }
    adjacency = linked.map((neighbours) =>
        neighbours
            .sort((x, y) => y[1] - x[1])
            .slice(0, MAX_RELATED)
            .map(([index]) => index),
    );
}

const listedNodes = computed(() => props.nodes.slice(0, LISTED_TAGS));

const activeSlug = computed(() => {
    if (props.focused) return props.focused;
    if (hovered.value?.kind === 'tag') return hovered.value.id;
    return keyboardFocus.value;
});

const pluginByName = computed(() => new Map((props.plugins ?? []).map((plugin) => [plugin.name, plugin])));

const tooltipContent = computed(() => {
    const target = hovered.value;
    if (!target) return null;
    if (target.kind === 'plugin') {
        const plugin = pluginByName.value.get(target.id);
        return plugin ? { value: `${formatNumber(plugin.installs)} installs`, name: plugin.label, isTag: false } : null;
    }
    if (target.kind === 'rest') {
        const rest = liveLevels(activeBloom())
            .flatMap((level) => level.cells)
            .find((cell) => cell.name === '' && cell.rest[0]?.name === target.id);
        return rest ? { value: `${formatNumber(rest.installs)} installs`, name: `${formatNumber(rest.rest.length)} more plugins`, isTag: false } : null;
    }
    const tag = bySlug.get(target.id)?.tag;
    if (!tag) return null;
    const plugins = `${formatNumber(tag.plugin_count)} ${tag.plugin_count === 1 ? 'plugin' : 'plugins'}`;
    return { value: `${plugins} · ${formatNumber(tag.total_installs)} installs`, name: tag.name, isTag: true };
});

const tooltipStyle = computed(() => {
    if (!tooltip.value) return {};
    const flip = tooltip.value.x > width - 240;
    return {
        left: `${tooltip.value.x}px`,
        top: `${tooltip.value.y}px`,
        transform: flip ? 'translate(calc(-100% - 14px), 14px)' : 'translate(14px, 14px)',
    };
});

function startWorker(): Worker | null {
    if (layoutWorker !== undefined) return layoutWorker;
    const failed = (): void => {
        layoutWorker?.terminate();
        layoutWorker = null;
        waitingLayouts.forEach((resolve) => resolve(null));
        waitingLayouts.clear();
    };
    try {
        layoutWorker = new Worker(new URL('../utils/tagMapLayout.worker.ts', import.meta.url), { type: 'module' });
        layoutWorker.onmessage = (event: MessageEvent<SettledLayout>) => {
            waitingLayouts.get(event.data.id)?.(event.data);
            waitingLayouts.delete(event.data.id);
        };
        layoutWorker.onerror = failed;
        const init: LayoutRequest = { nodes: toRaw(props.nodes), links: toRaw(props.links) };
        layoutWorker.postMessage(init);
    } catch {
        failed();
    }
    return layoutWorker ?? null;
}

function settleInWorker(metric: SizeMetric): Promise<SettledLayout | null> {
    const worker = startWorker();
    if (!worker) return Promise.resolve(null);
    const id = nextRequest++;
    return new Promise((resolve) => {
        waitingLayouts.set(id, resolve);
        const request: LayoutRequest = { id, metric };
        worker.postMessage(request);
    });
}

function layoutFor(metric: SizeMetric): Promise<MapLayout> {
    let layout = layouts.get(metric);
    if (!layout) {
        layout = Promise.all([settleInWorker(metric), measured]).then(async ([settled]) => {
            if (!settled) {
                const from = metric === 'plugin_count' ? undefined : await layoutFor('plugin_count');
                return layoutTags(props.nodes, props.links, metric, { from, measure });
            }
            const { positions, bounds } = settled;
            const tags = props.nodes.map((node, index): MapTag => {
                const r = positions[index * 3 + 2];
                return { ...node, index, x: positions[index * 3], y: positions[index * 3 + 1], r, label: fitLabel(node.name, r, measure) };
            });
            return { tags, bounds };
        });
        layouts.set(metric, layout);
    }
    return layout;
}

function applyLayout(layout: MapLayout, animate: boolean): void {
    shownLayout = layout;
    const oriented = layout.tags.map((tag) => (portrait ? { ...tag, x: tag.y, y: tag.x } : tag));
    const { minX, minY, maxX, maxY } = layout.bounds;
    bounds = portrait ? { minX: minY, minY: minX, maxX: maxY, maxY: maxX } : { minX, minY, maxX, maxY };
    smallestRadius = oriented.reduce((smallest, tag) => Math.min(smallest, tag.r), Infinity);

    if (bubbles.length !== oriented.length) {
        bubbles = oriented.map((tag) => ({
            tag,
            x: tag.x,
            y: tag.y,
            r: tag.r,
            fromX: tag.x,
            fromY: tag.y,
            fromR: tag.r,
            active: 0,
            related: 0,
            dim: 0,
            focus: 0,
            linked: 0,
            ...UNMEASURED,
        }));
    } else {
        bubbles.forEach((bubble, index) => {
            bubble.fromX = bubble.x;
            bubble.fromY = bubble.y;
            bubble.fromR = bubble.r;
            bubble.tag = oriented[index];
            if (!animate) {
                bubble.x = bubble.tag.x;
                bubble.y = bubble.tag.y;
                bubble.r = bubble.tag.r;
            }
        });
    }
    bySlug = new Map(bubbles.map((bubble) => [bubble.tag.slug, bubble]));
    morphStart = animate && !reducedMotion ? performance.now() : -Infinity;
}

function pixelsPerUnit(view: ZoomView = camera): number {
    return width / view.w;
}

function viewCentredOn(x: number, y: number, k: number): ZoomView {
    const regionCentreX = (width - props.insets.right) / 2;
    const regionCentreY = props.insets.top + (height - props.insets.top - props.insets.bottom) / 2;
    return { x: x + (width / 2 - regionCentreX) / k, y: y + (height / 2 - regionCentreY) / k, w: width / k };
}

function fitK(): number {
    const regionWidth = width - props.insets.right;
    const regionHeight = height - props.insets.top - props.insets.bottom;
    return Math.min(regionWidth / ((bounds.maxX - bounds.minX) * 1.04), regionHeight / ((bounds.maxY - bounds.minY) * 1.04));
}

function fitView(): ZoomView {
    return viewCentredOn((bounds.minX + bounds.maxX) / 2, (bounds.minY + bounds.maxY) / 2, fitK());
}

function homeView(): ZoomView {
    if (wholeMap) return fitView();
    const centre = homeCentre ?? { x: (bounds.minX + bounds.maxX) / 2, y: (bounds.minY + bounds.maxY) / 2 };
    const k = Math.max(fitK() * HOME_ZOOM, width < PHONE_WIDTH_PX ? HOME_MIN_K : 0);
    return clampView(viewCentredOn(centre.x, centre.y, k));
}

function focusK(r: number): number {
    const regionWidth = width - props.insets.right;
    const regionHeight = height - props.insets.top - props.insets.bottom;
    return (Math.min(regionWidth, regionHeight) * 0.88) / (2 * r);
}

function focusView(tag: MapTag): ZoomView {
    return viewCentredOn(tag.x, tag.y, focusK(tag.r));
}

function cellView(bubble: Bubble, cell: BloomCell): ZoomView {
    const radius = bubble.r * CELL_FILL;
    const xs = cell.polygon.map((point) => bubble.x + point.x * radius);
    const ys = cell.polygon.map((point) => bubble.y + point.y * radius);
    const [minX, maxX, minY, maxY] = [Math.min(...xs), Math.max(...xs), Math.min(...ys), Math.max(...ys)];
    const regionWidth = width - props.insets.right;
    const regionHeight = height - props.insets.top - props.insets.bottom;
    const k = Math.min(regionWidth / ((maxX - minX) * 1.15), regionHeight / ((maxY - minY) * 1.15));
    return viewCentredOn((minX + maxX) / 2, (minY + maxY) / 2, k);
}

function pairView(focus: Bubble, partner: Bubble): ZoomView | null {
    const minX = Math.min(focus.x - focus.r, partner.x - partner.r);
    const maxX = Math.max(focus.x + focus.r, partner.x + partner.r);
    const minY = Math.min(focus.y - focus.r, partner.y - partner.r);
    const maxY = Math.max(focus.y + focus.r, partner.y + partner.r);
    const regionWidth = width - props.insets.right;
    const regionHeight = height - props.insets.top - props.insets.bottom;
    const k = Math.min(regionWidth / ((maxX - minX) * 1.15), regionHeight / ((maxY - minY) * 1.15));
    return k >= focusK(focus.r) ? null : viewCentredOn((minX + maxX) / 2, (minY + maxY) / 2, k);
}

function peekAtLinked(): void {
    clearTimeout(peekTimer);
    peekTimer = setTimeout(() => {
        if (!ready.value) return;
        const focus = props.focused ? bySlug.get(props.focused) : undefined;
        const partner = props.linked ? bySlug.get(props.linked) : undefined;
        const view = focus && partner ? pairView(focus, partner) : null;
        if (view) {
            peeking = true;
            flyTo(view);
        } else if (peeking) {
            peeking = false;
            flyTo(targetView());
        }
    }, 160);
}

function targetView(): ZoomView {
    const focus = props.focused ? bySlug.get(props.focused) : undefined;
    if (!focus) return homeView();
    const opened = liveLevels(activeBloom()).at(-1)?.source;
    return opened ? cellView(focus, opened) : focusView(focus.tag);
}

function clampView(view: ZoomView): ZoomView {
    const base = fitK();
    const deepest = props.focused && bySlug.has(props.focused) ? width / targetView().w : 0;
    const k = Math.min(Math.max(width / view.w, base * MIN_ZOOM), Math.max(base * MAX_ZOOM, focusK(smallestRadius), deepest));
    return {
        x: Math.min(Math.max(view.x, bounds.minX), bounds.maxX),
        y: Math.min(Math.max(view.y, bounds.minY), bounds.maxY),
        w: width / k,
    };
}

function setCamera(view: ZoomView): void {
    camera = view;
    invalidate();
}

function flyTo(target: ZoomView): void {
    if (reducedMotion) {
        endFlight();
        setCamera(target);
        return;
    }
    const path = interpolateZoom(camera, target);
    flight = { at: path.at, start: performance.now(), duration: Math.min(Math.max(path.duration * 0.85, 420), 1300) };
    invalidate();
}

function endFlight(): void {
    flight = null;
    if (bloomWaiting) {
        bloomWaiting = false;
        syncBloom();
    }
    buildLevels();
}

function easeInOutCubic(t: number): number {
    return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
}

function easeOutBack(t: number): number {
    const overshoot = 1.3;
    const u = t - 1;
    return 1 + (overshoot + 1) * u * u * u + overshoot * u * u;
}

function progress(elapsed: number, duration: number): number {
    return Math.min(Math.max(elapsed / duration, 0), 1);
}

function invalidate(): void {
    if (!frameId && context) frameId = requestAnimationFrame(frame);
}

function frame(now: number): void {
    frameId = 0;
    const dt = lastFrame ? Math.min(now - lastFrame, 64) : 16;
    lastFrame = now;
    let animating = false;

    if (flight) {
        const t = progress(now - flight.start, flight.duration);
        camera = flight.at(easeInOutCubic(t));
        if (t < 1) {
            animating = true;
        } else {
            endFlight();
        }
    }

    animating = updateBubbles(now, dt) || animating;
    animating = draw(now) || animating;

    if (animating) {
        invalidate();
    } else {
        lastFrame = 0;
    }
}

function linkedSlug(active: Bubble | undefined): string | null {
    if (props.linked) return props.linked;
    const target = hovered.value;
    if (!active || target?.kind !== 'tag' || target.id === active.tag.slug) return null;
    const other = bySlug.get(target.id);
    return other && adjacency[active.tag.index]?.includes(other.tag.index) ? target.id : null;
}

function updateBubbles(now: number, dt: number): boolean {
    const morph = easeInOutCubic(progress(now - morphStart, 900));
    let animating = morph < 1;

    const active = activeSlug.value ? bySlug.get(activeSlug.value) : undefined;
    const related = new Set(active ? (adjacency[active.tag.index] ?? []) : []);
    const focused = props.focused;
    const linked = linkedSlug(active);
    linkedBubbles = [];
    const ease = reducedMotion ? 1 : 1 - Math.exp(-dt / 90);
    const approach = (value: number, target: number): number => {
        if (value === target) return value;
        const next = value + (target - value) * ease;
        if (Math.abs(target - next) <= 0.002) return target;
        animating = true;
        return next;
    };

    for (const bubble of bubbles) {
        bubble.x = bubble.fromX + (bubble.tag.x - bubble.fromX) * morph;
        bubble.y = bubble.fromY + (bubble.tag.y - bubble.fromY) * morph;
        bubble.r = bubble.fromR + (bubble.tag.r - bubble.fromR) * morph;

        const isActive = bubble === active;
        const isLinked = active !== undefined && !isActive && bubble.tag.slug === linked;
        const isRelated = related.has(bubble.tag.index) || isLinked;
        bubble.active = approach(bubble.active, isActive ? 1 : 0);
        bubble.related = approach(bubble.related, isRelated ? 1 : 0);
        bubble.dim = approach(bubble.dim, active && !isActive && !isRelated ? 1 : 0);
        bubble.focus = approach(bubble.focus, bubble.tag.slug === focused ? 1 : 0);
        bubble.linked = approach(bubble.linked, isLinked ? 1 : 0);
        if (bubble.linked > 0) linkedBubbles.push(bubble);
    }

    return animating;
}

const tone = new Float64Array(14);

function mixTone(bubble: { active: number; related: number; dim: number; focus: number }, base: Tone, highlight: Tone): Float64Array {
    tone.set(base);
    const blend = (target: Tone, amount: number): void => {
        if (amount <= 0.001) return;
        for (let i = 0; i < 14; i++) tone[i] += (target[i] - tone[i]) * amount;
    };
    blend(TONE.dim, bubble.dim);
    blend(TONE.related, bubble.related);
    blend(highlight, bubble.active);
    blend(TONE.focus, bubble.focus);
    return tone;
}

function rgb(t: Float64Array | Tone, offset: number, alpha = 1): string {
    return `rgba(${Math.round(t[offset])}, ${Math.round(t[offset + 1])}, ${Math.round(t[offset + 2])}, ${alpha})`;
}

function paintCircle(ctx: CanvasRenderingContext2D, x: number, y: number, r: number, t: Float64Array | Tone, halo: number): void {
    if (halo > 0.02) {
        ctx.beginPath();
        ctx.arc(x, y, r + 4, 0, TAU);
        ctx.lineWidth = 6;
        ctx.strokeStyle = `rgba(255, 108, 33, ${0.2 * halo})`;
        ctx.stroke();
    }

    ctx.beginPath();
    ctx.arc(x, y, r, 0, TAU);
    if (r < 4) {
        ctx.fillStyle = rgb(t, 0);
    } else {
        const gradient = ctx.createRadialGradient(x - r * 0.35, y - r * 0.4, r * 0.08, x, y, r);
        gradient.addColorStop(0, rgb(t, 0));
        gradient.addColorStop(1, rgb(t, 3));
        ctx.fillStyle = gradient;
    }
    ctx.fill();

    if (t[9] > 0.01 && r > 3) {
        ctx.lineWidth = t[10];
        ctx.strokeStyle = rgb(t, 6, t[9]);
        ctx.stroke();
    }
}

function buildSprites(): HTMLCanvasElement | null {
    const atlas = document.createElement('canvas');
    const variants = 2 * (DIM_STEPS + 1);
    atlas.width = variants * SPRITE_CELLS[SPRITE_CELLS.length - 1];
    atlas.height = SPRITE_CELLS.reduce((sum, cell) => sum + cell, 0);
    const ctx = atlas.getContext('2d');
    if (!ctx) return null;

    SPRITE_CELLS.forEach((cell, level) => {
        for (let variant = 0; variant < variants; variant++) {
            const named = variant > DIM_STEPS;
            const dim = (variant % (DIM_STEPS + 1)) / DIM_STEPS;
            const t = mixTone({ active: 0, related: 0, dim, focus: 0 }, named ? TONE.base : TONE.quiet, TONE.active);
            const x = variant * cell + cell / 2;
            const y = SPRITE_ROWS[level] + cell / 2;
            if (cell <= 16) {
                ctx.beginPath();
                ctx.arc(x, y, cell / 2 - 1, 0, TAU);
                ctx.fillStyle = rgb(t, 0);
                ctx.fill();
            } else {
                paintCircle(ctx, x, y, cell / 2 - 1, t, 0);
            }
        }
    });
    return atlas;
}

function paintSprite(ctx: CanvasRenderingContext2D, named: boolean, dim: number, x: number, y: number, r: number): boolean {
    if (!sprites) return false;
    const diameter = 2 * r * pixelRatio;
    const level = SPRITE_CELLS.findIndex((cell) => cell >= diameter);
    if (level < 0) return false;
    const cell = SPRITE_CELLS[level];
    const variant = (named ? DIM_STEPS + 1 : 0) + Math.round(dim * DIM_STEPS);
    const size = (cell / (cell / 2 - 1)) * r;
    ctx.drawImage(sprites, variant * cell, SPRITE_ROWS[level], cell, cell, x - size / 2, y - size / 2, size, size);
    return true;
}

function shortName(target: ShortName, name: string, radius: number): string | null {
    if (radius < MIN_LABEL_PX * SHORT_MIN_RADIUS) return null;
    if (!target.chars || !target.widths) {
        const chars = Array.from(name);
        const widths = new Float32Array(chars.length + 1);
        chars.forEach((char, index) => (widths[index + 1] = widths[index] + measure(char) * 1.04));
        target.chars = chars;
        target.widths = widths;
    }
    const { chars, widths } = target;
    const room = (radius * SHORT_WIDTH) / MIN_LABEL_PX;
    if (widths[chars.length] <= room) return name;

    const limit = room - ellipsisWidth;
    let low = 0;
    let high = chars.length;
    while (low < high) {
        const middle = (low + high + 1) >> 1;
        if (widths[middle] <= limit) {
            low = middle;
        } else {
            high = middle - 1;
        }
    }
    if (low < MIN_SHORT_CHARS) return null;
    if (target.shortLength !== low) {
        target.shortLength = low;
        target.shortText = `${chars.slice(0, low).join('').trimEnd()}…`;
    }
    return target.shortText;
}

let currentFont = '';

function setFont(ctx: CanvasRenderingContext2D, px: number): void {
    const font = `${px > 22 ? 600 : 500} ${px.toFixed(1)}px Poppins, ui-sans-serif, sans-serif`;
    if (font !== currentFont) {
        ctx.font = font;
        currentFont = font;
    }
}

function paintLines(ctx: CanvasRenderingContext2D, lines: string[], x: number, y: number, px: number, fill: string): void {
    setFont(ctx, px);
    ctx.fillStyle = fill;
    lines.forEach((line, index) => {
        ctx.fillText(line, x, y + (index - (lines.length - 1) / 2) * px * 1.08);
    });
}

function paintLabel(
    ctx: CanvasRenderingContext2D,
    label: MapLabel,
    x: number,
    y: number,
    pixelsPerWorld: number,
    t: Float64Array | Tone,
    alpha: number,
    capAt: number,
): void {
    const px = Math.min(label.fontSize * pixelsPerWorld, capAt);
    if (alpha <= 0.01 || px < MIN_LABEL_PX) return;
    paintLines(ctx, label.lines, x, y, px, rgb(t, 11, alpha));
}

function traceRounded(ctx: CanvasRenderingContext2D, points: Point[], corner: number): void {
    const count = points.length;
    const last = points[count - 1];
    ctx.moveTo((last.x + points[0].x) / 2, (last.y + points[0].y) / 2);
    for (let i = 0; i < count; i++) {
        const point = points[i];
        const next = points[(i + 1) % count];
        const previous = points[(i + count - 1) % count];
        const toPrevious = Math.hypot(previous.x - point.x, previous.y - point.y);
        const toNext = Math.hypot(next.x - point.x, next.y - point.y);
        if (toPrevious < 1e-6 || toNext < 1e-6) {
            ctx.lineTo(point.x, point.y);
            continue;
        }
        const cosine = ((previous.x - point.x) * (next.x - point.x) + (previous.y - point.y) * (next.y - point.y)) / (toPrevious * toNext);
        const halfAngle = Math.acos(Math.min(Math.max(cosine, -1), 1)) / 2;
        ctx.arcTo(point.x, point.y, next.x, next.y, Math.min(corner, (Math.tan(halfAngle) * Math.min(toPrevious, toNext)) / 2));
    }
    ctx.closePath();
}

function paintCell(ctx: CanvasRenderingContext2D, points: Point[], x: number, y: number, inner: number, t: Tone): void {
    ctx.beginPath();
    traceRounded(ctx, points, Math.min(inner * 0.45, 16));
    const reach = inner * 2 + 2;
    const gradient = ctx.createRadialGradient(x - inner * 0.3, y - inner * 0.35, inner * 0.05, x, y, reach);
    gradient.addColorStop(0, rgb(t, 0));
    gradient.addColorStop(1, rgb(t, 3));
    ctx.fillStyle = gradient;
    ctx.fill();
    ctx.lineWidth = 2.5;
    ctx.strokeStyle = MEMBRANE;
    ctx.stroke();
    ctx.lineWidth = t[10];
    ctx.strokeStyle = rgb(t, 6, t[9]);
    ctx.stroke();
}

function draw(now: number): boolean {
    const ctx = context;
    if (!ctx) return false;
    let animating = false;

    ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
    ctx.clearRect(0, 0, width, height);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    currentFont = '';

    const k = pixelsPerUnit();
    const originX = width / 2 - camera.x * k;
    const originY = height / 2 - camera.y * k;
    const toScreen = (bubble: Bubble): { x: number; y: number; r: number } => ({ x: originX + bubble.x * k, y: originY + bubble.y * k, r: bubble.r * k });
    const onScreen = (x: number, y: number, r: number): boolean => x + r > -8 && x - r < width + 8 && y + r > -8 && y - r < height + 8;
    const labels: PendingLabel[] = [];

    const growOf = (bubble: Bubble): number => {
        if (reducedMotion) return 1;
        const t = progress(now - entranceStart - Math.min(bubble.tag.index, 180) * 3, 650);
        if (t < 1) animating = true;
        return t <= 0 ? 0 : easeOutBack(t);
    };

    const paintBubble = (bubble: Bubble, x: number, y: number, r: number, scale: number, plain: boolean): void => {
        const lift = 1 + 0.05 * bubble.active * (1 - bubble.focus);
        const label = bubble.tag.label;
        const px = label ? Math.min(label.fontSize * scale * lift, MAX_LABEL_PX) : 0;
        const short = px >= MIN_LABEL_PX ? null : shortName(bubble, bubble.tag.name, r * lift);
        const named = px >= MIN_LABEL_PX || short !== null;
        if (!plain || !paintSprite(ctx, named, bubble.dim, x, y, r)) {
            paintCircle(ctx, x, y, r * lift, mixTone(bubble, named ? TONE.base : TONE.quiet, TONE.active), Math.max(bubble.active, bubble.focus));
        }

        const alpha = 1 - bubble.focus;
        if (alpha <= 0.01 || !named) return;
        const t = mixTone(bubble, TONE.base, TONE.active);
        if (label && px >= MIN_LABEL_PX) {
            labels.push({ lines: label.lines, x, y, px, fill: rgb(t, 11, alpha) });
        } else if (short) {
            labels.push({ lines: [short], x, y, px: MIN_LABEL_PX, fill: rgb(t, 11, alpha) });
        }
    };

    const flushLabels = (): void => {
        for (const label of labels) paintLines(ctx, label.lines, label.x, label.y, label.px, label.fill);
        labels.length = 0;
    };

    const highlighted: Bubble[] = [];
    for (const bubble of bubbles) {
        if (bubble.active > 0.01 || bubble.related > 0.01 || bubble.focus > 0.01) {
            highlighted.push(bubble);
            continue;
        }
        const grow = growOf(bubble);
        if (grow <= 0) continue;
        const x = originX + bubble.x * k;
        const y = originY + bubble.y * k;
        const r = bubble.r * k * grow;
        if (onScreen(x, y, r)) paintBubble(bubble, x, y, r, k * grow, true);
    }
    flushLabels();

    const raised: Bubble[] = [];
    let paintLink: ((other: Bubble) => void) | null = null;
    const owner = activeSlug.value ? bySlug.get(activeSlug.value) : undefined;
    if (owner) {
        if (linkOwner !== owner.tag.slug) {
            linkOwner = owner.tag.slug;
            linkStart = now;
        }
        const reach = reducedMotion ? 1 : 1 - Math.pow(1 - progress(now - linkStart, 380), 3);
        if (reach < 1) animating = true;
        const from = toScreen(owner);
        const partners = adjacency[owner.tag.index] ?? [];
        const emphasis = linkedBubbles.reduce((most, bubble) => Math.max(most, bubble.linked), 0);
        const lines = [
            ...partners.map((index) => bubbles[index]),
            ...linkedBubbles.filter((bubble) => bubble !== owner && !partners.includes(bubble.tag.index)),
        ];
        const restingAlpha = 0.75 * (1 - 0.55 * emphasis);
        ctx.lineCap = 'round';
        paintLink = (other: Bubble): void => {
            const lift = other.linked;
            const shown = partners.includes(other.tag.index) ? 1 : lift;
            if (shown <= 0.01) return;
            const to = toScreen(other);
            const dx = to.x - from.x;
            const dy = to.y - from.y;
            const distance = Math.hypot(dx, dy);
            const gapStart = from.r + 3;
            const gapEnd = to.r + 3;
            if (distance <= gapStart + gapEnd) return;
            const ux = dx / distance;
            const uy = dy / distance;
            const startX = from.x + ux * gapStart;
            const startY = from.y + uy * gapStart;
            const length = (distance - gapStart - gapEnd) * reach * shown;
            const alpha = restingAlpha + (1 - restingAlpha) * lift;
            ctx.beginPath();
            ctx.moveTo(startX, startY);
            ctx.lineTo(startX + ux * length, startY + uy * length);
            ctx.lineWidth = 1.5 + 2.5 * lift;
            ctx.strokeStyle = `rgba(255, 108, 33, ${alpha})`;
            ctx.stroke();
            if (reach >= 1 && shown >= 0.99) {
                ctx.beginPath();
                ctx.arc(startX + ux * length, startY + uy * length, 2.5 + 1.5 * lift, 0, TAU);
                ctx.fillStyle = `rgba(255, 108, 33, ${Math.max(alpha, 0.9)})`;
                ctx.fill();
            }
        };
        for (const other of lines) {
            if (!other) continue;
            if (other.linked > 0.01) {
                raised.push(other);
            } else {
                paintLink(other);
            }
        }
    } else {
        linkOwner = null;
    }

    for (const bubble of highlighted) {
        const grow = growOf(bubble);
        if (grow <= 0) continue;
        const screen = toScreen(bubble);
        if (onScreen(screen.x, screen.y, screen.r * grow * 1.05)) paintBubble(bubble, screen.x, screen.y, screen.r * grow, k * grow, false);
    }
    flushLabels();
    for (const other of raised) paintLink?.(other);

    const focus = props.focused ? bySlug.get(props.focused) : undefined;
    if (focus && props.plugins === null && !reducedMotion) {
        const screen = toScreen(focus);
        ctx.beginPath();
        ctx.arc(screen.x, screen.y, screen.r + 4, 0, TAU);
        ctx.lineWidth = 6;
        ctx.strokeStyle = `rgba(255, 108, 33, ${0.12 + 0.25 * (0.5 + 0.5 * Math.sin(now / 140))})`;
        ctx.stroke();
        animating = true;
    }

    blooms = blooms.filter((bloom) => bloom.leaving === null || now - bloom.leaving < 300);
    for (const bloom of blooms) {
        const bubble = bySlug.get(bloom.slug);
        if (!bubble) continue;
        const centre = toScreen(bubble);
        const radius = centre.r * CELL_FILL;
        if (!onScreen(centre.x, centre.y, radius)) continue;
        const collapse = bloom.leaving === null ? 1 : 1 - easeInOutCubic(progress(now - bloom.leaving, 280));
        if (bloom.leaving !== null) animating = true;
        bloom.levels = bloom.levels.filter((level) => level.leaving === null || now - level.leaving < 300);

        bloom.levels.forEach((level, depth) => {
            const shrink = level.leaving === null ? collapse : collapse * (1 - easeInOutCubic(progress(now - level.leaving, 280)));
            if (level.leaving !== null) animating = true;
            const opened = bloom.levels[depth + 1]?.source ?? null;

            for (const cell of level.cells) {
                if (cell.polygon.length < 3) continue;
                const t = reducedMotion ? 1 : progress(now - level.start - cell.delay, 650);
                if (t < 1) animating = true;
                if (t <= 0) continue;
                const grow = Math.max(easeOutBack(t) * shrink, 0);
                if (grow < 0.02) continue;
                const x = centre.x + cell.x * radius;
                const y = centre.y + cell.y * radius;
                const points = cell.polygon.map((point) => ({ x: x + (centre.x + point.x * radius - x) * grow, y: y + (centre.y + point.y * radius - y) * grow }));
                const isRest = cell.name === '';
                const isOpen = cell === opened;
                const isHighlighted = isRest
                    ? !isOpen && ((hovered.value?.kind === 'rest' && hovered.value.id === cell.rest[0]?.name) || cell.rest.some((plugin) => plugin.name === props.highlighted))
                    : cell.name === props.highlighted || (hovered.value?.kind === 'plugin' && hovered.value.id === cell.name);
                const cellTone = isRest ? (isHighlighted ? TONE.restActive : TONE.rest) : isHighlighted ? TONE.pluginActive : TONE.plugin;
                paintCell(ctx, points, x, y, cell.inner * radius * grow, cellTone);
                if (isOpen) continue;

                const alpha = Math.min(grow, 1);
                const px = cell.text ? Math.min(cell.text.fontSize * radius * grow, MAX_CELL_LABEL_PX) : 0;
                if (cell.text && px >= MIN_LABEL_PX) {
                    paintLabel(ctx, cell.text, x, y, radius * grow, cellTone, alpha, MAX_CELL_LABEL_PX);
                } else {
                    const short = shortName(cell, cell.label, cell.inner * radius * grow);
                    if (short) paintLines(ctx, [short], x, y, MIN_LABEL_PX, rgb(cellTone, 11, alpha));
                }
            }
        });
    }

    return animating;
}

function buildCells(plugins: MapPlugin[], boundary?: Point[]): BloomCell[] {
    const total = plugins.reduce((sum, plugin) => sum + Math.max(plugin.installs, 1), 0);
    let kept = plugins.filter((plugin) => plugin.installs / total >= CELL_MIN_SHARE).slice(0, MAX_CELLS);
    let rest = plugins.slice(kept.length);
    if (rest.length <= 2) {
        kept = plugins;
        rest = [];
    }
    const restInstalls = rest.reduce((sum, plugin) => sum + plugin.installs, 0);
    const values = kept.map((plugin) => Math.max(plugin.installs, total * CELL_MIN_SHARE));
    if (rest.length) values.push(Math.max(restInstalls, total * 0.015));

    const shapes = cellsIn(values, boundary);
    const cells: BloomCell[] = kept.map((plugin, index) => ({
        ...shapes[index],
        ...UNMEASURED,
        name: plugin.name,
        label: plugin.label,
        installs: plugin.installs,
        rest: [],
        text: fitLabel(plugin.label, shapes[index].inner, measure),
        delay: Math.min(index * 14, 520),
    }));
    if (rest.length) {
        const shape = shapes[shapes.length - 1];
        const label = `+${rest.length} more`;
        cells.push({
            ...shape,
            ...UNMEASURED,
            name: '',
            label,
            installs: restInstalls,
            rest,
            text: fitLabel(label, shape.inner, measure),
            delay: Math.min(kept.length * 14, 520),
        });
    }
    return cells;
}

function activeBloom(): Bloom | undefined {
    return blooms.find((bloom) => bloom.leaving === null);
}

function liveLevels(bloom: Bloom | undefined): CellLevel[] {
    return bloom?.levels.filter((level) => level.leaving === null) ?? [];
}

function buildLevels(): void {
    for (const level of liveLevels(activeBloom())) {
        if (level.cells.length || !level.source) continue;
        level.cells = buildCells(level.source.rest, level.source.polygon);
        level.start = performance.now();
    }
    invalidate();
}

function openRest(): void {
    const levels = liveLevels(activeBloom());
    const source = levels.at(-1)?.cells.find((cell) => cell.name === '' && cell.polygon.length > 2);
    if (!source) return;
    activeBloom()!.levels.push({ source, cells: [], start: 0, leaving: null });
    emit('more', source.rest[0]?.name ?? '');
    flyTo(targetView());
    if (!flight) buildLevels();
}

function collapse(): boolean {
    const levels = liveLevels(activeBloom());
    if (levels.length < 2) return false;
    levels[levels.length - 1].leaving = performance.now();
    flyTo(targetView());
    return true;
}

function syncBloom(): void {
    const now = performance.now();
    const current = activeBloom();
    const wanted = props.focused && props.plugins?.length ? props.focused : null;
    const shown = current?.levels[0].cells.reduce((sum, cell) => sum + Math.max(cell.rest.length, 1), 0);

    if (current && (current.slug !== wanted || shown !== props.plugins?.length)) {
        current.leaving = now;
    }
    if (wanted && props.plugins && (!current || current.leaving !== null)) {
        if (!bySlug.has(wanted)) {
            bloomWaiting = false;
        } else if (flight && !reducedMotion) {
            bloomWaiting = true;
        } else {
            blooms.push({ slug: wanted, leaving: null, levels: [{ source: null, cells: buildCells(props.plugins), start: now, leaving: null }] });
        }
    }
    invalidate();
}

function toWorld(point: { x: number; y: number }, view: ZoomView = camera): { x: number; y: number } {
    const k = pixelsPerUnit(view);
    return { x: view.x + (point.x - width / 2) / k, y: view.y + (point.y - height / 2) / k };
}

type Hit = { kind: 'tag' | 'plugin'; id: string } | { kind: 'rest'; id: string; deepest: boolean } | { kind: 'inside' } | null;

function hitTest(point: { x: number; y: number }): Hit {
    const world = toWorld(point);
    const tolerance = TAP_TOLERANCE_PX / pixelsPerUnit();
    const focus = props.focused ? bySlug.get(props.focused) : undefined;

    if (focus && Math.hypot(world.x - focus.x, world.y - focus.y) <= focus.r) {
        const bloom = activeBloom();
        const levels = bloom?.slug === focus.tag.slug ? liveLevels(bloom) : [];
        const radius = focus.r * CELL_FILL;
        const x = (world.x - focus.x) / radius;
        const y = (world.y - focus.y) / radius;
        for (let depth = 0; depth < levels.length; depth++) {
            const cell = levels[depth].cells.find((candidate) => candidate.polygon.length > 2 && insidePolygon(candidate.polygon, x, y));
            if (!cell) break;
            if (cell.name) return { kind: 'plugin', id: cell.name };
            if (levels[depth + 1]?.cells.length) continue;
            return { kind: 'rest', id: cell.rest[0]?.name ?? '', deepest: depth === levels.length - 1 };
        }
        return { kind: 'inside' };
    }

    let nearest: Bubble | null = null;
    let nearestGap = tolerance;
    for (const bubble of bubbles) {
        const gap = Math.hypot(world.x - bubble.x, world.y - bubble.y) - bubble.r;
        if (gap <= 0) return { kind: 'tag', id: bubble.tag.slug };
        if (gap < nearestGap) {
            nearest = bubble;
            nearestGap = gap;
        }
    }
    return nearest ? { kind: 'tag', id: nearest.tag.slug } : null;
}

const pointers = new Map<number, { x: number; y: number }>();
let gesture:
    | { kind: 'pan'; start: ZoomView; origin: { x: number; y: number }; moved: boolean }
    | { kind: 'pinch'; start: ZoomView; mid: { x: number; y: number }; distance: number }
    | null = null;

function localPoint(event: PointerEvent | WheelEvent): { x: number; y: number } {
    const rect = root.value!.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
}

function onPointerDown(event: PointerEvent): void {
    if (!ready.value || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey) return;
    root.value?.setPointerCapture(event.pointerId);
    pointers.set(event.pointerId, localPoint(event));
    endFlight();

    if (pointers.size === 1) {
        gesture = { kind: 'pan', start: { ...camera }, origin: localPoint(event), moved: false };
    } else if (pointers.size === 2) {
        const [a, b] = [...pointers.values()];
        gesture = {
            kind: 'pinch',
            start: { ...camera },
            mid: { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 },
            distance: Math.max(Math.hypot(a.x - b.x, a.y - b.y), 1),
        };
    }
}

function onPointerMove(event: PointerEvent): void {
    if (!ready.value) return;
    const point = localPoint(event);

    if (!pointers.has(event.pointerId)) {
        if (event.pointerType === 'mouse') updateHover(point);
        return;
    }
    pointers.set(event.pointerId, point);

    if (gesture?.kind === 'pan') {
        const dx = point.x - gesture.origin.x;
        const dy = point.y - gesture.origin.y;
        if (!gesture.moved && Math.hypot(dx, dy) < DRAG_THRESHOLD_PX) return;
        gesture.moved = true;
        hovered.value = null;
        tooltip.value = null;
        const k = pixelsPerUnit(gesture.start);
        setCamera(clampView({ x: gesture.start.x - dx / k, y: gesture.start.y - dy / k, w: gesture.start.w }));
    } else if (gesture?.kind === 'pinch' && pointers.size >= 2) {
        const [a, b] = [...pointers.values()];
        const mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
        const anchor = toWorld(gesture.mid, gesture.start);
        const w = (gesture.start.w * gesture.distance) / Math.max(Math.hypot(a.x - b.x, a.y - b.y), 1);
        const k = width / w;
        setCamera(clampView({ x: anchor.x - (mid.x - width / 2) / k, y: anchor.y - (mid.y - height / 2) / k, w }));
    }
}

function onPointerUp(event: PointerEvent): void {
    if (!pointers.has(event.pointerId)) return;
    const point = localPoint(event);
    const wasTap = gesture?.kind === 'pan' && !gesture.moved && pointers.size === 1;
    pointers.delete(event.pointerId);

    if (pointers.size === 1 && gesture?.kind === 'pinch') {
        const [remaining] = [...pointers.values()];
        gesture = { kind: 'pan', start: { ...camera }, origin: remaining, moved: true };
    }
    if (pointers.size === 0) gesture = null;
    if (wasTap && event.type === 'pointerup') tap(point, event.pointerType === 'mouse');
}

function tap(point: { x: number; y: number }, byMouse: boolean): void {
    const hit = hitTest(point);
    if (hit === null) {
        if (props.focused) emit('clear');
        return;
    }
    if (hit.kind === 'rest') {
        if (hit.deepest) openRest();
    } else if (hit.kind === 'plugin') {
        emit('selectPlugin', hit.id, byMouse);
    } else if (hit.kind === 'tag' && hit.id !== props.focused) {
        emit('select', hit.id);
    }
}

function updateHover(point: { x: number; y: number }): void {
    const hit = hitTest(point);
    const next = hit && hit.kind !== 'inside' ? hit : null;
    if (next?.id !== hovered.value?.id) {
        hovered.value = next;
        emit('hoverTag', next?.kind === 'tag' ? next.id : null);
    }
    tooltip.value = next ? point : null;
}

function onPointerLeave(): void {
    if (pointers.size > 0) return;
    hovered.value = null;
    tooltip.value = null;
    emit('hoverTag', null);
}

function onWheel(event: WheelEvent): void {
    if (!ready.value) return;
    endFlight();
    const point = localPoint(event);
    const pixels = event.deltaMode === 1 ? event.deltaY * 16 : event.deltaY;
    const factor = Math.exp(pixels * (event.ctrlKey ? 0.01 : 0.0018));
    const anchor = toWorld(point);
    const w = camera.w * factor;
    const k = width / w;
    setCamera(clampView({ x: anchor.x - (point.x - width / 2) / k, y: anchor.y - (point.y - height / 2) / k, w }));
    updateHover(point);
}

function onTagKey(event: MouseEvent, slug: string): void {
    if (event.ctrlKey || event.metaKey || event.shiftKey) return;
    event.preventDefault();
    emit('select', slug);
}

function zoomBy(factor: number): void {
    flyTo(clampView({ ...camera, w: camera.w / factor }));
}

function resetView(): void {
    homeCentre = null;
    wholeMap = true;
    if (props.focused) {
        emit('clear');
    } else {
        flyTo(fitView());
    }
}

defineExpose({ zoomBy, resetView, collapse });

function measureCanvas(): void {
    const el = root.value;
    const cv = canvas.value;
    if (!el || !cv) return;
    width = el.clientWidth;
    height = el.clientHeight;
    pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
    cv.width = Math.round(width * pixelRatio);
    cv.height = Math.round(height * pixelRatio);
}

async function loadMeasure(): Promise<void> {
    try {
        await Promise.race([document.fonts.load('500 16px Poppins'), new Promise((resolve) => setTimeout(resolve, 1500))]);
    } catch {
        return;
    }
    const probe = document.createElement('canvas').getContext('2d');
    if (!probe) return;
    probe.font = '500 100px Poppins, ui-sans-serif, sans-serif';
    const widths = new Map<string, number>();
    measure = (text) => {
        let known = widths.get(text);
        if (known === undefined) {
            known = probe.measureText(text).width / 100;
            widths.set(text, known);
        }
        return known;
    };
    ellipsisWidth = measure('…');
}

watch(activeSlug, invalidate);
watch(() => props.highlighted, invalidate);
watch(
    () => props.linked,
    () => {
        invalidate();
        peekAtLinked();
    },
);
watch(hovered, invalidate);

watch(
    () => props.focused,
    (focused, previous) => {
        const left = previous ? bySlug.get(previous) : undefined;
        if (left) homeCentre = { x: left.x, y: left.y };
        if (focused) wholeMap = false;
        peeking = false;
        clearTimeout(peekTimer);
        if (ready.value) flyTo(targetView());
        syncBloom();
    },
);

watch(() => props.plugins, syncBloom);

watch(
    () => [props.insets.top, props.insets.right, props.insets.bottom],
    () => {
        if (ready.value) flyTo(targetView());
    },
);

watch(
    () => props.metric,
    async (metric) => {
        if (!ready.value) return;
        const layout = await layoutFor(metric);
        if (metric !== props.metric || !context) return;
        applyLayout(layout, true);
        flyTo(targetView());
    },
);

onMounted(async () => {
    reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    context = canvas.value?.getContext('2d') ?? null;
    sprites = buildSprites();
    measureCanvas();
    portrait = height > width * 1.05;
    measured = loadMeasure();
    buildAdjacency();

    const layout = await layoutFor(props.metric);
    if (!context) return;
    applyLayout(layout, false);
    const settled = homeView();
    camera = reducedMotion ? settled : { ...settled, w: settled.w * 1.12 };
    entranceStart = performance.now();
    ready.value = true;
    flyTo(settled);
    syncBloom();

    if (layoutWorker) layoutFor(props.metric === 'plugin_count' ? 'total_installs' : 'plugin_count');

    resizeObserver = new ResizeObserver(() => {
        const [previousWidth, previousHeight] = [width, height];
        measureCanvas();
        if (width === previousWidth && height === previousHeight) return;
        const nextPortrait = height > width * 1.05;
        if (nextPortrait !== portrait && shownLayout) {
            portrait = nextPortrait;
            applyLayout(shownLayout, false);
        }
        endFlight();
        camera = clampView(targetView());
        invalidate();
    });
    if (root.value) resizeObserver.observe(root.value);
});

onBeforeUnmount(() => {
    cancelAnimationFrame(frameId);
    clearTimeout(peekTimer);
    layoutWorker?.terminate();
    resizeObserver?.disconnect();
    context = null;
});
</script>

<template>
    <div
        ref="root"
        class="tag-map"
        :class="{ 'tag-map--ready': ready, 'tag-map--hovering': !!hovered }"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
        @pointerleave="onPointerLeave"
        @wheel.prevent="onWheel"
    >
        <canvas ref="canvas" class="tag-map__canvas" aria-hidden="true" />

        <ul class="tag-map__index" aria-label="Most used tags on the map">
            <li v-for="node in listedNodes" :key="node.slug">
                <a
                    :href="tagShow.url(node.slug)"
                    @click="onTagKey($event, node.slug)"
                    @focus="keyboardFocus = node.slug"
                    @blur="keyboardFocus = null"
                >{{ node.name }}: {{ node.plugin_count }} plugins</a>
            </li>
        </ul>

        <div v-if="tooltip && tooltipContent" class="tag-map__tooltip" :style="tooltipStyle">
            <span class="tag-map__tooltip-value">{{ tooltipContent.value }}</span>
            <span class="tag-map__tooltip-name" :class="{ 'tag-map__tooltip-name--tag': tooltipContent.isTag }">{{ tooltipContent.name }}</span>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.tag-map {
    @apply relative h-full w-full overflow-hidden select-none;
    touch-action: none;
    cursor: grab;
    background:
        radial-gradient(ellipse at 50% 42%, rgba(255, 108, 33, 0.08), transparent 62%),
        radial-gradient(ellipse at 50% 50%, transparent 55%, rgba(0, 0, 0, 0.35) 100%);
}

.tag-map:active {
    cursor: grabbing;
}

.tag-map--hovering {
    cursor: pointer;
}

.tag-map__canvas {
    @apply absolute inset-0 block h-full w-full opacity-0 transition-opacity duration-300;
}

.tag-map--ready .tag-map__canvas {
    @apply opacity-100;
}

.tag-map__index {
    @apply sr-only;
}

.tag-map__tooltip {
    @apply pointer-events-none absolute z-20 flex flex-col rounded-lg px-2.5 py-1.5 text-xs whitespace-nowrap shadow-lg;
    background: rgba(13, 13, 13, 0.94);
    border: 1px solid rgba(255, 108, 33, 0.35);
}

.tag-map__tooltip-value {
    @apply font-semibold tabular-nums text-white;
}

.tag-map__tooltip-name {
    @apply text-gray-400;
}

.tag-map__tooltip-name--tag {
    @apply capitalize;
}
</style>
