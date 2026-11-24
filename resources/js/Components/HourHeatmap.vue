<script setup>
import { computed } from 'vue';
import { useHoverPopup } from '../Composables/useHoverPopup';
import HoverDetails from './HoverDetails.vue';

const props = defineProps({
    /** The rows top to bottom, `{key, label}`. */
    rows: { type: Array, required: true },
    /** The hours left to right, as whole numbers. */
    hours: { type: Array, required: true },
    /** The cells that have something, `{row, hour, value, details: {title, rows}}`; a cell that is not here is empty. The details open in a dialog on hover. */
    cells: { type: Array, required: true },
    /** Writes a cell's value, for the screen reader's name of the cell. */
    formatValue: { type: Function, required: true },
    /** What the chart is of, for a screen reader. */
    label: { type: String, required: true },
    emptyText: { type: String, default: 'Belum ada penjualan pada periode ini.' },
});

const { openKey, show, hideSoon, close } = useHoverPopup();

/**
 * Five steps of one blue, lightest to darkest: a cell is shaded by how it compares with the busiest, in fifths, so the
 * pattern reads at a glance and the exact figure is in its details. An empty cell is the neutral of the surface.
 */
const STEPS = ['#cde2fb', '#9ec5f4', '#5598e7', '#2a78d6', '#184f95'];
const EMPTY_COLOR = '#f0efec';

const largest = computed(() => Math.max(...props.cells.map((cell) => Number(cell.value)), 0));

const cellsByPosition = computed(() => new Map(props.cells.map((cell) => [`${cell.row}-${cell.hour}`, cell])));

/** One row of the grid, each hour a slot with its colour and, when it had something, its key and details. */
const lines = computed(() =>
    props.rows.map((row) => ({
        ...row,
        slots: props.hours.map((hour) => {
            const cell = cellsByPosition.value.get(`${row.key}-${hour}`);
            const step = cell && largest.value > 0 ? Math.min(Math.max(Math.ceil((Number(cell.value) / largest.value) * STEPS.length), 1), STEPS.length) : 0;

            return { hour, key: `${row.key}-${hour}`, color: step === 0 ? EMPTY_COLOR : STEPS[step - 1], cell };
        }),
    })),
);

/** An hour is written above its column every other hour, so the numbers keep clear of each other when the cells are small. */
const isLabelled = (index) => index % 2 === 0;
</script>

<template>
    <p v-if="largest <= 0" class="heatmap__empty">{{ emptyText }}</p>
    <div v-else class="heatmap" role="group" :aria-label="label">
        <div class="heatmap__grid" :style="{ gridTemplateColumns: `64px repeat(${hours.length}, minmax(0, 1fr))` }">
            <span></span>
            <span v-for="(hour, index) in hours" :key="hour" class="heatmap__hour">{{ isLabelled(index) ? String(hour).padStart(2, '0') : '' }}</span>

            <template v-for="line in lines" :key="line.key">
                <span class="heatmap__row">{{ line.label }}</span>
                <template v-for="slot in line.slots" :key="slot.hour">
                    <button
                        v-if="slot.cell"
                        type="button"
                        class="heatmap__cell heatmap__cell--has"
                        :class="{ 'heatmap__cell--on': openKey === slot.key }"
                        :style="{ background: slot.color }"
                        :aria-label="`${slot.cell.details.title}: ${formatValue(slot.cell.value)}`"
                        @mouseenter="show(slot.key)"
                        @mouseleave="hideSoon"
                        @focus="show(slot.key)"
                        @blur="hideSoon"
                        @click="show(slot.key)"
                    >
                        <HoverDetails :is-open="openKey === slot.key" :title="slot.cell.details.title" :rows="slot.cell.details.rows" @enter="show(slot.key)" @leave="hideSoon" @close="close(slot.key)" />
                    </button>
                    <i v-else class="heatmap__cell" :style="{ background: slot.color }"></i>
                </template>
            </template>
        </div>

        <div class="heatmap__legend">
            <span>Sepi</span>
            <i v-for="color in STEPS" :key="color" class="heatmap__step" :style="{ background: color }"></i>
            <span>Ramai</span>
        </div>
    </div>
</template>

<style scoped>
.heatmap__grid {
    display: grid;
    gap: 2px;
}

.heatmap__hour {
    color: var(--app-mute);
    font-size: 11px;
    text-align: center;
    white-space: nowrap;
}

.heatmap__row {
    align-self: center;
    color: var(--app-ink-soft);
    font-size: 12px;
}

/** A cell is a mark of its own with a 4px corner, and the 2px between cells is the card showing through. */
.heatmap__cell {
    display: block;
    height: 28px;
    padding: 0;
    border: 0;
    border-radius: 4px;
}

.heatmap__cell--has {
    cursor: pointer;
}

/** The cell the details are of is ringed, so it stays found while the dialog covers the cells above it. */
.heatmap__cell--has:hover,
.heatmap__cell--on {
    outline: 2px solid var(--app-ink);
    outline-offset: -2px;
}

.heatmap__cell--has:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 1px;
}

.heatmap__legend {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 12px;
    color: var(--app-mute);
    font-size: 12px;
}

.heatmap__legend span:first-child {
    margin-right: 4px;
}

.heatmap__legend span:last-child {
    margin-left: 4px;
}

.heatmap__step {
    display: block;
    width: 22px;
    height: 12px;
    border-radius: 3px;
}

.heatmap__empty {
    margin: 0;
    color: var(--app-mute);
}
</style>
