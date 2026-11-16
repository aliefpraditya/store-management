<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Width of the thermal roll in millimetres, as the store set it. */
    width: { type: Number, required: true },
});

/**
 * The paper size of a roll receipt follows the store's setting, so the rule is written here instead of in the style
 * of each sheet. A zero margin leaves the browser no room for its own header and footer.
 */
const pageRule = computed(() => `@page { size: ${props.width}mm auto; margin: 0; }`);
</script>

<template>
    <component :is="'style'">{{ pageRule }}</component>
</template>