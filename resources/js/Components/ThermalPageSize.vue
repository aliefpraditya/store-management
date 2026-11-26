<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Width of the thermal roll in millimetres, as the store set it. */
    width: { type: Number, required: true },
});

const DEFAULT_PADDING_MM = { left: 4, right: 4 };

/**
 * Space kept free on the left and right of the text, per roll width. A printer cannot print edge to edge, and the window
 * it does print is not always centred on the roll: a 76 mm dot matrix roll (Epson TM-U220) prints only about 62 mm, starting
 * about 9 mm from the left edge, so text that starts 4 mm from either edge is cut off. The 76 mm figures were measured from
 * a printed receipt; the other widths keep the default until a print shows otherwise.
 */
const PADDING_MM = { 76: { left: 9, right: 6 } };

/**
 * The paper size of a roll receipt follows the store's setting, so the rule is written here instead of in the style
 * of each sheet. A zero margin leaves the browser no room for its own header and footer. The sheets read the side space
 * from `--thermal-padding-left` and `--thermal-padding-right`.
 */
const pageRule = computed(() => {
    const padding = PADDING_MM[props.width] ?? DEFAULT_PADDING_MM;

    return `@page { size: ${props.width}mm auto; margin: 0; } :root { --thermal-padding-left: ${padding.left}mm; --thermal-padding-right: ${padding.right}mm; }`;
});
</script>

<template>
    <component :is="'style'">{{ pageRule }}</component>
</template>
