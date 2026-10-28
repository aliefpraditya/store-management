<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    /** The chosen record: an existing one ({ id, name }) or a new one typed here ({ id: null, name }). */
    modelValue: { type: Object, default: null },
    options: { type: Array, required: true },
    label: { type: String, required: true },
    hint: { type: String, default: undefined },
    error: { type: String, default: undefined },
    disable: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const visibleOptions = ref(props.options);

watch(
    () => props.options,
    (options) => {
        visibleOptions.value = options;
    },
);

function filterOptions(typed, update) {
    update(() => {
        const needle = typed.trim().toLowerCase();

        visibleOptions.value = props.options.filter((option) => option.name.toLowerCase().includes(needle));
    });
}

/**
 * Typing a name that already exists picks that record instead of making a duplicate.
 */
function createFromTyped(typed, done) {
    const name = typed.trim();

    if (name === '') {
        done();

        return;
    }

    const existing = props.options.find((option) => option.name.toLowerCase() === name.toLowerCase());

    done(existing ?? { id: null, name }, 'add-unique');
}
</script>

<template>
    <q-select
        :model-value="modelValue"
        :options="visibleOptions"
        :label="label"
        :hint="hint"
        :error="!!error"
        :error-message="error"
        :disable="disable"
        :clearable="clearable"
        option-label="name"
        option-value="name"
        outlined
        use-input
        hide-selected
        fill-input
        input-debounce="0"
        @filter="filterOptions"
        @new-value="createFromTyped"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <template #no-option>
            <q-item>
                <q-item-section class="text-grey">Ketik nama lalu Enter untuk menambah baru</q-item-section>
            </q-item>
        </template>
    </q-select>
</template>
