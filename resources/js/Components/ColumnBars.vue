<script setup>
import { computed } from 'vue';
import { SERIES_COLORS } from '../Composables/chartColors';
import { useHoverPopup } from '../Composables/useHoverPopup';
import HoverDetails from './HoverDetails.vue';

const props = defineProps({
    /** The columns left to right, `{key, label, value, details: {title, rows}}`; the details open in a dialog on hover. */
    items: { type: Array, required: true },
    /** Writes a value as it is printed above its column. */
    formatValue: { type: Function, required: true },
    /** Which columns print their value: the tallest only (for many columns), or every one. */
    valueLabels: { type: String, default: 'peak', validator: (value) => ['peak', 'all'].includes(value) },
    /** What the chart is of, for a screen reader. */
    label: { type: String, required: true },
    emptyText: { type: String, default: 'Belum ada penjualan pada periode ini.' },
});

const { openKey, show, hideSoon, close } = useHoverPopup();

/** The columns stand on one scale, the tallest filling the chart; a value of nothing draws no column, any other at least a sliver. */
const columns = computed(() => {
    const largest = Math.max(...props.items.map((item) => Number(item.value)), 0);

    return props.items.map((item) => {
        const value = Number(item.value);

        return { ...item, height: value > 0 ? Math.max((value / largest) * 100, 2) : 0, isPrinted: props.valueLabels === 'all' ? value > 0 : value > 0 && value === largest };
    });
});

const hasAny = computed(() => columns.value.some((column) => column.height > 0));
</script>

<template>
    <p v-if="!hasAny" class="column-bars__empty">{{ emptyText }}</p>
    <div v-else class="column-bars" role="group" :aria-label="label">
        <button
            v-for="column in columns"
            :key="column.key"
            type="button"
            class="column-bars__column"
            :class="{ 'column-bars__column--on': openKey === column.key }"
            :aria-label="`${column.details.title}: ${formatValue(column.value)}`"
            @mouseenter="show(column.key)"
            @mouseleave="hideSoon"
            @focus="show(column.key)"
            @blur="hideSoon"
            @click="show(column.key)"
        >
            <span class="column-bars__value">{{ column.isPrinted ? formatValue(column.value) : '' }}</span>
            <span class="column-bars__track">
                <i class="column-bars__bar" :style="{ height: `${column.height}%`, background: SERIES_COLORS[0] }"></i>
            </span>
            <span class="column-bars__label">{{ column.label }}</span>

            <HoverDetails :is-open="openKey === column.key" :title="column.details.title" :rows="column.details.rows" @enter="show(column.key)" @leave="hideSoon" @close="close(column.key)" />
        </button>
    </div>
</template>

<style scoped>
.column-bars {
    display: flex;
    align-items: stretch;
    gap: 4px;
    height: 190px;
}

/** A column is a button: the whole column answers the pointer, so a thin bar is no harder to hit than a wide one. */
.column-bars__column {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    min-width: 0;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: none;
    color: inherit;
    cursor: pointer;
    font: inherit;
    transition: background-color 0.15s;
}

.column-bars__column:hover,
.column-bars__column--on {
    background: var(--app-brand-soft);
}

.column-bars__column:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 1px;
}

.column-bars__value {
    flex: none;
    height: 16px;
    color: var(--app-ink-soft);
    font-size: 11px;
    font-weight: 600;
    line-height: 16px;
    white-space: nowrap;
}

/** Holds only the bar, so every bar stands on the same baseline whatever is printed above or below it. */
.column-bars__track {
    display: flex;
    flex: 1;
    align-items: flex-end;
    justify-content: center;
    width: 100%;
    min-height: 0;
}

/** Square on the baseline and 4px round at the data end, thin enough that a crowd of them stays a set of marks. */
.column-bars__bar {
    display: block;
    width: 100%;
    max-width: 28px;
    border-radius: 4px 4px 0 0;
}

.column-bars__label {
    flex: none;
    height: 16px;
    color: var(--app-mute);
    font-size: 11px;
    line-height: 16px;
    white-space: nowrap;
}

.column-bars__empty {
    margin: 0;
    color: var(--app-mute);
}
</style>
