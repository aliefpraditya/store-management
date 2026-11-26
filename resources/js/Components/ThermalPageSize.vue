<script setup>
import { computed } from 'vue';
import { thermalLayoutOf } from '../Composables/thermalLayout';

const props = defineProps({
    /** Width of the thermal roll in millimetres, as the store set it. */
    width: { type: Number, required: true },
});

/**
 * The paper size of a roll receipt follows the store's setting, so the rule is written here instead of in the style
 * of each sheet. A zero margin leaves the browser no room for its own header and footer. The sheets that still lay out
 * with CSS read the side space from `--thermal-padding-left` and `--thermal-padding-right`; the receipt sheet writes its
 * space inline instead, because the renderer of QZ Tray does not know CSS variables.
 */
const pageRule = computed(() => {
    const layout = thermalLayoutOf(props.width);

    return `@page { size: ${props.width}mm auto; margin: 0; } :root { --thermal-padding-left: ${layout.left}mm; --thermal-padding-right: ${layout.right}mm; }`;
});
</script>

<template>
    <component :is="'style'">{{ pageRule }}</component>
</template>
