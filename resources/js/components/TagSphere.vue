<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Canvas-rendered 3D word sphere. The top LABEL_COUNT items by weight render
 * as text (log-scaled font size); the rest render as small dots. Labels are
 * laid out with collision-aware packing on the sphere surface - biggest
 * first, each claiming an elliptical angular footprint sized to its rendered
 * width/height - so words neither overlap nor leave random gaps. Emits
 * `select` with the item id when a label is clicked.
 */
const props = defineProps<{
    items: { id: string; name: string; weight: number }[];
}>();

const emit = defineEmits<{
    select: [id: string];
}>();

const container = ref<HTMLElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const hoveredSlug = ref<string | null>(null);

const LABEL_COUNT = 600;
const MIN_FONT_PX = 12;
const MAX_FONT_PX = 66;
const SIZE_CONTRAST = 1.5;
const MIN_DOT_PX = 0.8;
const MAX_DOT_PX = 2.6;
/** Average glyph width as a fraction of font size (Poppins medium). */
const GLYPH_WIDTH = 0.58;
/**
 * Words claim an elliptical footprint: wide (text width) but short (font
 * height), so rows of words stack tightly above/below each other while
 * still keeping left/right clearance. <1 packs tighter, >1 spreads.
 */
const PACKING_X = 1.05;
const PACKING_Y = 0.85;
const CANDIDATE_COUNT = 6000;
const IDLE_VELOCITY_Y = 0.0022;
const POINTER_SPEED = 0.009;
const GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5));

interface SphereTag {
    slug: string;
    name: string;
    isLabel: boolean;
    fontPx: number;
    dotPx: number;
    baseX: number;
    baseY: number;
    baseZ: number;
}

interface HitBox {
    x: number;
    y: number;
    w: number;
    h: number;
    slug: string;
}

let sphereTags: SphereTag[] = [];
let hitBoxes: HitBox[] = [];
let frame = 0;
let angleX = -0.3;
let angleY = 0;
let velocityX = 0;
let velocityY = IDLE_VELOCITY_Y;
let pointerInside = false;
let pointerX = 0;
let pointerY = 0;
let width = 0;
let height = 0;
let dpr = 1;
let lastLayoutRadius = 0;
let resizeObserver: ResizeObserver | null = null;
let reducedMotion = false;

function fibonacciPoints(count: number): { x: number; y: number; z: number }[] {
    return Array.from({ length: count }, (_, i) => {
        const y = 1 - (2 * (i + 0.5)) / count;
        const r = Math.sqrt(Math.max(1 - y * y, 0));
        const theta = i * GOLDEN_ANGLE;
        return { x: r * Math.cos(theta), y, z: r * Math.sin(theta) };
    });
}

function sphereRadius(): number {
    return Math.min(width * 0.44, height * 0.5);
}

function layoutSphere(): void {
    const radiusPx = sphereRadius();
    if (radiusPx <= 0 || props.items.length === 0) return;
    lastLayoutRadius = radiusPx;

    const ranked = [...props.items].sort((a, b) => b.weight - a.weight);
    const labelTags = ranked.slice(0, LABEL_COUNT);
    const dotTags = ranked.slice(LABEL_COUNT);

    const labelLogs = labelTags.map((tag) => Math.log(tag.weight + 1));
    const labelLo = Math.min(...labelLogs);
    const labelSpan = Math.max(Math.max(...labelLogs) - labelLo, 1e-9);

    const allLogs = props.items.map((tag) => Math.log(tag.weight + 1));
    const allLo = Math.min(...allLogs);
    const allSpan = Math.max(Math.max(...allLogs) - allLo, 1e-9);

    const dotSize = (weight: number): number =>
        MIN_DOT_PX + (MAX_DOT_PX - MIN_DOT_PX) * ((Math.log(weight + 1) - allLo) / allSpan);

    const candidates = fibonacciPoints(CANDIDATE_COUNT);
    const candidateLat = candidates.map((c) => Math.asin(c.y));
    const candidateLon = candidates.map((c) => Math.atan2(c.z, c.x));
    const alive = new Uint8Array(CANDIDATE_COUNT).fill(1);
    const placed: { lat: number; lon: number; halfW: number; halfH: number }[] = [];

    /* Anisotropic overlap test: latitude separation vs summed half-heights,
       longitude separation (shortened by latitude) vs summed half-widths. */
    const collides = (lat: number, lon: number, halfW: number, halfH: number): boolean => {
        for (const p of placed) {
            const dv = Math.abs(lat - p.lat);
            if (dv >= halfH + p.halfH) continue;

            const rawLon = Math.abs(lon - p.lon);
            const dh = Math.min(rawLon, 2 * Math.PI - rawLon) * Math.cos((lat + p.lat) / 2);
            if (dh < halfW + p.halfW) return true;
        }
        return false;
    };

    sphereTags = [];
    const demoted: typeof labelTags = [];

    labelTags.forEach((tag, index) => {
        const norm = (Math.log(tag.weight + 1) - labelLo) / labelSpan;
        const fontPx = MIN_FONT_PX + (MAX_FONT_PX - MIN_FONT_PX) * Math.pow(Math.max(norm, 0), SIZE_CONTRAST);
        const halfW = (((fontPx * GLYPH_WIDTH * tag.name.length) / 2) / radiusPx) * PACKING_X;
        const halfH = ((fontPx * 0.55) / radiusPx) * PACKING_Y;

        /* Hash-scattered scan start so successive words spread over the whole
           sphere instead of spiralling out from one pole. */
        const start = (index * 2654435761) % CANDIDATE_COUNT;
        let found = -1;

        for (let step = 0; step < CANDIDATE_COUNT; step++) {
            const i = (start + step) % CANDIDATE_COUNT;
            if (!alive[i]) continue;

            if (!collides(candidateLat[i], candidateLon[i], halfW, halfH)) {
                found = i;
                break;
            }
        }

        if (found === -1) {
            demoted.push(tag);
            return;
        }

        const at = candidates[found];
        const atLat = candidateLat[found];
        const atLon = candidateLon[found];
        placed.push({ lat: atLat, lon: atLon, halfW, halfH });

        for (let i = 0; i < CANDIDATE_COUNT; i++) {
            if (!alive[i]) continue;
            const dv = Math.abs(candidateLat[i] - atLat);
            if (dv >= halfH) continue;
            const rawLon = Math.abs(candidateLon[i] - atLon);
            const dh = Math.min(rawLon, 2 * Math.PI - rawLon) * Math.cos((candidateLat[i] + atLat) / 2);
            if (dh < halfW) alive[i] = 0;
        }

        sphereTags.push({
            slug: tag.id,
            name: tag.name,
            isLabel: true,
            fontPx,
            dotPx: 0,
            baseX: at.x,
            baseY: at.y,
            baseZ: at.z,
        });
    });

    const allDots = [...dotTags, ...demoted];
    const dotPoints = fibonacciPoints(allDots.length);

    allDots.forEach((tag, i) => {
        sphereTags.push({
            slug: tag.id,
            name: tag.name,
            isLabel: false,
            fontPx: 0,
            dotPx: dotSize(tag.weight),
            baseX: dotPoints[i].x,
            baseY: dotPoints[i].y,
            baseZ: dotPoints[i].z,
        });
    });
}

function measure(): void {
    const el = container.value;
    const cv = canvas.value;
    if (!el || !cv) return;

    const top = el.getBoundingClientRect().top;
    el.style.height = `${Math.max(window.innerHeight - top, 420)}px`;

    width = el.clientWidth;
    height = el.clientHeight;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    cv.width = Math.round(width * dpr);
    cv.height = Math.round(height * dpr);

    const radiusPx = sphereRadius();
    if (lastLayoutRadius > 0 && Math.abs(radiusPx - lastLayoutRadius) / lastLayoutRadius > 0.12) {
        layoutSphere();
    }
}

function trackPointer(event: MouseEvent): DOMRect | null {
    const el = container.value;
    if (!el) return null;
    const rect = el.getBoundingClientRect();
    pointerX = event.clientX - rect.left;
    pointerY = event.clientY - rect.top;
    updateHover();
    return rect;
}

function onPointerMove(event: PointerEvent): void {
    const rect = trackPointer(event);
    if (!rect) return;
    pointerInside = true;

    const nx = (pointerX / rect.width) * 2 - 1;
    const ny = (pointerY / rect.height) * 2 - 1;
    velocityY = nx * POINTER_SPEED;
    velocityX = -ny * POINTER_SPEED;

    if (reducedMotion) renderFrame(false);
}

function onPointerLeave(): void {
    pointerInside = false;
    hoveredSlug.value = null;
}

function updateHover(): void {
    for (let i = hitBoxes.length - 1; i >= 0; i--) {
        const box = hitBoxes[i];
        if (pointerX >= box.x && pointerX <= box.x + box.w && pointerY >= box.y && pointerY <= box.y + box.h) {
            hoveredSlug.value = box.slug;
            return;
        }
    }
    hoveredSlug.value = null;
}

/**
 * Hit-tests the click's own position: a touch tap fires no pointermove, and
 * its pointerleave clears the hover before the click arrives.
 */
function onClick(event: MouseEvent): void {
    trackPointer(event);
    if (hoveredSlug.value) {
        emit('select', hoveredSlug.value);
    }
}

function renderFrame(advance = true): void {
    const cv = canvas.value;
    const ctx = cv?.getContext('2d');
    if (!cv || !ctx) return;

    if (advance) {
        if (!pointerInside) {
            velocityX *= 0.96;
            velocityY = velocityY * 0.96 + IDLE_VELOCITY_Y * 0.04;
        }
        angleX += velocityX;
        angleY += velocityY;
    }

    const cosY = Math.cos(angleY);
    const sinY = Math.sin(angleY);
    const cosX = Math.cos(angleX);
    const sinX = Math.sin(angleX);
    const radius = sphereRadius();
    const cx = width / 2;
    const cy = height / 2;

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, width, height);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = 'rgba(0, 0, 0, 0.85)';

    const projected: { tag: SphereTag; x: number; y: number; depth: number }[] = [];

    for (const tag of sphereTags) {
        const x1 = tag.baseX * cosY + tag.baseZ * sinY;
        const z1 = -tag.baseX * sinY + tag.baseZ * cosY;
        const y2 = tag.baseY * cosX - z1 * sinX;
        const z2 = tag.baseY * sinX + z1 * cosX;

        projected.push({ tag, x: cx + x1 * radius, y: cy + y2 * radius, depth: (z2 + 1) / 2 });
    }

    projected.sort((a, b) => a.depth - b.depth);

    hitBoxes = [];
    let lastFont = '';

    for (const point of projected) {
        const scale = 0.42 + 0.8 * point.depth;
        const depthAlpha = Math.pow(point.depth, 2);

        if (!point.tag.isLabel) {
            ctx.globalAlpha = 0.05 + 0.4 * depthAlpha;
            ctx.fillStyle = '#fb923c';
            const dot = point.tag.dotPx * scale;
            ctx.fillRect(point.x - dot / 2, point.y - dot / 2, dot, dot);
            continue;
        }

        const px = Math.max(Math.round(point.tag.fontPx * scale), 7);
        const isHovered = point.tag.slug === hoveredSlug.value;

        const font = `500 ${px}px Poppins, ui-sans-serif, sans-serif`;
        if (font !== lastFont) {
            ctx.font = font;
            ctx.lineWidth = Math.max(px * 0.14, 1.5);
            lastFont = font;
        }

        ctx.globalAlpha = isHovered ? 1 : 0.08 + 0.9 * depthAlpha;
        ctx.fillStyle = isHovered ? '#ff6c21' : point.depth > 0.72 ? '#e5e5e5' : '#9ca3af';
        ctx.strokeText(point.tag.name, point.x, point.y);
        ctx.fillText(point.tag.name, point.x, point.y);

        if (point.depth > 0.55) {
            const textWidth = ctx.measureText(point.tag.name).width;
            hitBoxes.push({
                x: point.x - textWidth / 2,
                y: point.y - px / 2,
                w: textWidth,
                h: px,
                slug: point.tag.slug,
            });
        }
    }

    ctx.globalAlpha = 1;

    if (!reducedMotion && advance) {
        frame = requestAnimationFrame(() => renderFrame());
    }
}

watch(
    () => props.items,
    () => {
        layoutSphere();
        if (reducedMotion) renderFrame(false);
    },
);

onMounted(() => {
    reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    measure();
    layoutSphere();

    const el = container.value;
    if (el) {
        resizeObserver = new ResizeObserver(() => {
            measure();
            if (reducedMotion) renderFrame(false);
        });
        resizeObserver.observe(el);
    }

    window.addEventListener('resize', measure);
    renderFrame(!reducedMotion);
});

onBeforeUnmount(() => {
    cancelAnimationFrame(frame);
    resizeObserver?.disconnect();
    window.removeEventListener('resize', measure);
});
</script>

<template>
    <div
        ref="container"
        class="tag-sphere"
        :class="{ 'tag-sphere--hovering': hoveredSlug }"
        @pointermove="onPointerMove"
        @pointerleave="onPointerLeave"
        @click="onClick"
    >
        <canvas ref="canvas" class="tag-sphere__canvas" />
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.tag-sphere {
    @apply relative w-full overflow-hidden;
    background-image: radial-gradient(circle at 50% 50%, rgba(255, 108, 33, 0.06), transparent 55%);
    touch-action: none;
}

.tag-sphere--hovering {
    cursor: pointer;
}

.tag-sphere__canvas {
    @apply block h-full w-full;
}
</style>
