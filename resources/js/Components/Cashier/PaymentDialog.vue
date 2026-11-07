<script setup>
import { computed, ref, watch } from 'vue';
import { roundMoney } from '../../Composables/useCart';
import { formatRupiah } from '../../Composables/useMoneyFormat';
import { formatQuantity } from '../../Composables/useQuantityFormat';
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

/** What the arrows of the amount field (and the up and down keys) move it by; any amount can still be typed. */
const AMOUNT_STEP = 50000;

const METHOD_ICONS = { cash: 'payments', transfer: 'account_balance', qris: 'qr_code_2', edc: 'credit_card', cod: 'local_shipping' };

const blankLine = (method) => ({ method, amount: '', reference: '' });

/** The order being collected already knows who to deliver to and where, so the delivery note starts from that. */
const deliveryOf = (order) => ({ recipient_name: order?.customer_name ?? '', recipient_phone: order?.customer_phone ?? '', recipient_address: order?.customer_address ?? '', notes: '' });

const blankOrderForm = () => ({ phone: '', address: '', isPayingNow: false });

const customerName = ref(props.collectingOrder?.customer_name ?? '');
const lines = ref([blankLine('cash')]);
const delivery = ref(deliveryOf(props.collectingOrder));
const orderForm = ref(blankOrderForm());

/** Whether the cashier chose to change the recipient that came from the order. */
const isDeliveryEdited = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            customerName.value = props.collectingOrder?.customer_name ?? '';
            lines.value = [blankLine('cash')];
            delivery.value = deliveryOf(props.collectingOrder);
            orderForm.value = blankOrderForm();
            isDeliveryEdited.value = false;
            isOriginSaleIncluded.value = true;
            isDeliveryHeld.value = true;
        }
    },
);

/**
 * What of the receipt made together with the order was not delivered yet. It can go out on the same delivery note, so the
 * customer is delivered once instead of twice.
 */
const originSaleUndelivered = computed(() => props.collectingOrder?.origin_sale_undelivered ?? []);

const isOriginSaleIncluded = ref(true);

/** Whether the COD receipt made with an order waits for the order, so one delivery note and one collection serve both. */
const isDeliveryHeld = ref(true);

const isDeliveryHeldNow = computed(() => isCod.value && isOrderMade.value && isDeliveryHeld.value);

/** An order that has an address goes ahead as it is: the recipient is shown, not asked again, unless the cashier changes it. */
const isDeliveryFromOrder = computed(() => props.collectingOrder !== null && Boolean(props.collectingOrder.customer_address) && !isDeliveryEdited.value);

const isOrderMade = computed(() => props.split !== null && props.split.orderLines.length > 0);

const orderTotal = computed(() => (isOrderMade.value ? props.split.orderTotal : 0));

const isOrderPaidNow = computed(() => isOrderMade.value && orderForm.value.isPayingNow);

/** Something is paid now: the receipt, or a deposit on the order made with it. Not when the deposit already covers an order being collected. */
const isPaymentNeeded = computed(() => props.total > 0 || isOrderPaidNow.value);

const amountOf = (line) => Number(line.amount) || 0;

const paidTotal = computed(() => roundMoney(lines.value.reduce((sum, line) => sum + amountOf(line), 0)));

/**
 * When the customer pays for the order now, what was paid beyond the receipt goes to the order as its deposit, up to the
 * order's total; the cashier does not enter it twice, and only what is paid beyond the order too is change. Without
 * that choice, anything paid beyond the receipt is change, as at any till.
 */
const depositAmount = computed(() => (isOrderPaidNow.value ? roundMoney(Math.min(Math.max(paidTotal.value - props.total, 0), orderTotal.value)) : 0));

const cashPaid = computed(() => lines.value.filter((line) => line.method === 'cash').reduce((sum, line) => sum + amountOf(line), 0));

const changeAmount = computed(() => roundMoney(Math.max(paidTotal.value - props.total - depositAmount.value, 0)));

const isCovered = computed(() => paidTotal.value >= props.total);

const isChangeFromCash = computed(() => changeAmount.value <= cashPaid.value);

const isCod = computed(() => lines.value.some((line) => line.method === COD));

/** COD is what is left to pay on delivery, so with it the payments add up to the total exactly. */
const isCodTheRest = computed(() => !isCod.value || paidTotal.value === roundMoney(props.total));

const isOrderReady = computed(() => !isOrderMade.value || (customerName.value.trim() !== '' && orderForm.value.phone.trim() !== ''));

/** A deposit is money received now, so it cannot ride on a COD payment. */
const isCodWithDeposit = computed(() => isCod.value && depositAmount.value > 0);

/** A COD sale goes out with a delivery note, so who and where must be known before it can be saved. */
const isDeliveryComplete = computed(() => !isCod.value || isDeliveryHeldNow.value || (effectiveRecipientName.value !== '' && delivery.value.recipient_address.trim() !== ''));

/** The customer is the one the goods are delivered to, so the delivery note takes the customer's name and does not ask for it twice. */
const effectiveRecipientName = computed(() => customerName.value.trim());

const isSalePartReady = computed(() => !isPaymentNeeded.value || (isCovered.value && isChangeFromCash.value && isCodTheRest.value && isDeliveryComplete.value && !isCodWithDeposit.value));

const canConfirm = computed(() => (isPaymentNeeded.value || isOrderMade.value || props.collectingOrder !== null) && isSalePartReady.value && isOrderReady.value && !props.isProcessing);

const confirmLabel = computed(() => {
    if (props.collectingOrder !== null) {
        if (isCod.value) {
            return 'Buat Nota COD & Surat Jalan';
        }

        return isPaymentNeeded.value ? `Ambil Pesanan, bayar ${formatRupiah(props.total)}` : 'Ambil Pesanan';
    }

    if (props.total <= 0) {
        return depositAmount.value > 0 ? `Simpan Pesanan, DP ${formatRupiah(depositAmount.value)}` : 'Simpan Pesanan';
    }

    if (isCod.value) {
        return 'Simpan Nota COD';
    }

    return `Bayar ${formatRupiah(roundMoney(props.total + depositAmount.value))}`;
});

/** The COD method can be chosen on one line only, only when there is a receipt to pay, and not while the order takes a deposit. */
const isMethodDisabled = (line, method) => method === COD && (props.total <= 0 || depositAmount.value > 0 || lines.value.some((other) => other !== line && other.method === COD));

/** Choosing COD fills in what is left to pay, the way the COD line is meant to be used. */
function chooseMethod(line, method) {
    line.method = method;

    if (method !== COD || amountOf(line) > 0) {
        return;
    }

    const paidByOthers = lines.value.filter((other) => other !== line).reduce((sum, other) => sum + amountOf(other), 0);

    line.amount = roundMoney(Math.max(props.total - paidByOthers, 0)) || '';
}

function addLine() {
    const remaining = roundMoney(Math.max(props.total - paidTotal.value, 0));

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
        customer_address: orderForm.value.address.trim() || null,
        deposit_amount: depositAmount.value > 0 ? depositAmount.value : null,
    };
}

function confirm() {
    emit('confirm', {
        customer_name: customerName.value.trim() || null,
        payments: lines.value
            .filter((line) => isPaymentNeeded.value && amountOf(line) > 0)
            .map((line) => ({ method: line.method, amount: amountOf(line), reference: ['cash', COD].includes(line.method) ? null : line.reference.trim() || null })),
        delivery_note: isCod.value && !isDeliveryHeldNow.value ? { ...delivery.value, recipient_name: effectiveRecipientName.value, include_linked_sale_items: originSaleUndelivered.value.length > 0 && isOriginSaleIncluded.value } : null,
        ...(isDeliveryHeldNow.value ? { hold_delivery: true } : {}),
        order: isOrderMade.value ? orderInput() : null,
    });
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)" @keydown.enter.prevent="canConfirm && confirm()">
        <q-card class="payment" :class="{ 'payment--wide': isOrderMade }">
            <q-card-section class="payment__head">
                <span v-if="collectingOrder">{{ isCod ? 'Sisa tagihan pesanan, dibayar saat barang diantar' : 'Sisa tagihan pesanan' }}</span>
                <span v-else-if="isOrderMade && total > 0">Nota penjualan</span>
                <span v-else-if="isOrderMade">Nota penjualan (tidak ada)</span>
                <span v-else>Total belanja</span>
                <b>{{ formatRupiah(total) }}</b>
            </q-card-section>

            <q-card-section v-if="isOrderMade" class="payment__breakdown">
                <span>Pesanan (PO) <b>{{ formatRupiah(orderTotal) }}</b></span>
                <span v-if="isOrderPaidNow">Uang muka dari kelebihan bayar <b>{{ formatRupiah(depositAmount) }}</b></span>
                <span v-else>Dibayar saat barang diambil</span>
            </q-card-section>

            <q-card-section v-if="collectingOrder" class="payment__collect">
                <b>{{ collectingOrder.number }} · {{ collectingOrder.customer_name }}</b>
                <span>Total pesanan {{ formatRupiah(collectingOrder.total) }}, uang muka sudah diterima {{ formatRupiah(collectingOrder.deposit) }}.</span>
            </q-card-section>

            <q-card-section class="payment__body" :class="{ 'payment__body--two-columns': isOrderMade }">
                <div class="payment__main">
                <p v-if="!isPaymentNeeded && collectingOrder" class="payment__note">Uang muka sudah melunasi pesanan ini, tidak ada yang perlu dibayar lagi.</p>
                <p v-else-if="isOrderMade && total <= 0 && !isOrderPaidNow" class="payment__note">Tidak ada barang yang tersedia sekarang, jadi tidak ada nota penjualan. Seluruh keranjang dicatat sebagai pesanan. Centang "Pelanggan bayar pesanan sekarang" di bawah bila ada uang muka.</p>

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
                            :step="AMOUNT_STEP"
                            outlined
                            prefix="Rp"
                            placeholder="0"
                            class="payment__amount-field"
                            input-class="payment__amount-input"
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
                    <q-btn flat dense no-caps size="sm" color="grey-8" icon="add" label="Metode bayar lain" @click="addLine">
                        <q-tooltip>Bayar campur, mis. sebagian tunai, sisanya transfer</q-tooltip>
                    </q-btn>
                </div>
                </template>

                <q-input v-if="!collectingOrder" v-model="customerName" outlined dense maxlength="255" :label="isOrderMade ? 'Nama pelanggan (wajib untuk pesanan)' : isCod ? 'Nama pelanggan (wajib untuk COD)' : 'Nama pelanggan (opsional)'" class="payment__customer" />

                <div v-if="isCod && isOrderMade" class="payment__delivery">
                    <q-checkbox v-model="isDeliveryHeld" dense class="payment__held">
                        <span class="payment__held-text">
                            <b>Antar bersama pesanan</b>
                            <small>Barang nota ini disimpan di toko. Surat jalan dan pembayaran COD diselesaikan sekali saat barang pesanan datang.</small>
                        </span>
                    </q-checkbox>
                </div>

                <div v-if="isCod && !isDeliveryHeldNow" class="payment__delivery">
                    <b>COD: surat jalan dibuat bersama nota ini</b>

                    <div v-if="isDeliveryFromOrder" class="payment__delivery-known">
                        <span><b>{{ delivery.recipient_name }}</b><template v-if="delivery.recipient_phone"> · {{ delivery.recipient_phone }}</template></span>
                        <span>{{ delivery.recipient_address }}</span>
                        <q-btn flat dense no-caps color="primary" label="Ubah" @click="isDeliveryEdited = true" />
                    </div>

                    <template v-else>
                        <q-input v-model="delivery.recipient_phone" outlined dense maxlength="255" label="Telepon" />
                        <q-input v-model="delivery.recipient_address" outlined dense type="textarea" autogrow maxlength="1000" label="Alamat pengantaran" />
                    </template>
                    <q-checkbox v-if="originSaleUndelivered.length > 0" v-model="isOriginSaleIncluded" dense class="payment__delivery-linked">
                        Antar sekalian barang nota {{ collectingOrder.origin_sale_number }} yang belum diantar:
                        {{ originSaleUndelivered.map((line) => `${formatQuantity(line.remaining)} ${line.unit} ${line.name}`).join(', ') }}
                    </q-checkbox>
                    <small v-if="originSaleUndelivered.length > 0 && isOriginSaleIncluded && Number(collectingOrder?.origin_sale_cod_amount) > 0" class="payment__delivery-hint">
                        Nota {{ collectingOrder.origin_sale_number }} masih COD {{ formatRupiah(collectingOrder.origin_sale_cod_amount) }}. Total ditagih di pintu {{ formatRupiah(Number(collectingOrder.origin_sale_cod_amount) + total) }}.
                    </small>
                    <small class="payment__delivery-hint">Sopir dan kendaraan diisi nanti, saat surat jalan ditandai dikirim.</small>
                </div>

                </div>

                <OrderFields v-if="isOrderMade" v-model="orderForm" :order-lines="split.orderLines" :order-total="split.orderTotal" :deposit-amount="depositAmount" />
            </q-card-section>

            <q-card-section v-if="isPaymentNeeded" class="payment__result" :class="{ 'payment__result--short': !isCovered }">
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
            <q-card-section v-if="isCodWithDeposit" class="payment__warning">COD tidak bisa digabung dengan uang muka pesanan. Ganti metodenya atau kosongkan uang muka.</q-card-section>
            <q-card-section v-else-if="isCod && !isCodTheRest" class="payment__warning">Jumlah COD harus sisa tagihan setelah pembayaran lain, tepat sampai total.</q-card-section>
            <q-card-section v-else-if="isCod && !isDeliveryComplete" class="payment__warning">Isi nama pelanggan dan alamat pengantaran.</q-card-section>

            <q-card-actions align="right" class="payment__actions">
                <q-btn flat no-caps label="Batal" :disable="isProcessing" @click="emit('update:modelValue', false)" />
                <q-btn color="primary" unelevated no-caps :label="confirmLabel" :loading="isProcessing" :disable="!canConfirm" @click="confirm" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.payment {
    display: flex;
    flex-direction: column;
    width: 680px;
    max-width: 94vw;
    max-height: 94vh;
}

/** The order form sits beside the payments, so a cart with an order does not become a long page. */
.payment--wide {
    width: 1040px;
}

.payment > :not(.payment__body) {
    flex-shrink: 0;
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
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.payment__main {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.payment__body--two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    align-items: start;
}

@media (max-width: 900px) {
    .payment__body--two-columns {
        grid-template-columns: 1fr;
    }
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

/** The amount is what the cashier types on every sale, so it is the biggest thing in the line. */
.payment__amount-field :deep(.q-field__control) {
    height: 68px;
    background: #fff;
}

.payment__amount-field :deep(.q-field__prefix) {
    padding-right: 8px;
    color: var(--app-ink-soft);
    font-size: 22px;
    font-weight: 600;
}

.payment__amount-field :deep(.payment__amount-input) {
    font-size: 30px;
    font-weight: 700;
    letter-spacing: 0.5px;
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
    padding: 4px 6px;
    background: #fff;
    font-size: 13px;
}

.payment__method :deep(.q-btn__content) {
    flex-wrap: nowrap;
    white-space: nowrap;
}

.payment__held {
    align-items: flex-start;
}

.payment__held-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-left: 6px;
}

.payment__held-text small {
    color: var(--app-mute);
    font-size: 12px;
    line-height: 1.35;
}

.payment__split {
    display: flex;
    justify-content: flex-end;
    margin-top: -12px;
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

.payment__delivery-known {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    font-size: 13px;
}

.payment__delivery-hint {
    color: var(--app-mute);
}
</style>
