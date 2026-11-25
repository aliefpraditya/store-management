<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    /** Values already used elsewhere (a city, a container number), offered while typing; anything else typed is kept as it is. */
    options: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const visibleOptions = ref(props.options);

watch(
    () => props.options,
    (options) => {
        visibleOptions.value = options;
    },
);

/** @param {string} typed what was typed in the box; an empty box shows every suggestion again */
function filterOptions(typed, update) {
    update(() => {
        const needle = typed.trim().toLowerCase();

        visibleOptions.value = needle === '' ? props.options : props.options.filter((option) => option.toLowerCase().includes(needle));
    });
}
</script>

<template>
    <q-select
        :model-value="modelValue"
        :options="visibleOptions"
        outlined
        use-input
        hide-selected
        fill-input
        input-debounce="0"
        @filter="filterOptions"
        @input-value="emit('update:modelValue', $event)"
        @update:model-value="emit('update:modelValue', $event ?? '')"
    />
</template>
