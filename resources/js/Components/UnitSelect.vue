<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    /** The chosen unit ({ code, name }) or null. */
    modelValue: { type: Object, default: null },
    units: { type: Array, required: true },
    label: { type: String, required: true },
    error: { type: String, default: undefined },
    disable: { type: Boolean, default: false },
    dense: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'create']);

const select = ref(null);
const visibleUnits = ref(props.units);

watch(
    () => props.units,
    (units) => {
        visibleUnits.value = units;
    },
);

function filterUnits(typed, update) {
    update(() => {
        const needle = typed.trim().toLowerCase();

        visibleUnits.value = props.units.filter((unit) => `${unit.code} ${unit.name}`.toLowerCase().includes(needle));
    });
}

function createUnit() {
    select.value.hidePopup();
    emit('create');
}
</script>

<template>
    <q-select
        ref="select"
        :model-value="modelValue"
        :options="visibleUnits"
        :label="label"
        :error="!!error"
        :error-message="error"
        :disable="disable"
        :dense="dense"
        :hide-bottom-space="dense"
        option-label="code"
        option-value="code"
        outlined
        use-input
        hide-selected
        fill-input
        input-debounce="0"
        @filter="filterUnits"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <template #option="{ itemProps, opt }">
            <q-item v-bind="itemProps">
                <q-item-section>
                    <q-item-label>{{ opt.code }}</q-item-label>
                    <q-item-label v-if="opt.name.toLowerCase() !== opt.code.toLowerCase()" caption>{{ opt.name }}</q-item-label>
                </q-item-section>
            </q-item>
        </template>

        <template #no-option>
            <q-item>
                <q-item-section class="text-grey">Satuan tidak ditemukan</q-item-section>
            </q-item>
        </template>

        <template #after-options>
            <q-item clickable class="text-primary" @click="createUnit">
                <q-item-section avatar class="unit-select__add-icon"><q-icon name="add" /></q-item-section>
                <q-item-section>Tambah satuan baru</q-item-section>
            </q-item>
        </template>
    </q-select>
</template>

<style scoped>
.unit-select__add-icon {
    min-width: 0;
    padding-right: 8px;
}
</style>
