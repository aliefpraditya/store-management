<script setup>
import { computed } from 'vue';
import { formatFigure as figure } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';
import { thermalLayoutOf } from '../Composables/thermalLayout';
import Code39Barcode from './Code39Barcode.vue';
import ReceiptRow from './ReceiptRow.vue';
import ThermalPageSize from './ThermalPageSize.vue';

const props = defineProps({
    /** `order` is the customer order made in the same checkout, printed on the same paper (null when there is none). The server has named the payments as they are printed. The receipt shows plain figures ("1.280.000"); only a unit price carries "Rp.". @type {{order: object|null, number: string, sold_at: string, cashier_name: string, customer_name: string|null, status: string, is_copy: boolean, store: {name: string, address: string|null, phone: string|null}, footer: string, paper_width: number, items: object[], payments: object[], total: string, change_amount: string}} */
    receipt: { type: Object, required: true },
});

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
const timeFormat = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

/** Width of one character of the 12px monospace font, in millimetres (7.2 px at 96 px per inch). */
const CHARACTER_WIDTH_MM = 7.2 / (96 / 25.4);

const layout = computed(() => thermalLayoutOf(props.receipt.paper_width));

/** The space around the text is written into the element, not a CSS variable, which the renderer of QZ Tray does not know. */
const sheetStyle = computed(() => `padding: 4mm ${layout.value.right}mm 4mm ${layout.value.left}mm;`);

/** As many dashes as fit the text, one character short, ending in a plus: a line longer than the text would be cut at the paper's edge. */
const rule = computed(() => {
    const textWidthMm = props.receipt.paper_width - layout.value.left - layout.value.right;
    const characters = Math.floor(textWidthMm / CHARACTER_WIDTH_MM) - 1;

    return `${'- '.repeat(characters).slice(0, characters - 1)}+`;
});
</script>

<template>
    <div class="receipt-print" :style="sheetStyle">
        <ThermalPageSize :width="receipt.paper_width" />
        <div class="receipt-print__letterhead">
            <b>{{ receipt.store.name }}</b>
            <span v-if="receipt.store.address">{{ receipt.store.address }}</span>
            <span v-if="receipt.store.phone">No Tlp. {{ receipt.store.phone }}</span>
        </div>

        <p v-if="receipt.status === 'voided'" class="receipt-print__mark">VOID</p>
        <p v-else-if="receipt.is_copy" class="receipt-print__mark">SALINAN</p>

        <div class="receipt-print__meta">
            <ReceiptRow>No : {{ receipt.number }}</ReceiptRow>
            <ReceiptRow>Tanggal : {{ dateFormat.format(new Date(receipt.sold_at)) }} {{ timeFormat.format(new Date(receipt.sold_at)) }}</ReceiptRow>
            <ReceiptRow>Kasir : {{ receipt.cashier_name }}</ReceiptRow>
            <ReceiptRow v-if="receipt.customer_name">Pelanggan : {{ receipt.customer_name }}</ReceiptRow>
            <ReceiptRow v-if="receipt.order?.customer_phone">Telepon : {{ receipt.order.customer_phone }}</ReceiptRow>
        </div>

        <div v-for="(item, index) in receipt.items" :key="`item-${index}`" class="receipt-print__item">
            <ReceiptRow>{{ item.name }}</ReceiptRow>
            <div class="receipt-print__row"><span>{{ formatQuantity(item.quantity) }} {{ item.unit }} x Rp. {{ figure(item.unit_price) }}</span><span>{{ figure(item.subtotal) }}</span></div>
        </div>

        <div class="receipt-print__rule">{{ rule }}</div>

        <div class="receipt-print__totals">
            <ReceiptRow is-right-aligned>Total : {{ figure(receipt.total) }}</ReceiptRow>
            <template v-if="!receipt.order">
                <ReceiptRow v-for="(payment, index) in receipt.payments" :key="`payment-${index}`" is-right-aligned>{{ payment.method }} : {{ figure(payment.amount) }}</ReceiptRow>
                <ReceiptRow is-right-aligned>Kembalian : {{ figure(receipt.change_amount) }}</ReceiptRow>
            </template>
        </div>

        <div v-if="receipt.order" class="receipt-print__order">
            <div class="receipt-print__rule">{{ rule }}</div>
            <ReceiptRow><b>PESANAN {{ receipt.order.number }}</b></ReceiptRow>
            <div class="receipt-print__barcode"><ReceiptRow><Code39Barcode :value="receipt.order.number" :height="30" /></ReceiptRow></div>
            <ReceiptRow>{{ receipt.order.status === 'completed' ? 'Sudah diambil' : 'Barang menyusul' }}</ReceiptRow>
            <div v-for="(item, index) in receipt.order.items" :key="`order-item-${index}`" class="receipt-print__item">
                <ReceiptRow>{{ item.name }}</ReceiptRow>
                <div class="receipt-print__row"><span>{{ formatQuantity(item.quantity) }} {{ item.unit }} x Rp. {{ figure(item.unit_price) }}</span><span>{{ figure(item.subtotal) }}</span></div>
            </div>
            <div class="receipt-print__totals">
                <ReceiptRow is-right-aligned>Total pesanan : {{ figure(receipt.order.total) }}</ReceiptRow>
                <ReceiptRow is-right-aligned>Uang muka : {{ figure(receipt.order.deposit) }}</ReceiptRow>
                <ReceiptRow is-right-aligned><b>Sisa dibayar saat diambil : {{ figure(receipt.order.remaining) }}</b></ReceiptRow>
            </div>
            <ReceiptRow>Bawa nota ini saat mengambil barang. Harga sesuai nota ini.</ReceiptRow>
        </div>

        <template v-if="receipt.order">
            <div class="receipt-print__rule">{{ rule }}</div>
            <div class="receipt-print__totals">
                <ReceiptRow is-right-aligned>Uang muka pesanan : {{ figure(receipt.order.deposit) }}</ReceiptRow>
                <ReceiptRow is-right-aligned>Total dibayar : {{ figure(Number(receipt.total) + Number(receipt.order.deposit)) }}</ReceiptRow>
                <ReceiptRow v-for="(payment, index) in receipt.payments" :key="`order-payment-${index}`" is-right-aligned>{{ payment.method }} : {{ figure(payment.amount) }}</ReceiptRow>
                <ReceiptRow is-right-aligned>Kembalian : {{ figure(receipt.change_amount) }}</ReceiptRow>
            </div>
        </template>

        <div class="receipt-print__closing">
            <p class="receipt-print__footer">{{ receipt.footer }}</p>
            <p>{{ receipt.number }}</p>
        </div>
    </div>
</template>

<style>
/*
 * Left and right lines are `ReceiptRow`s and the letterhead and footer are centred blocks (plain `div`s, as the printer test
 * lines that centre exactly are, not `header` and `footer` elements): the renderer of QZ Tray centres
 * every line narrower than the paper, so only a line that fills the paper can sit at an edge (see ReceiptRow). The paper size
 * is written by ThermalPageSize, from the store's setting; the space around the text is padding because that component's
 * paper margin is zero.
 */
.receipt-print {
    box-sizing: border-box;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.35;
    color: #000;
}

.receipt-print__letterhead,
.receipt-print__closing {
    text-align: center;
}

.receipt-print__letterhead span,
.receipt-print__letterhead b {
    display: block;
}

.receipt-print__letterhead b {
    font-size: 16px;
}

.receipt-print__closing {
    margin-top: 8px;
}

.receipt-print__closing p {
    margin: 0 0 6px;
}

.receipt-print .receipt-print__footer {
    white-space: pre-line;
}

.receipt-print__mark {
    margin: 6px 0;
    font-size: 16px;
    font-weight: 700;
    text-align: center;
    letter-spacing: 0.2em;
}

.receipt-print__meta {
    margin: 10px 0 12px;
}

.receipt-print__item {
    margin-bottom: 4px;
}

.receipt-print__rule {
    margin: 4px 0;
    white-space: nowrap;
}

.receipt-print__order {
    margin-bottom: 8px;
}

.receipt-print__barcode {
    margin: 4px 0 6px;
}

.receipt-print__totals {
    margin: 0 0 8px;
}
</style>
