<script setup>
import { computed } from 'vue';
import { SERIES_COLORS } from '../Composables/chartColors';

const props = defineProps({
    /** The rows, biggest first, `{key, label, value}`. */
    rows: { type: Array, required: true },
    /** Writes a value as it is printed at the end of its bar. */
    formatValue: { type: Function, required: true },
    emptyText: { type: String, default: 'Belum ada penjualan pada periode ini.' },
});

/** The bars stand on one scale, the biggest value filling the track; a value of nothing or less leaves a bar of no length. */
const bars = computed(() => {
    const largest = Math.max(...props.rows.map((row) => Number(row.value)), 1);

    return props.rows.map((row) => ({ ...row, width: (Math.max(Number(row.value), 0) / largest) * 100 }));
});
</script>

<template>
    <p v-if="rows.length === 0" class="ranked-bars__empty">{{ emptyText }}</p>
    <ul v-else class="ranked-bars">
        <li v-for="bar in bars" :key="bar.key" :title="`${bar.label}: ${formatValue(bar.value)}`">
            <span class="ranked-bars__name">{{ bar.label }}</span>
            <span class="ranked-bars__track"><i class="ranked-bars__bar" :style="{ width: `${bar.width}%`, background: SERIES_COLORS[0] }"></i></span>
            <b class="ranked-bars__value">{{ formatValue(bar.value) }}</b>
        </li>
    </ul>
</template>

<style scoped>
.ranked-bars {
    margin: 0;
    padding: 0;
    list-style: none;
}

.ranked-bars li {
    display: grid;
    grid-template-columns: minmax(90px, 34%) minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding: 5px 0;
    font-size: 13px;
}

.ranked-bars__name {
    overflow: hidden;
    color: var(--app-ink-soft);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ranked-bars__track {
    display: flex;
    align-items: center;
    height: 12px;
}

/** Square on the baseline, 4px round at the data end, and thin: the bar is for reading length, not for filling the row. */
.ranked-bars__bar {
    display: block;
    height: 10px;
    border-radius: 0 4px 4px 0;
}

.ranked-bars__value {
    min-width: 96px;
    font-weight: 600;
    text-align: right;
    white-space: nowrap;
}

.ranked-bars__empty {
    margin: 0;
    color: var(--app-mute);
}
</style>
