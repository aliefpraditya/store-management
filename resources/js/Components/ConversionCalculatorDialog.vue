<script setup>
import { computed, ref, watch } from 'vue';
import { conversionFromCount } from '../Composables/useUnitConversion';
import { formatQuantity } from '../Composables/useQuantityFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** Code of the unit whose conversion is being worked out. */
    unitCode: { type: String, required: true },
    baseUnitCode: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue', 'apply']);

const direction = ref('unit-holds-base');
const count = ref('');

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            direction.value = 'unit-holds-base';
            count.value = '';
        }
    },
);

const result = computed(() => conversionFromCount(count.value, direction.value));

const countUnitCode = computed(() => (direction.value === 'unit-holds-base' ? props.baseUnitCode : props.unitCode));
const oneUnitCode = computed(() => (direction.value === 'unit-holds-base' ? props.unitCode : props.baseUnitCode));

const directionOptions = computed(() => [
    { label: `1 ${props.unitCode} = … ${props.baseUnitCode}`, value: 'unit-holds-base' },
    { label: `1 ${props.baseUnitCode} = … ${props.unitCode}`, value: 'base-holds-unit' },
]);

function apply() {
    emit('apply', result.value.conversion);
    emit('update:modelValue', false);
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="calculator">
            <q-card-section>
                <div class="calculator__title">Hitung konversi {{ unitCode }}</div>
                <small class="calculator__hint">Isi seperti Anda menyebutnya sehari-hari. Konversinya dihitung otomatis.</small>
            </q-card-section>

            <q-card-section class="calculator__body">
                <q-btn-toggle v-model="direction" :options="directionOptions" no-caps unelevated toggle-color="primary" color="grey-3" text-color="grey-8" spread />

                <div class="calculator__sentence">
                    <span>1 {{ oneUnitCode }} =</span>
                    <q-input v-model="count" type="number" min="0" step="any" dense outlined autofocus class="calculator__input" aria-label="Jumlah" @keyup.enter="result && apply()" />
                    <span>{{ countUnitCode }}</span>
                </div>

                <p v-if="result" class="calculator__result">
                    Konversi {{ unitCode }}: <b>{{ formatQuantity(result.conversion) }}</b> {{ baseUnitCode }}
                </p>
                <p v-if="result && !result.isExact" class="calculator__warning">
                    Konversi ini tidak pas dalam 3 desimal, jadi hitungan stok akan meleset sedikit. Lebih aman menjadikan {{ unitCode }} satuan terkecil (satuan dasar), supaya konversinya bulat.
                </p>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Batal" @click="emit('update:modelValue', false)" />
                <q-btn color="primary" unelevated no-caps label="Pakai" :disable="!result" @click="apply" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.calculator {
    width: 440px;
    max-width: 94vw;
}

.calculator__title {
    font-size: 17px;
    font-weight: 700;
}

.calculator__hint {
    color: var(--app-mute);
}

.calculator__body {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.calculator__sentence {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 600;
}

.calculator__input {
    flex: 1;
    min-width: 90px;
}

.calculator__result {
    margin: 0;
    padding: 8px 12px;
    border-radius: 10px;
    background: #f6f6f7;
}

.calculator__warning {
    margin: 0;
    padding: 8px 12px;
    border-radius: 10px;
    background: #fdf1e0;
    color: #c2710c;
    font-size: 13px;
}
</style>
