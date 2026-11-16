<script setup>
import { computed, ref, watch } from 'vue';
import { roundMoney } from '../../Composables/useCart';
import { formatRupiah } from '../../Composables/useMoneyFormat';
import MoneyInput from '../MoneyInput.vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /**
     * What the server lets the cashier see: counts only, no amount of any kind, so the drawer is counted blind.
     * @type {{sale_count: number, cod: {delivered_count: number, undelivered_count: number}, opening_cash: string}}
     */
    summary: { type: Object, required: true },
    isProcessing: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const countedCash = ref('');
const drawerLeftCash = ref('');
const isDrawerLeftEdited = ref(false);
const note = ref('');

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            countedCash.value = '';
            drawerLeftCash.value = '';
            isDrawerLeftEdited.value = false;
            note.value = '';
        }
    },
);

/** Until the cashier types an amount, the drawer keeps what the shift opened with, as long as the count covers it. */
watch(countedCash, (counted) => {
    if (!isDrawerLeftEdited.value && counted !== '' && counted !== null) {
        drawerLeftCash.value = String(Math.min(Number(props.summary.opening_cash), Number(counted)));
    }
});

const isCountEntered = computed(() => countedCash.value !== '' && countedCash.value !== null);

const isDrawerLeftEntered = computed(() => drawerLeftCash.value !== '' && drawerLeftCash.value !== null);

const isDrawerLeftTooMuch = computed(() => isCountEntered.value && isDrawerLeftEntered.value && Number(drawerLeftCash.value) > Number(countedCash.value));

const deposit = computed(() => roundMoney(Number(countedCash.value) - Number(drawerLeftCash.value)));

const canConfirm = computed(
    () => isCountEntered.value
        && isDrawerLeftEntered.value
        && Number(drawerLeftCash.value) >= 0
        && !isDrawerLeftTooMuch.value
        && !props.isProcessing,
);

function confirm() {
    emit('confirm', { counted_cash: Number(countedCash.value), drawer_left_cash: Number(drawerLeftCash.value), closing_note: note.value.trim() || null });
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)">
        <q-card class="close">
            <q-card-section>
                <div class="close__title">Tutup shift</div>

                <dl class="close__summary">
                    <div><dt>Jumlah nota</dt><dd>{{ summary.sale_count }}</dd></div>
                    <template v-if="summary.cod.delivered_count + summary.cod.undelivered_count > 0">
                        <div class="close__cod"><dt>Nota COD sudah diantar</dt><dd>{{ summary.cod.delivered_count }}</dd></div>
                        <div class="close__cod close__cod--open"><dt>Nota COD belum diantar</dt><dd>{{ summary.cod.undelivered_count }}</dd></div>
                    </template>
                </dl>
            </q-card-section>

            <q-card-section class="close__form">
                <MoneyInput v-model="countedCash" allow-decimal outlined dense prefix="Rp" label="Tunai yang dihitung" autofocus />

                <MoneyInput
                    v-model="drawerLeftCash"
                    allow-decimal
                    outlined
                    dense
                    prefix="Rp"
                    label="Ditinggal di laci untuk shift berikutnya"
                    :error="isDrawerLeftTooMuch"
                    error-message="Tidak boleh lebih dari tunai yang dihitung."
                    @update:model-value="isDrawerLeftEdited = true"
                />
                <div v-if="isCountEntered && isDrawerLeftEntered && !isDrawerLeftTooMuch" class="close__deposit">Setoran: {{ formatRupiah(deposit) }}</div>

                <q-input v-model="note" outlined dense maxlength="500" label="Catatan (opsional)" />
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

.close__summary dt small {
    color: var(--app-mute);
    font-size: 12px;
}

.close__summary .close__cod {
    margin-top: 2px;
    padding: 4px 10px;
    border-radius: 8px;
    background: #f1f5ff;
}

.close__summary .close__cod--open {
    background: #fff4e0;
}

.close__form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.close__deposit {
    padding: 8px 12px;
    border-radius: 10px;
    background: #f6f6f7;
    font-weight: 600;
}
</style>
