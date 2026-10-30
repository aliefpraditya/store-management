<script setup>
import { computed, ref, watch } from 'vue';
import { roundMoney } from '../../Composables/useCart';
import { formatRupiah } from '../../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** @type {{sale_count: number, total_sales: string, payments: Record<string, string>, opening_cash: string, expected_cash: string}} */
    summary: { type: Object, required: true },
    paymentMethods: { type: Array, required: true },
    isProcessing: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const countedCash = ref('');
const note = ref('');

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            countedCash.value = '';
            note.value = '';
        }
    },
);

const isCountEntered = computed(() => countedCash.value !== '' && countedCash.value !== null);

const difference = computed(() => roundMoney(Number(countedCash.value) - Number(props.summary.expected_cash)));

const isNoteNeeded = computed(() => isCountEntered.value && difference.value !== 0);

const canConfirm = computed(() => isCountEntered.value && (!isNoteNeeded.value || note.value.trim() !== '') && !props.isProcessing);

function confirm() {
    emit('confirm', { counted_cash: Number(countedCash.value), closing_note: note.value.trim() || null });
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)">
        <q-card class="close">
            <q-card-section>
                <div class="close__title">Tutup shift</div>

                <dl class="close__summary">
                    <div><dt>Jumlah nota</dt><dd>{{ summary.sale_count }}</dd></div>
                    <div><dt>Total penjualan</dt><dd>{{ formatRupiah(summary.total_sales) }}</dd></div>
                    <div v-for="method in paymentMethods" :key="method.value">
                        <dt>{{ method.label }}</dt>
                        <dd>{{ formatRupiah(summary.payments[method.value]) }}</dd>
                    </div>
                    <div><dt>Modal kas awal</dt><dd>{{ formatRupiah(summary.opening_cash) }}</dd></div>
                    <div class="close__expected"><dt>Tunai seharusnya di laci</dt><dd>{{ formatRupiah(summary.expected_cash) }}</dd></div>
                </dl>
            </q-card-section>

            <q-card-section class="close__form">
                <q-input v-model="countedCash" type="number" min="0" step="any" outlined dense prefix="Rp" label="Tunai yang dihitung" autofocus />

                <div v-if="isCountEntered" class="close__difference" :class="{ 'close__difference--bad': difference !== 0 }">
                    {{ difference === 0 ? 'Kas cocok' : `Selisih ${formatRupiah(difference)}` }}
                </div>

                <q-input v-model="note" outlined dense maxlength="500" :label="isNoteNeeded ? 'Catatan selisih (wajib)' : 'Catatan (opsional)'" />
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Batal" :disable="isProcessing" @click="emit('update:modelValue', false)" />
                <q-btn color="negative" unelevated no-caps label="Tutup Shift" :loading="isProcessing" :disable="!canConfirm" @click="confirm" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.close {
    width: 440px;
    max-width: 94vw;
}

.close__title {
    margin-bottom: 12px;
    font-size: 17px;
    font-weight: 700;
}

.close__summary {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0;
}

.close__summary div {
    display: flex;
    justify-content: space-between;
    color: var(--app-ink-soft);
}

.close__summary dd {
    margin: 0;
    color: var(--app-ink);
    font-weight: 600;
}

.close__summary .close__expected {
    margin-top: 6px;
    padding-top: 8px;
    border-top: 1px solid var(--app-line);
    color: var(--app-ink);
    font-weight: 700;
}

.close__form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.close__difference {
    padding: 8px 12px;
    border-radius: 10px;
    background: #e7f8f1;
    color: #059669;
    font-weight: 600;
}

.close__difference--bad {
    background: #fde8ee;
    color: #be123c;
}
</style>
