<script setup>
import { formatQuantity } from '../Composables/useQuantityFormat';

defineProps({
    /** @type {{number: string, sold_at: string, cashier_name: string, customer_name: string|null, status: string, is_copy: boolean, store: {name: string, address: string|null, phone: string|null}, items: object[], payments: object[], total: string, change_amount: string}} */
    receipt: { type: Object, required: true },
});

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
const timeFormat = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });
const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/** Longer than any paper is wide; the line is cut at the edge and ends with a plus. */
const rule = '- '.repeat(60);

/** The receipt shows plain figures ("1.280.000"); only a unit price carries "Rp.". */
const figure = (amount) => numberFormat.format(Number(amount));

/** What is left to pay on delivery is the receipt's "kekurangan", so a COD line is named that way. */
const paymentLabel = (payment) => (payment.method === 'COD' ? 'Kekurangan (COD)' : payment.method);
</script>

<template>
    <div class="receipt-print">
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
            <span v-for="(payment, index) in receipt.payments" :key="index">{{ paymentLabel(payment) }} : {{ figure(payment.amount) }}</span>
            <span>Kembalian : {{ figure(receipt.change_amount) }}</span>
        </div>

        <footer>
            <p>Barang yang sudah dibeli tidak dapat ditukar atau dikembalikan kecuali ada perjanjian sebelumnya.</p>
            <p>Terimakasih Telah Berbelanja<br />{{ receipt.number }}</p>
        </footer>
    </div>
</template>

<style>
/*
 * The paper margin is zero so the browser has no room for its own header and footer (date, address, page number) on
 * the roll; the space around the text is padding instead.
 */
@page {
    size: 80mm auto;
    margin: 0;
}

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

.receipt-print__totals {
    display: flex;
    flex-direction: column;
    margin: 0 0 8px;
    text-align: right;
}
</style>