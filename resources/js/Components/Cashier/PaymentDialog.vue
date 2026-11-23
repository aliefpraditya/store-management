<script setup>
import { computed, ref, watch } from 'vue';
import { roundMoney } from '../../Composables/useCart';
import { formatRupiah } from '../../Composables/useMoneyFormat';
import { formatQuantity } from '../../Composables/useQuantityFormat';
import MoneyInput from '../MoneyInput.vue';
import OrderFields from './OrderFields.vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** What is paid now: the receipt part of the cart, or what is left of an order being collected. */
    total: { type: Number, required: true },
    /** @type {{value: string, label: string}[]} */
    paymentMethods: { type: Array, required: true },
    /** How the goods reach the customer: taken at the store, or delivered. @type {{value: string, label: string}[]} */
    fulfillmentOptions: { type: Array, required: true },
    isProcessing: { type: Boolean, default: false },
    /** The cart divided into receipt and order when part of it is not in stock; null when all of it is. */
    split: { type: Object, default: null },
    /** The order being collected (its deposit is already paid and `total` is what is left), or null for a normal sale. */
    collectingOrder: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const COD = 'cod';

const IN_STORE = 'in_store';
const DELIVERY = 'delivery';

const FULFILLMENT_ICONS = { [IN_STORE]: 'storefront', [DELIVERY]: 'local_shipping' };

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

/** Goods are taken at the store unless the cashier chooses to deliver them; only a delivered receipt gets a delivery note. */
const fulfillment = ref(IN_STORE);

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
            fulfillment.value = IN_STORE;
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

/** Whether the delivered receipt made with an order waits for the order, so one delivery note and one collection serve both. */
const isDeliveryHeld = ref(true);

/** A receipt is delivered when chosen so; with nothing to pay for the receipt itself (an order alone) there is nothing to deliver. */
const isDelivery = computed(() => fulfillment.value === DELIVERY && props.total > 0);

const isDeliveryHeldNow = computed(() => isDelivery.value && isOrderMade.value && isDeliveryHeld.value);

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

/**
 * COD is not money received now but what is left to pay when the goods arrive, so it is its own choice, apart from the ways
 * money comes in. Its amount is never typed: it is whatever the other payments leave of the total.
 */
const codLine = computed(() => lines.value.find((line) => line.method === COD));

const isCod = computed(() => codLine.value !== undefined);

const isCodOffered = computed(() => props.paymentMethods.some((method) => method.value === COD));

const moneyLines = computed(() => lines.value.filter((line) => line.method !== COD));

const moneyMethods = computed(() => props.paymentMethods.filter((method) => method.value !== COD));

const paidByMoney = computed(() => roundMoney(moneyLines.value.reduce((sum, line) => sum + amountOf(line), 0)));

const codAmount = computed(() => roundMoney(Math.max(props.total - paidByMoney.value, 0)));

/** The other payments already cover the total, so nothing is left to pay on delivery. */
const isCodWithoutBalance = computed(() => isCod.value && codAmount.value === 0);

/** COD is what is left to pay on delivery, so with it the payments add up to the total exactly. */
const isCodTheRest = computed(() => !isCod.value || paidTotal.value === roundMoney(props.total));

const isOrderReady = computed(() => !isOrderMade.value || (customerName.value.trim() !== '' && orderForm.value.phone.trim() !== ''));

/** A deposit is money received now, so it cannot ride on a COD payment. */
const isCodWithDeposit = computed(() => isCod.value && depositAmount.value > 0);

/** A delivered sale goes out with a delivery note, so who and where must be known before it can be saved. */
const isDeliveryComplete = computed(() => !isDelivery.value || isDeliveryHeldNow.value || (effectiveRecipientName.value !== '' && delivery.value.recipient_address.trim() !== ''));

/** The customer is the one the goods are delivered to, so the delivery note takes the customer's name and does not ask for it twice. */
const effectiveRecipientName = computed(() => customerName.value.trim());

const isSalePartReady = computed(() => !isPaymentNeeded.value || (isCovered.value && isChangeFromCash.value && isCodTheRest.value && isDeliveryComplete.value && !isCodWithDeposit.value && !isCodWithoutBalance.value));

const canConfirm = computed(() => (isPaymentNeeded.value || isOrderMade.value || props.collectingOrder !== null) && isSalePartReady.value && isOrderReady.value && !props.isProcessing);

const confirmLabel = computed(() => {
    if (props.collectingOrder !== null) {
        if (isDelivery.value) {
            return isCod.value ? 'Buat Nota COD & Surat Jalan' : 'Bayar & Buat Surat Jalan';
        }

        return isPaymentNeeded.value ? `Ambil Pesanan, bayar ${formatRupiah(props.total)}` : 'Ambil Pesanan';
    }

    if (props.total <= 0) {
        return depositAmount.value > 0 ? `Simpan Pesanan, DP ${formatRupiah(depositAmount.value)}` : 'Simpan Pesanan';
    }

    if (isCod.value) {
        return 'Simpan Nota COD';
    }

    if (isDelivery.value) {
        return `Bayar ${formatRupiah(roundMoney(props.total + depositAmount.value))} & Surat Jalan`;
    }

    return `Bayar ${formatRupiah(roundMoney(props.total + depositAmount.value))}`;
});

/** Taking the goods at the store cannot be chosen while the bill is left to be paid on delivery. */
const fulfillmentChoices = computed(() => props.fulfillmentOptions.map((option) => ({
    ...option,
    icon: FULFILLMENT_ICONS[option.value],
    disable: option.value === IN_STORE && isCod.value,
})));

/** COD can be chosen only when there is a receipt to pay, and not while the order takes a deposit. */
const isCodChoiceDisabled = computed(() => props.total <= 0 || depositAmount.value > 0);

/**
 * Choosing transfer, QRIS or EDC fills in what is left to pay while the line is still empty: none of them gives change,
 * so the rest of the bill is almost always the amount. Cash is left empty because the cashier types what the customer
 * handed over.
 */
function chooseMethod(line, method) {
    line.method = method;

    if (method === 'cash' || amountOf(line) > 0) {
        return;
    }

    const paidByOthers = lines.value.filter((other) => other !== line).reduce((sum, other) => sum + amountOf(other), 0);

    line.amount = roundMoney(Math.max(props.total - paidByOthers, 0)) || '';
}

function addLine() {
    const remaining = roundMoney(Math.max(props.total - paidTotal.value, 0));

    lines.value.push({ ...blankLine('qris'), amount: remaining > 0 ? remaining : '' });
}

function removeLine(line) {
    lines.value.splice(lines.value.indexOf(line), 1);
}

/**
 * Puts the rest of the bill on COD, or takes it off again. COD only exists for a delivered receipt, so choosing it also
 * chooses delivery. Money lines still empty are dropped, so a sale paid on delivery alone does not keep an empty cash line.
 */
function toggleCod() {
    if (codLine.value) {
        removeLine(codLine.value);

        if (lines.value.length === 0) {
            lines.value.push(blankLine('cash'));
        }

        return;
    }

    lines.value = lines.value.filter((line) => amountOf(line) > 0);
    lines.value.push({ ...blankLine(COD), amount: codAmount.value });
    fulfillment.value = DELIVERY;
}

watch(codAmount, (amount) => {
    if (codLine.value) {
        codLine.value.amount = amount;
    }
});

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
        fulfillment: isDelivery.value ? DELIVERY : IN_STORE,
        delivery_note: isDelivery.value && !isDeliveryHeldNow.value ? { ...delivery.value, recipient_name: effectiveRecipientName.value, include_linked_sale_items: originSaleUndelivered.value.length > 0 && isOriginSaleIncluded.value } : null,
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

                <div v-if="total > 0" class="payment__fulfillment">
                    <q-btn-toggle
                        v-model="fulfillment"
                        :options="fulfillmentChoices"
                        no-caps
                        unelevated
                        spread
                        toggle-color="primary"
                        color="grey-3"
                        text-color="grey-8"
                        aria-label="Barang diambil di toko atau diantar"
                    />
                    <small v-if="isCod">COD hanya untuk nota yang diantar.</small>
                    <small v-else-if="!isDelivery">Barang dibawa pelanggan sekarang. Tidak ada surat jalan.</small>
                </div>

                <template v-if="isPaymentNeeded">
                <div v-for="(line, index) in moneyLines" :key="index" class="payment__line">
                    <div v-if="lines.length > 1" class="payment__line-head">
                        <b class="payment__line-title">Pembayaran {{ index + 1 }}</b>
                        <q-btn flat round dense size="sm" icon="close" color="grey-7" class="payment__remove" :aria-label="`Hapus pembayaran ${index + 1}`" @click="removeLine(line)" />
                    </div>
                    <div class="payment__methods" role="group" aria-label="Metode pembayaran">
                        <q-btn
                            v-for="method in moneyMethods"
                            :key="method.value"
                            no-caps
                            :unelevated="line.method === method.value"
                            :outline="line.method !== method.value"
                            :color="line.method === method.value ? 'primary' : 'grey-7'"
                            :icon="METHOD_ICONS[method.value] ?? 'payments'"
                            :label="method.label"
                            :aria-pressed="line.method === method.value"
                            class="payment__method"
                            @click="chooseMethod(line, method.value)"
                        />
                    </div>
                    <div class="payment__amount">
                        <MoneyInput
                            v-model="line.amount"
                            allow-decimal
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
                        v-if="line.method !== 'cash'"
                        v-model="line.reference"
                        outlined
                        dense
                        maxlength="255"
                        placeholder="No. referensi (opsional)"
                    />
                </div>

                <div v-if="codLine" class="payment__line payment__line--cod">
                    <div class="payment__line-head">
                        <b class="payment__line-title"><q-icon name="local_shipping" /> Bayar saat diantar (COD)</b>
                        <q-btn flat round dense size="sm" icon="close" color="grey-7" class="payment__remove" aria-label="Batalkan bayar saat diantar" @click="toggleCod" />
                    </div>
                    <div class="payment__cod-amount">
                        <b>{{ formatRupiah(codLine.amount) }}</b>
                        <small>Sisa tagihan, dibayar pelanggan saat barang tiba. Cara bayarnya dipilih saat uang diterima, setelah surat jalan selesai.</small>
                    </div>
                </div>

                <div class="payment__split">
                    <q-btn flat dense no-caps size="sm" color="grey-8" icon="add" label="Metode bayar lain" @click="addLine">
                        <q-tooltip>Bayar campur, mis. sebagian tunai, sisanya transfer</q-tooltip>
                    </q-btn>
                    <q-btn v-if="isCodOffered && !codLine" flat dense no-caps size="sm" color="grey-8" icon="local_shipping" label="Bayar saat diantar (COD)" :disable="isCodChoiceDisabled" @click="toggleCod">
                        <q-tooltip>Sisa tagihan dibayar saat barang tiba. Surat jalan dibuat bersama nota ini.</q-tooltip>
                    </q-btn>
                </div>
                </template>

                <q-input v-if="!collectingOrder" v-model="customerName" outlined dense maxlength="255" :label="isOrderMade ? 'Nama pelanggan (wajib untuk pesanan)' : isDelivery ? 'Nama pelanggan (wajib untuk pengantaran)' : 'Nama pelanggan (opsional)'" class="payment__customer" />

                <div v-if="isDelivery && isOrderMade" class="payment__delivery">
                    <q-checkbox v-model="isDeliveryHeld" dense class="payment__held">
                        <span class="payment__held-text">
                            <b>Antar bersama pesanan</b>
                            <small>Barang nota ini disimpan di toko. Surat jalan dan pembayaran COD diselesaikan sekali saat barang pesanan datang.</small>
                        </span>
                    </q-checkbox>
                </div>

                <div v-if="isDelivery && !isDeliveryHeldNow" class="payment__delivery">
                    <b>Diantar: surat jalan dibuat dan siap dicetak bersama nota ini</b>

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
            <q-card-section v-if="isCodWithDeposit" class="payment__warning">COD tidak bisa digabung dengan uang muka pesanan. Batalkan bayar saat diantar atau kosongkan uang muka.</q-card-section>
            <q-card-section v-else-if="isCodWithoutBalance" class="payment__warning">Pembayaran lain sudah menutup total, jadi tidak ada sisa untuk dibayar saat diantar. Batalkan COD atau kurangi jumlah yang dibayar sekarang.</q-card-section>
            <q-card-section v-else-if="isCod && !isCodTheRest" class="payment__warning">Jumlah COD harus sisa tagihan setelah pembayaran lain, tepat sampai total.</q-card-section>
            <q-card-section v-else-if="isDelivery && !isDeliveryComplete" class="payment__warning">Isi nama pelanggan dan alamat pengantaran.</q-card-section>

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
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 4px 12px;
    margin-top: -12px;
}

.payment__fulfillment {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.payment__fulfillment small {
    color: var(--app-mute);
}

.payment__line--cod {
    border-style: dashed;
}

.payment__cod-amount {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.payment__cod-amount b {
    font-size: 20px;
}

.payment__cod-amount small {
    color: var(--app-mute);
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
