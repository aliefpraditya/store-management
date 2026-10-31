<script setup>
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps({
    modelValue: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const field = ref(null);

/**
 * A native date field only opens its calendar from the small icon. Opening it from anywhere in the field is what
 * people expect, except when the click is on the clear button.
 * @param {MouseEvent} event
 */
function openPicker(event) {
    if (event.target.closest('.q-field__append')) {
        return;
    }

    try {
        field.value?.getNativeElement()?.showPicker();
    } catch {
        // The browser refuses outside a click or when the calendar is already open; the icon still works.
    }
}
</script>

<template>
    <q-input ref="field" v-bind="$attrs" :model-value="modelValue" type="date" @update:model-value="emit('update:modelValue', $event)" @click="openPicker" />
</template>
