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

const COD = 'cod';

const blankLine = (method) => ({ method, amount: '', reference: '' });

const blankDelivery = () => ({ recipient_name: '', recipient_phone: '', recipient_address: '', driver_name: '', vehicle_number: '', notes: '' });

const customerName = ref('');
const lines = ref([blankLine('cash')]);
const delivery = ref(blankDelivery());

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            customerName.value = '';
            lines.value = [blankLine('cash')];
            delivery.value = blankDelivery();
        }
    },
);

const amountOf = (line) => Number(line.amount) || 0;

const paidTotal = computed(() => roundMoney(lines.value.reduce((sum, line) => sum + amountOf(line), 0)));

const cashPaid = computed(() => lines.value.filter((line) => line.method === 'cash').reduce((sum, line) => sum + amountOf(line), 0));

const changeAmount = computed(() => roundMoney(paidTotal.value - props.total));

const isCovered = computed(() => paidTotal.value >= props.total);

const isChangeFromCash = computed(() => changeAmount.value <= cashPaid.value);

const isCod = computed(() => lines.value.some((line) => line.method === COD));

/** COD is what is left to pay on delivery, so with it the payments add up to the total exactly. */
const isCodTheRest = computed(() => !isCod.value || paidTotal.value === roundMoney(props.total));

/** A COD sale goes out with a delivery note, so who and where must be known before it can be saved. */
const isDeliveryComplete = computed(() => !isCod.value || (effectiveRecipientName.value !== '' && delivery.value.recipient_address.trim() !== ''));

const effectiveRecipientName = computed(() => (delivery.value.recipient_name.trim() || customerName.value.trim()));

const canConfirm = computed(() => props.total > 0 && isCovered.value && isChangeFromCash.value && isCodTheRest.value && isDeliveryComplete.value && !props.isProcessing);

/** The COD method can be chosen on one line only. */
const isMethodDisabled = (line, method) => method === COD && lines.value.some((other) => other !== line && other.method === COD);

/** Choosing COD fills in what is left to pay, the way the COD line is meant to be used. */
function onMethodChange(line, method) {
    if (method !== COD || amountOf(line) > 0) {
        return;
    }

    const paidByOthers = lines.value.filter((other) => other !== line).reduce((sum, other) => sum + amountOf(other), 0);

    line.amount = roundMoney(Math.max(props.total - paidByOthers, 0)) || '';
}

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
            .map((line) => ({ method: line.method, amount: amountOf(line), reference: ['cash', COD].includes(line.method) ? null : line.reference.trim() || null })),
        delivery_note: isCod.value ? { ...delivery.value, recipient_name: effectiveRecipientName.value } : null,
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
                        :options="paymentMethods.map((method) => ({ label: method.label, value: method.value, disable: isMethodDisabled(line, method.value) }))"
                        no-caps
                        unelevated
                        dense
                        toggle-color="primary"
                        color="grey-3"
                        text-color="grey-8"
                        @update:model-value="onMethodChange(line, $event)"
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
                        v-if="!['cash', 'cod'].includes(line.method)"
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

                <div v-if="isCod" class="payment__delivery">
                    <b>COD: surat jalan dibuat bersama nota ini</b>
                    <q-input v-model="delivery.recipient_name" outlined dense maxlength="255" :placeholder="customerName || 'Nama penerima'" label="Nama penerima" />
                    <q-input v-model="delivery.recipient_address" outlined dense type="textarea" autogrow maxlength="1000" label="Alamat pengantaran" />
                    <div class="payment__delivery-grid">
                        <q-input v-model="delivery.recipient_phone" outlined dense maxlength="255" label="Telepon" />
                        <q-input v-model="delivery.vehicle_number" outlined dense maxlength="255" label="No. kendaraan" />
                    </div>
                    <q-input v-model="delivery.driver_name" outlined dense maxlength="255" label="Sopir (opsional)" />
                </div>
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
            <q-card-section v-if="isCod && !isCodTheRest" class="payment__warning">Jumlah COD harus sisa tagihan setelah pembayaran lain, tepat sampai total.</q-card-section>
            <q-card-section v-else-if="isCod && !isDeliveryComplete" class="payment__warning">Isi nama penerima (atau nama pelanggan) dan alamat pengantaran.</q-card-section>

            <q-card-actions align="right" class="payment__actions">
                <q-btn flat no-caps label="Batal" :disable="isProcessing" @click="emit('update:modelValue', false)" />
                <q-btn color="primary" unelevated no-caps :label="isCod ? 'Simpan Nota COD' : `Bayar ${formatRupiah(total)}`" :loading="isProcessing" :disable="!canConfirm" @click="confirm" />
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

.payment__delivery {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: #fafafb;
}

.payment__delivery-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
</style>
