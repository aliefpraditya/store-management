<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: null },
    /** The choices as { id, name }. */
    options: { type: Array, required: true },
});

defineEmits(['update:modelValue']);

const visibleOptions = ref(props.options);

watch(
    () => props.options,
    (options) => {
        visibleOptions.value = options;
    },
);

/** @param {string} typed what was typed in the box; an empty box shows every choice again */
function filterOptions(typed, update) {
    update(() => {
        const needle = typed.trim().toLowerCase();

        visibleOptions.value = props.options.filter((option) => option.name.toLowerCase().includes(needle));
    });
}
</script>

<template>
    <q-select
        :model-value="modelValue"
        :options="visibleOptions"
        option-value="id"
        option-label="name"
        emit-value
        map-options
        outlined
        use-input
        hide-selected
        fill-input
        input-debounce="0"
        @filter="filterOptions"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <template #no-option>
            <q-item>
                <q-item-section class="text-grey">Tidak ditemukan</q-item-section>
            </q-item>
        </template>
    </q-select>
</template>
