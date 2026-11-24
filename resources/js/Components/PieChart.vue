<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    /** The parts of the whole, `{key, label, value, color, note?}` (a note is a line under the name in the legend); a part of nothing (or less) is left out. Keep it to six or fewer: fold the tail into one part. */
    slices: { type: Array, required: true },
    /** Writes a value as the legend shows it. */
    formatValue: { type: Function, required: true },
    /** What the chart is of, for a screen reader, which is also read the legend. */
    label: { type: String, required: true },
});

const CENTER = 70;
const RADIUS = 66;

const hoveredKey = ref(null);

const total = computed(() => props.slices.reduce((sum, slice) => sum + Math.max(Number(slice.value), 0), 0));

/**
 * Each slice with its share and the path of its wedge, going round clockwise from twelve o'clock. A whole that is one
 * slice is a circle, since a wedge of 360° has no arc to draw.
 */
const wedges = computed(() => {
    const parts = props.slices.filter((slice) => Number(slice.value) > 0);
    let startAngle = -Math.PI / 2;

    return parts.map((slice) => {
        const share = Number(slice.value) / total.value;
        const endAngle = startAngle + share * 2 * Math.PI;
        const pointAt = (angle) => `${(CENTER + RADIUS * Math.cos(angle)).toFixed(3)} ${(CENTER + RADIUS * Math.sin(angle)).toFixed(3)}`;
        const path = `M ${CENTER} ${CENTER} L ${pointAt(startAngle)} A ${RADIUS} ${RADIUS} 0 ${share > 0.5 ? 1 : 0} 1 ${pointAt(endAngle)} Z`;

        startAngle = endAngle;

        return { ...slice, share, percent: (share * 100).toFixed(1), path, isWhole: parts.length === 1 };
    });
});

const isDimmed = (key) => hoveredKey.value !== null && hoveredKey.value !== key;
</script>

<template>
    <p v-if="total <= 0" class="pie__empty">Belum ada penjualan pada periode ini.</p>
    <div v-else class="pie">
        <svg class="pie__chart" viewBox="0 0 140 140" role="img" :aria-label="label">
            <template v-for="wedge in wedges" :key="wedge.key">
                <circle v-if="wedge.isWhole" :cx="CENTER" :cy="CENTER" :r="RADIUS" :fill="wedge.color" class="pie__wedge" />
                <path v-else :d="wedge.path" :fill="wedge.color" class="pie__wedge" :class="{ 'pie__wedge--dim': isDimmed(wedge.key) }" @mouseenter="hoveredKey = wedge.key" @mouseleave="hoveredKey = null">
                    <title>{{ wedge.label }}: {{ formatValue(wedge.value) }} ({{ wedge.percent }}%)</title>
                </path>
            </template>
        </svg>

        <ul class="pie__legend">
            <li v-for="wedge in wedges" :key="wedge.key" :class="{ 'pie__row--dim': isDimmed(wedge.key) }" @mouseenter="hoveredKey = wedge.key" @mouseleave="hoveredKey = null">
                <i class="pie__swatch" :style="{ background: wedge.color }"></i>
                <span class="pie__name">{{ wedge.label }}<small v-if="wedge.note" class="pie__note">{{ wedge.note }}</small></span>
                <b class="pie__value">{{ formatValue(wedge.value) }}</b>
                <span class="pie__percent">{{ wedge.percent }}%</span>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.pie {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px 24px;
}

.pie__chart {
    flex: none;
    width: 160px;
    height: 160px;
}

/** The 2px gap between wedges is the card's own colour showing through, not a border drawn on the marks. */
.pie__wedge {
    stroke: var(--app-surface);
    stroke-width: 2;
    transition: opacity 0.15s;
}

.pie__wedge--dim,
.pie__row--dim {
    opacity: 0.35;
}

.pie__legend {
    flex: 1;
    min-width: 220px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pie__legend li {
    display: grid;
    grid-template-columns: 12px minmax(0, 1fr) auto 52px;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid var(--app-line);
    font-size: 13px;
    transition: opacity 0.15s;
}

.pie__legend li:last-child {
    border-bottom: 0;
}

.pie__swatch {
    width: 10px;
    height: 10px;
    border-radius: 3px;
}

/** A long name wraps onto a second line rather than being cut off: the legend is what names the colours. */
.pie__name {
    color: var(--app-ink-soft);
    overflow-wrap: anywhere;
}

/** A line break in a note is kept, so a note can be set as a short list. */
.pie__note {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
    white-space: pre-line;
}

.pie__value {
    font-weight: 600;
    white-space: nowrap;
}

.pie__percent {
    color: var(--app-mute);
    text-align: right;
}

.pie__empty {
    margin: 0;
    color: var(--app-mute);
}
</style>
