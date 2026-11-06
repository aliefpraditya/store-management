<script setup>
import { computed, ref, watch } from 'vue';
import { roundMoney } from '../../Composables/useCart';
import { formatRupiah } from '../../Composables/useMoneyFormat';
import OrderFields from './OrderFields.vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** What is paid now: the receipt part of the cart, or what is left of an order being collected. */
    total: { type: Number, required: true },
    /** @type {{value: string, label: string}[]} */
    paymentMethods: { type: Array, required: true },
    isProcessing: { type: Boolean, default: false },
    /** The cart divided into receipt and order when part of it is not in stock; null when all of it is. */
    split: { type: Object, default: null },
    /** The order being collected (its deposit is already paid and `total` is what is left), or null for a normal sale. */
    collectingOrder: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const COD = 'cod';

const METHOD_ICONS = { cash: 'payments', transfer: 'account_balance', qris: 'qr_code_2', edc: 'credit_card', cod: 'local_shipping' };

const blankLine = (method) => ({ method, amount: '', reference: '' });

const blankDelivery = () => ({ recipient_name: '', recipient_phone: '', recipient_address: '', driver_name: '', vehicle_number: '', notes: '' });

const blankOrderForm = () => ({ phone: '', sourceNote: '', expectedDate: '', depositAmount: '' });

const customerName = ref('');
const lines = ref([blankLine('cash')]);
const delivery = ref(blankDelivery());
const orderForm = ref(blankOrderForm());

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            customerName.value = props.collectingOrder?.customer_name ?? '';
            lines.value = [blankLine('cash')];
            delivery.value = blankDelivery();
            orderForm.value = blankOrderForm();
        }
    },
);

const isOrderMade = computed(() => props.split !== null && props.split.orderLines.length > 0);

const depositAmount = computed(() => (isOrderMade.value ? Number(orderForm.value.depositAmount) || 0 : 0));

/**
 * What the customer hands over now: the receipt, plus the deposit of the order made with it. The payments are one tender
 * for both, so the change is worked out once. Nothing is due when the whole cart is an order without a deposit, or when the
 * deposit already covers the order being collected.
 */
const dueNow = computed(() => roundMoney(props.total + depositAmount.value));

const isPaymentNeeded = computed(() => dueNow.value > 0);

/**
 * Entering or changing the deposit moves what is due, so a payment that was exactly right stays exactly right: the
 * cashier does not have to retype it after pressing "Bayar lunas". A payment that was not exact (cash handed over with
 * change to give, or nothing entered yet) is left alone.
 */
watch(dueNow, (newDue, oldDue) => {
    const lastMoneyLine = [...lines.value].reverse().find((line) => line.method !== COD);

    if (!props.modelValue || !lastMoneyLine || paidTotal.value !== oldDue || paidTotal.value <= 0) {
        return;
    }

    lastMoneyLine.amount = roundMoney(amountOf(lastMoneyLine) + newDue - oldDue) || '';
});

const isOrderReady = computed(
    () => !isOrderMade.value || (customerName.value.trim() !== '' && orderForm.value.phone.trim() !== '' && depositAmount.value >= 0 && depositAmount.value <= props.split.orderTotal),
);

const amountOf = (line) => Number(line.amount) || 0;

const paidTotal = computed(() => roundMoney(lines.value.reduce((sum, line) => sum + amountOf(line), 0)));

const cashPaid = computed(() => lines.value.filter((line) => line.method === 'cash').reduce((sum, line) => sum + amountOf(line), 0));

const changeAmount = computed(() => roundMoney(paidTotal.value - dueNow.value));

const isCovered = computed(() => paidTotal.value >= dueNow.value);

const isChangeFromCash = computed(() => changeAmount.value <= cashPaid.value);

const isCod = computed(() => lines.value.some((line) => line.method === COD));

/** COD is what is left to pay on delivery, so with it the payments add up to the total exactly. */
const isCodTheRest = computed(() => !isCod.value || paidTotal.value === dueNow.value);

/** A deposit is money received now, so it cannot ride on a COD payment. */
const isCodWithDeposit = computed(() => isCod.value && depositAmount.value > 0);

/** A COD sale goes out with a delivery note, so who and where must be known before it can be saved. */
const isDeliveryComplete = computed(() => !isCod.value || (effectiveRecipientName.value !== '' && delivery.value.recipient_address.trim() !== ''));

const effectiveRecipientName = computed(() => (delivery.value.recipient_name.trim() || customerName.value.trim()));

const isSalePartReady = computed(() => !isPaymentNeeded.value || (isCovered.value && isChangeFromCash.value && isCodTheRest.value && isDeliveryComplete.value && !isCodWithDeposit.value));

const canConfirm = computed(() => (isPaymentNeeded.value || isOrderMade.value || props.collectingOrder !== null) && isSalePartReady.value && isOrderReady.value && !props.isProcessing);

const confirmLabel = computed(() => {
    if (props.collectingOrder !== null) {
        return isPaymentNeeded.value ? `Ambil Pesanan, bayar ${formatRupiah(dueNow.value)}` : 'Ambil Pesanan';
    }

    if (!isPaymentNeeded.value) {
        return 'Simpan Pesanan';
    }

    return isCod.value ? 'Simpan Nota COD' : `Bayar ${formatRupiah(dueNow.value)}`;
});

/** The COD method can be chosen on one line only, and not while the order takes a deposit. */
const isMethodDisabled = (line, method) => method === COD && (depositAmount.value > 0 || lines.value.some((other) => other !== line && other.method === COD));

/** Choosing COD fills in what is left to pay, the way the COD line is meant to be used. */
function chooseMethod(line, method) {
    line.method = method;

    if (method !== COD || amountOf(line) > 0) {
        return;
    }

    const paidByOthers = lines.value.filter((other) => other !== line).reduce((sum, other) => sum + amountOf(other), 0);

    line.amount = roundMoney(Math.max(dueNow.value - paidByOthers, 0)) || '';
}

function addLine() {
    const remaining = roundMoney(Math.max(dueNow.value - paidTotal.value, 0));

    lines.value.push({ ...blankLine('qris'), amount: remaining > 0 ? remaining : '' });
}

function removeLine(index) {
    lines.value.splice(index, 1);
}

/** The order part of the checkout as the request wants it; the lines are filled in by the till, which owns the cart. */
function orderInput() {
    return {
        customer_name: customerName.value.trim(),
        customer_phone: orderForm.value.phone.trim(),
        source_note: orderForm.value.sourceNote.trim() || null,
        expected_date: orderForm.value.expectedDate || null,
        deposit_amount: depositAmount.value > 0 ? depositAmount.value : null,
    };
}

function confirm() {
    emit('confirm', {
        customer_name: customerName.value.trim() || null,
        payments: lines.value
            .filter((line) => isPaymentNeeded.value && amountOf(line) > 0)
            .map((line) => ({ method: line.method, amount: amountOf(line), reference: ['cash', COD].includes(line.method) ? null : line.reference.trim() || null })),
        delivery_note: isCod.value ? { ...delivery.value, recipient_name: effectiveRecipientName.value } : null,
        order: isOrderMade.value ? orderInput() : null,
    });
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)" @keydown.enter.prevent="canConfirm && confirm()">
        <q-card class="payment">
            <q-card-section class="payment__head">
                <span v-if="collectingOrder">Sisa tagihan pesanan</span>
                <span v-else-if="isOrderMade">Bayar sekarang</span>
                <span v-else>Total belanja</span>
                <b>{{ formatRupiah(dueNow) }}</b>
            </q-card-section>

            <q-card-section v-if="isOrderMade" class="payment__breakdown">
                <span>Nota penjualan <b>{{ formatRupiah(total) }}</b></span>
                <span>Uang muka pesanan <b>{{ formatRupiah(depositAmount) }}</b></span>
            </q-card-section>

            <q-card-section v-if="collectingOrder" class="payment__collect">
                <b>{{ collectingOrder.number }} · {{ collectingOrder.customer_name }}</b>
                <span>Total pesanan {{ formatRupiah(collectingOrder.total) }}, uang muka sudah diterima {{ formatRupiah(collectingOrder.deposit) }}.</span>
            </q-card-section>

            <q-card-section class="payment__body">
                <p v-if="!isPaymentNeeded && collectingOrder" class="payment__note">Uang muka sudah melunasi pesanan ini, tidak ada yang perlu dibayar lagi.</p>
                <p v-else-if="!isPaymentNeeded" class="payment__note">Tidak ada barang yang tersedia sekarang, jadi tidak ada nota penjualan. Seluruh keranjang dicatat sebagai pesanan.</p>

                <template v-if="isPaymentNeeded">
                <div v-for="(line, index) in lines" :key="index" class="payment__line">
                    <div v-if="lines.length > 1" class="payment__line-head">
                        <b class="payment__line-title">Pembayaran {{ index + 1 }}</b>
                        <q-btn flat round dense size="sm" icon="close" color="grey-7" class="payment__remove" :aria-label="`Hapus pembayaran ${index + 1}`" @click="removeLine(index)" />
                    </div>
                    <div class="payment__methods" role="group" aria-label="Metode pembayaran">
                        <q-btn
                            v-for="method in paymentMethods"
                            :key="method.value"
                            no-caps
                            :unelevated="line.method === method.value"
                            :outline="line.method !== method.value"
                            :color="line.method === method.value ? 'primary' : 'grey-7'"
                            :icon="METHOD_ICONS[method.value] ?? 'payments'"
                            :label="method.label"
                            :disable="isMethodDisabled(line, method.value)"
                            :aria-pressed="line.method === method.value"
                            class="payment__method"
                            @click="chooseMethod(line, method.value)"
                        />
                    </div>
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

                <div class="payment__split">
                    <q-btn outline no-caps color="primary" icon="add" label="Tambah metode bayar" @click="addLine" />
                    <span>Bayar campur, mis. sebagian tunai, sisanya transfer.</span>
                </div>
                </template>

                <q-input v-if="!collectingOrder" v-model="customerName" outlined dense maxlength="255" :label="isOrderMade ? 'Nama pelanggan (wajib untuk pesanan)' : 'Nama pelanggan (opsional)'" class="payment__customer" />

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

                <OrderFields v-if="isOrderMade" v-model="orderForm" :order-lines="split.orderLines" :order-total="split.orderTotal" />
            </q-card-section>

            <q-card-section v-if="isPaymentNeeded" class="payment__result" :class="{ 'payment__result--short': !isCovered }">
                <template v-if="!isCovered">
                    <span>Kurang</span>
                    <b>{{ formatRupiah(roundMoney(dueNow - paidTotal)) }}</b>
                </template>
                <template v-else>
                    <span>Kembalian</span>
                    <b>{{ formatRupiah(changeAmount) }}</b>
                </template>
            </q-card-section>
            <q-card-section v-if="isCovered && !isChangeFromCash" class="payment__warning">Kembalian hanya bisa diberikan dari pembayaran tunai.</q-card-section>
            <q-card-section v-if="isCodWithDeposit" class="payment__warning">COD tidak bisa digabung dengan uang muka pesanan. Ganti metodenya atau kosongkan uang muka.</q-card-section>
            <q-card-section v-else-if="isCod && !isCodTheRest" class="payment__warning">Jumlah COD harus sisa tagihan setelah pembayaran lain, tepat sampai total.</q-card-section>
            <q-card-section v-else-if="isCod && !isDeliveryComplete" class="payment__warning">Isi nama penerima (atau nama pelanggan) dan alamat pengantaran.</q-card-section>

            <q-card-actions align="right" class="payment__actions">
                <q-btn flat no-caps label="Batal" :disable="isProcessing" @click="emit('update:modelValue', false)" />
                <q-btn color="primary" unelevated no-caps :label="confirmLabel" :loading="isProcessing" :disable="!canConfirm" @click="confirm" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.payment {
    width: 680px;
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
    gap: 20px;
}

.payment__line {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--app-line);
    border-radius: 10px;
    background: #f8f9fb;
}

.payment__line-head {
    display: flex;
    align-items: center;
    width: 100%;
}

.payment__remove {
    margin-left: auto;
}

.payment__line-title {
    color: var(--app-ink-soft);
    font-size: 13px;
}

.payment__amount {
    display: flex;
    align-items: center;
    gap: 6px;
}

.payment__amount :deep(.q-field) {
    flex: 1;
}

.payment__breakdown {
    display: flex;
    justify-content: space-between;
    padding-block: 8px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-ink-soft);
    font-size: 13px;
}

.payment__collect {
    display: flex;
    flex-direction: column;
    gap: 2px;
    background: #fff4e0;
    color: #92400e;
    font-size: 13px;
}

.payment__note {
    margin: 0;
    color: var(--app-ink-soft);
    font-size: 13px;
}

.payment__methods {
    display: flex;
    gap: 8px;
}

.payment__method {
    flex: 1 1 0;
    min-width: 0;
    min-height: 40px;
    padding: 4px 8px;
    background: #fff;
}

.payment__split {
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: #6b7280;
    font-size: 12px;
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
