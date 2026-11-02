<script setup>
import { computed, ref, watch } from 'vue';
import { roundMoney } from '../../Composables/useCart';
import { formatRupiah } from '../../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    total: { type: Number, required: true },
    /** @type {{value: string, label: string}[]} */
    paymentMethods: { type: Array, required: true },
    isProcessing: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const CASH_SHORTCUTS = [20000, 50000, 100000];

const blankLine = (method) => ({ method, amount: '', reference: '' });

const customerName = ref('');
const lines = ref([blankLine('cash')]);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            customerName.value = '';
            lines.value = [blankLine('cash')];
        }
    },
);

const amountOf = (line) => Number(line.amount) || 0;

const paidTotal = computed(() => roundMoney(lines.value.reduce((sum, line) => sum + amountOf(line), 0)));

const cashPaid = computed(() => lines.value.filter((line) => line.method === 'cash').reduce((sum, line) => sum + amountOf(line), 0));

const changeAmount = computed(() => roundMoney(paidTotal.value - props.total));

const isCovered = computed(() => paidTotal.value >= props.total);

const isChangeFromCash = computed(() => changeAmount.value <= cashPaid.value);

const canConfirm = computed(() => props.total > 0 && isCovered.value && isChangeFromCash.value && !props.isProcessing);

const firstCashLine = computed(() => lines.value.find((line) => line.method === 'cash'));

function setCash(amount) {
    const line = firstCashLine.value ?? lines.value[0];

    line.method = 'cash';
    line.amount = amount;
}

function addLine() {
    const remaining = roundMoney(Math.max(props.total - paidTotal.value, 0));

    lines.value.push({ ...blankLine('qris'), amount: remaining > 0 ? remaining : '' });
}

function removeLine(index) {
    lines.value.splice(index, 1);
}

function confirm() {
    emit('confirm', {
        customer_name: customerName.value.trim() || null,
        payments: lines.value
            .filter((line) => amountOf(line) > 0)
            .map((line) => ({ method: line.method, amount: amountOf(line), reference: line.method === 'cash' ? null : line.reference.trim() || null })),
    });
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)" @keydown.enter.prevent="canConfirm && confirm()">
        <q-card class="payment">
            <q-card-section class="payment__head">
                <span>Total belanja</span>
                <b>{{ formatRupiah(total) }}</b>
            </q-card-section>

            <q-card-section class="payment__body">
                <div v-for="(line, index) in lines" :key="index" class="payment__line">
                    <q-btn-toggle
                        v-model="line.method"
                        :options="paymentMethods.map((method) => ({ label: method.label, value: method.value }))"
                        no-caps
                        unelevated
                        dense
                        toggle-color="primary"
                        color="grey-3"
                        text-color="grey-8"
                    />
                    <div class="payment__amount">
                        <q-input
                            v-model="line.amount"
                            type="number"
                            min="0"
                            step="any"
                            outlined
                            dense
                            prefix="Rp"
                            placeholder="0"
                            :autofocus="index === 0"
                            :aria-label="`Jumlah ${line.method}`"
                        />
                        <q-btn v-if="lines.length > 1" flat round dense icon="close" color="grey-7" aria-label="Hapus pembayaran" @click="removeLine(index)" />
                    </div>
                    <q-input
                        v-if="line.method !== 'cash'"
                        v-model="line.reference"
                        outlined
                        dense
                        maxlength="255"
                        placeholder="No. referensi (opsional)"
                    />
                </div>

                <div class="payment__shortcuts">
                    <q-btn outline no-caps dense color="primary" label="Uang pas" @click="setCash(total)" />
                    <q-btn v-for="amount in CASH_SHORTCUTS" :key="amount" outline no-caps dense color="grey-8" :label="formatRupiah(amount)" @click="setCash(amount)" />
                    <q-btn flat no-caps dense color="primary" icon="add" label="Metode lain" @click="addLine" />
                </div>

                <q-input v-model="customerName" outlined dense maxlength="255" label="Nama pelanggan (opsional)" class="payment__customer" />
            </q-card-section>

            <q-card-section class="payment__result" :class="{ 'payment__result--short': !isCovered }">
                <template v-if="!isCovered">
                    <span>Kurang</span>
                    <b>{{ formatRupiah(roundMoney(total - paidTotal)) }}</b>
                </template>
                <template v-else>
                    <span>Kembalian</span>
                    <b>{{ formatRupiah(changeAmount) }}</b>
                </template>
            </q-card-section>
            <q-card-section v-if="isCovered && !isChangeFromCash" class="payment__warning">Kembalian hanya bisa diberikan dari pembayaran tunai.</q-card-section>

            <q-card-actions align="right" class="payment__actions">
                <q-btn flat no-caps label="Batal" :disable="isProcessing" @click="emit('update:modelValue', false)" />
                <q-btn color="primary" unelevated no-caps :label="`Bayar ${formatRupiah(total)}`" :loading="isProcessing" :disable="!canConfirm" @click="confirm" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.payment {
    width: 480px;
    max-width: 94vw;
}

.payment__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    background: var(--app-dark);
    color: #b4b8c2;
}

.payment__head b {
    color: #fff;
    font-size: 26px;
}

.payment__body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment__line {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 1px dashed var(--app-line);
}

.payment__amount {
    display: flex;
    align-items: center;
    gap: 6px;
}

.payment__amount :deep(.q-field) {
    flex: 1;
}

.payment__shortcuts {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.payment__result {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    background: #e7f8f1;
    color: #059669;
}

.payment__result b {
    font-size: 24px;
}

.payment__result--short {
    background: #fde8ee;
    color: #be123c;
}

.payment__warning {
    color: #be123c;
    font-size: 13px;
}
</style>
