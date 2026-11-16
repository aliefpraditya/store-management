<script setup>
import { formatFigure as figure } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';
import Code39Barcode from './Code39Barcode.vue';
import ThermalPageSize from './ThermalPageSize.vue';

defineProps({
    /** `order` is the customer order made in the same checkout, printed on the same paper (null when there is none). The server has named the payments as they are printed. The receipt shows plain figures ("1.280.000"); only a unit price carries "Rp.". @type {{order: object|null, number: string, sold_at: string, cashier_name: string, customer_name: string|null, status: string, is_copy: boolean, store: {name: string, address: string|null, phone: string|null}, footer: string, paper_width: number, items: object[], payments: object[], total: string, change_amount: string}} */
    receipt: { type: Object, required: true },
});

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
const timeFormat = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

/** Longer than any paper is wide; the line is cut at the edge and ends with a plus. */
const rule = '- '.repeat(60);
</script>

<template>
    <div class="receipt-print">
        <ThermalPageSize :width="receipt.paper_width" />
        <header>
            <b>{{ receipt.store.name }}</b>
            <span v-if="receipt.store.address">{{ receipt.store.address }}</span>
            <span v-if="receipt.store.phone">No Tlp. {{ receipt.store.phone }}</span>
        </header>

        <p v-if="receipt.status === 'voided'" class="receipt-print__mark">VOID</p>
        <p v-else-if="receipt.is_copy" class="receipt-print__mark">SALINAN</p>

        <div class="receipt-print__meta">
            <span>No : {{ receipt.number }}</span>
            <span>Tanggal : {{ dateFormat.format(new Date(receipt.sold_at)) }} {{ timeFormat.format(new Date(receipt.sold_at)) }}</span>
            <span>Kasir : {{ receipt.cashier_name }}</span>
            <span v-if="receipt.customer_name">Pelanggan : {{ receipt.customer_name }}</span>
            <span v-if="receipt.order?.customer_phone">Telepon : {{ receipt.order.customer_phone }}</span>
        </div>

        <ul class="receipt-print__items">
            <li v-for="(item, index) in receipt.items" :key="index">
                <span>{{ item.name }}</span>
                <span class="receipt-print__line"><span>{{ formatQuantity(item.quantity) }} {{ item.unit }} x Rp. {{ figure(item.unit_price) }}</span><span>{{ figure(item.subtotal) }}</span></span>
            </li>
        </ul>

        <div class="receipt-print__rule"><span>{{ rule }}</span><span>+</span></div>

        <div class="receipt-print__totals">
            <span>Total : {{ figure(receipt.total) }}</span>
            <template v-if="!receipt.order">
                <span v-for="(payment, index) in receipt.payments" :key="index">{{ payment.method }} : {{ figure(payment.amount) }}</span>
                <span>Kembalian : {{ figure(receipt.change_amount) }}</span>
            </template>
        </div>

        <section v-if="receipt.order" class="receipt-print__order">
            <div class="receipt-print__rule"><span>{{ rule }}</span><span>+</span></div>
            <b>PESANAN {{ receipt.order.number }}</b>
            <div class="receipt-print__barcode"><Code39Barcode :value="receipt.order.number" :height="30" /></div>
            <span>{{ receipt.order.status === 'completed' ? 'Sudah diambil' : 'Barang menyusul' }}</span>
            <ul class="receipt-print__items">
                <li v-for="(item, index) in receipt.order.items" :key="index">
                    <span>{{ item.name }}</span>
                    <span class="receipt-print__line"><span>{{ formatQuantity(item.quantity) }} {{ item.unit }} x Rp. {{ figure(item.unit_price) }}</span><span>{{ figure(item.subtotal) }}</span></span>
                </li>
            </ul>
            <div class="receipt-print__totals">
                <span>Total pesanan : {{ figure(receipt.order.total) }}</span>
                <span>Uang muka : {{ figure(receipt.order.deposit) }}</span>
                <b>Sisa dibayar saat diambil : {{ figure(receipt.order.remaining) }}</b>
            </div>
            <span>Bawa nota ini saat mengambil barang. Harga sesuai nota ini.</span>
        </section>

        <template v-if="receipt.order">
            <div class="receipt-print__rule"><span>{{ rule }}</span><span>+</span></div>
            <div class="receipt-print__totals">
                <span>Uang muka pesanan : {{ figure(receipt.order.deposit) }}</span>
                <span>Total dibayar : {{ figure(Number(receipt.total) + Number(receipt.order.deposit)) }}</span>
                <span v-for="(payment, index) in receipt.payments" :key="index">{{ payment.method }} : {{ figure(payment.amount) }}</span>
                <span>Kembalian : {{ figure(receipt.change_amount) }}</span>
            </div>
        </template>

        <footer>
            <p class="receipt-print__footer">{{ receipt.footer }}</p>
            <p>{{ receipt.number }}</p>
        </footer>
    </div>
</template>

<style>
/* The paper size is written by ThermalPageSize, from the store's setting; the space around the text is padding because that component's paper margin is zero. */
.receipt-print {
    box-sizing: border-box;
    padding: 4mm;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.35;
    color: #000;
}

.receipt-print header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.receipt-print header b {
    font-size: 16px;
}

.receipt-print footer {
    margin-top: 8px;
    text-align: left;
}

.receipt-print footer p {
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
    display: flex;
    flex-direction: column;
    margin: 10px 0 12px;
}

.receipt-print__items {
    margin: 0;
    padding: 0;
    list-style: none;
}

.receipt-print__items li {
    display: flex;
    flex-direction: column;
    margin-bottom: 4px;
}

.receipt-print__line {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.receipt-print__rule {
    display: flex;
    margin: 4px 0;
    white-space: nowrap;
}

.receipt-print__rule span:first-child {
    flex: 1;
    overflow: hidden;
}

.receipt-print__order {
    display: flex;
    flex-direction: column;
    margin-bottom: 8px;
}

.receipt-print__barcode {
    margin: 4px 0 6px;
}

.receipt-print__totals {
    display: flex;
    flex-direction: column;
    margin: 0 0 8px;
    text-align: right;
}
</style>