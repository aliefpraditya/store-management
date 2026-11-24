<script setup>
import { computed } from 'vue';
import { SERIES_COLORS } from '../Composables/chartColors';

const props = defineProps({
    /** How much of the whole is filled, in percent; kept between 0 and 100. */
    percent: { type: [Number, String], required: true },
    /** What the chart is of, for a screen reader, which is also read the percent. */
    label: { type: String, required: true },
});

const fill = computed(() => Math.min(Math.max(Number(props.percent), 0), 100));
</script>

<template>
    <div class="meter" role="meter" :aria-label="label" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="fill" :aria-valuetext="`${fill}%`">
        <i class="meter__fill" :style="{ width: `${fill}%`, background: SERIES_COLORS[0] }"></i>
    </div>
</template>

<style scoped>
/** The track is a light step of the fill's own blue, so the whole bar reads as one ratio; the fill stands on the baseline and is round only at its data end. */
.meter {
    height: 12px;
    border-radius: 6px;
    background: #cde2fb;
    overflow: hidden;
}

.meter__fill {
    display: block;
    height: 100%;
    border-radius: 0 6px 6px 0;
}
</style>
