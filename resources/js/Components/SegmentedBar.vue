<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    /** The parts of the whole in block order, `{key, label, value, color, textColor?, note?}`; a part of nothing (or less) draws no block but keeps its place in the legend. At most six. */
    parts: { type: Array, required: true },
    /** Writes a part's value as the legend shows it. */
    formatValue: { type: Function, required: true },
    /** What the chart is of, for a screen reader, which is also read every part. */
    label: { type: String, required: true },
    emptyText: { type: String, default: 'Belum ada data.' },
});

/** A block narrower than this share of the bar prints no percent: the text would not fit inside it. */
const MIN_SHARE_TO_PRINT = 0.09;

const hoveredKey = ref(null);

const total = computed(() => props.parts.reduce((sum, part) => sum + Math.max(Number(part.value), 0), 0));

const blocks = computed(() =>
    props.parts.map((part) => {
        const share = total.value > 0 ? Math.max(Number(part.value), 0) / total.value : 0;

        return { ...part, share, percent: (share * 100).toFixed(share > 0 && share < 0.1 ? 1 : 0), isPrinted: share >= MIN_SHARE_TO_PRINT };
    }),
);

const description = computed(() => `${props.label}: ${blocks.value.map((block) => `${block.label} ${props.formatValue(block.value)} (${block.percent}%)`).join(', ')}`);

const isDimmed = (key) => hoveredKey.value !== null && hoveredKey.value !== key;
</script>

<template>
    <p v-if="total <= 0" class="segmented__empty">{{ emptyText }}</p>
    <div v-else class="segmented">
        <div class="segmented__bar" role="img" :aria-label="description">
            <template v-for="block in blocks" :key="block.key">
                <div
                    v-if="block.share > 0"
                    class="segmented__block"
                    :class="{ 'segmented__block--dim': isDimmed(block.key) }"
                    :style="{ flexGrow: block.share, background: block.color, color: block.textColor ?? '#ffffff' }"
                    :title="`${block.label}: ${formatValue(block.value)} (${block.percent}%)`"
                    @mouseenter="hoveredKey = block.key"
                    @mouseleave="hoveredKey = null"
                >
                    <span v-if="block.isPrinted">{{ block.percent }}%</span>
                </div>
            </template>
        </div>

        <ul class="segmented__legend">
            <li v-for="block in blocks" :key="block.key" :class="{ 'segmented__row--dim': isDimmed(block.key) }" @mouseenter="hoveredKey = block.key" @mouseleave="hoveredKey = null">
                <i class="segmented__swatch" :style="{ background: block.color }"></i>
                <span class="segmented__name">{{ block.label }}<small v-if="block.note" class="segmented__note">{{ block.note }}</small></span>
                <b class="segmented__value">{{ formatValue(block.value) }}</b>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.segmented__bar {
    display: flex;
    gap: 2px;
    height: 28px;
}

/** The 2px gap between blocks is the card showing through; the bar is a figure with no baseline, so every block has its own 4px corners. */
.segmented__block {
    display: flex;
    flex-basis: 0;
    align-items: center;
    min-width: 4px;
    padding-left: 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    overflow: hidden;
    transition: opacity 0.15s;
}

.segmented__block--dim,
.segmented__row--dim {
    opacity: 0.35;
}

.segmented__legend {
    margin: 12px 0 0;
    padding: 0;
    list-style: none;
}

.segmented__legend li {
    display: grid;
    grid-template-columns: 12px minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid var(--app-line);
    font-size: 13px;
    transition: opacity 0.15s;
}

.segmented__legend li:last-child {
    border-bottom: 0;
}

.segmented__swatch {
    width: 10px;
    height: 10px;
    border-radius: 3px;
}

.segmented__name {
    color: var(--app-ink-soft);
    overflow-wrap: anywhere;
}

.segmented__note {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.segmented__value {
    font-weight: 600;
    white-space: nowrap;
}

.segmented__empty {
    margin: 0;
    color: var(--app-mute);
}
</style>
