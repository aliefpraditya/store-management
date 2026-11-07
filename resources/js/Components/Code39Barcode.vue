<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** The text to encode, such as an order number; lower case letters are printed as capitals. */
    value: { type: String, required: true },
    height: { type: Number, default: 34 },
});

/**
 * Code 39: every character is nine elements (bar, space, bar, ...) of which three are wide; 1 is a wide element. The
 * characters between asterisks are what a scanner types, so the order number scans back as itself.
 */
const PATTERNS = {
    0: '000110100', 1: '100100001', 2: '001100001', 3: '101100000', 4: '000110001',
    5: '100110000', 6: '001110000', 7: '000100101', 8: '100100100', 9: '001100100',
    A: '100001001', B: '001001001', C: '101001000', D: '000011001', E: '100011000',
    F: '001011000', G: '000001101', H: '100001100', I: '001001100', J: '000011100',
    K: '100000011', L: '001000011', M: '101000010', N: '000010011', O: '100010010',
    P: '001010010', Q: '000000111', R: '100000110', S: '001000110', T: '000010110',
    U: '110000001', V: '011000001', W: '111000000', X: '010010001', Y: '110010000',
    Z: '011010000', '-': '010000101', '.': '110000100', ' ': '011000100', '*': '010010100',
    $: '010101000', '/': '010100010', '+': '010001010', '%': '000101010',
};

const WIDE = 3;

/** The bars as `{x, width}` and the total width, in units of the narrowest element. */
const bars = computed(() => {
    const characters = `*${props.value.toUpperCase()}*`.split('').filter((character) => PATTERNS[character] !== undefined);
    const drawn = [];
    let x = 0;

    characters.forEach((character) => {
        [...PATTERNS[character]].forEach((element, index) => {
            const width = element === '1' ? WIDE : 1;

            if (index % 2 === 0) {
                drawn.push({ x, width });
            }

            x += width;
        });

        x += 1;
    });

    return { drawn, totalWidth: Math.max(x - 1, 1) };
});
</script>

<template>
    <svg
        class="barcode"
        :viewBox="`0 0 ${bars.totalWidth} 10`"
        preserveAspectRatio="none"
        shape-rendering="crispEdges"
        :height="height"
        role="img"
        :aria-label="`Barcode ${value}`"
    >
        <rect v-for="(bar, index) in bars.drawn" :key="index" :x="bar.x" y="0" :width="bar.width" height="10" fill="#000" />
    </svg>
</template>

<style scoped>
.barcode {
    display: block;
    width: 100%;
    max-width: 100%;
}
</style>
