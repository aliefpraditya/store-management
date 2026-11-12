<script setup>
const props = defineProps({
    /** One part per unit typed, e.g. 1 ROL and 30 MTR. @type {{unit_code: string, quantity: string}[]} */
    modelValue: { type: Array, required: true },
    /** The product's units. @type {{code: string}[]} */
    units: { type: Array, required: true },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    isError: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function changePart(partIndex, change) {
    emit('update:modelValue', props.modelValue.map((part, index) => (index === partIndex ? { ...part, ...change } : part)));
}

function addPart() {
    const unusedUnit = props.units.find((unit) => !props.modelValue.some((part) => part.unit_code === unit.code));

    emit('update:modelValue', [...props.modelValue, { unit_code: (unusedUnit ?? props.units[0]).code, quantity: '' }]);
}

function removePart(partIndex) {
    emit('update:modelValue', props.modelValue.filter((part, index) => index !== partIndex));
}
</script>

<template>
    <div class="unit-parts">
        <div v-for="(part, partIndex) in modelValue" :key="partIndex" class="unit-parts__row">
            <q-input
                :model-value="part.quantity"
                type="number"
                min="0"
                step="any"
                outlined
                dense
                :label="partIndex === 0 ? label : undefined"
                :hint="partIndex === 0 ? hint : undefined"
                :error="isError"
                :aria-label="label"
                class="unit-parts__quantity"
                @update:model-value="changePart(partIndex, { quantity: $event === null ? '' : String($event) })"
            />
            <q-select
                :model-value="part.unit_code"
                :options="units.map((unit) => unit.code)"
                outlined
                dense
                :aria-label="`Satuan ${label}`"
                class="unit-parts__unit"
                @update:model-value="changePart(partIndex, { unit_code: $event })"
            />
            <q-btn v-if="partIndex > 0" flat round dense size="sm" icon="close" color="grey-7" aria-label="Hapus satuan" @click="removePart(partIndex)" />
            <q-btn v-else-if="units.length > 1" flat round dense size="sm" icon="add" color="grey-7" aria-label="Tambah satuan lain" @click="addPart" />
        </div>
    </div>
</template>

<style scoped>
.unit-parts {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.unit-parts__row {
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.unit-parts__quantity {
    flex: 1;
}

.unit-parts__unit {
    width: 96px;
}
</style>
