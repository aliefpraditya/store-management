<script setup>
import { formatFigure as figure } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';
import Code39Barcode from './Code39Barcode.vue';
import ThermalPageSize from './ThermalPageSize.vue';

defineProps({
    /** @type {{number: string, ordered_at: string, expected_date: string|null, customer_name: string, customer_phone: string, source_note: string|null, created_by_name: string, status: string, is_copy: boolean, store: {name: string, address: string|null, phone: string|null}, footer: string, paper_width: number, items: object[], total: string, deposit: string, remaining: string, origin_sale_number: string|null}} */
    order: { type: Object, required: true },
});

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
const timeFormat = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

/** Longer than any paper is wide; the line is cut at the edge and ends with a plus. */
const rule = '- '.repeat(60);
</script>

<template>
    <div class="order-print">
        <ThermalPageSize :width="order.paper_width" />
        <header>
            <b>{{ order.store.name }}</b>
            <span v-if="order.store.address">{{ order.store.address }}</span>
            <span v-if="order.store.phone">No Tlp. {{ order.store.phone }}</span>
        </header>

        <p class="order-print__title">NOTA PESANAN</p>
        <div class="order-print__barcode">
            <Code39Barcode :value="order.number" />
            <small>{{ order.number }}</small>
        </div>
        <p v-if="order.status === 'cancelled'" class="order-print__mark">DIBATALKAN</p>
        <p v-else-if="order.is_copy" class="order-print__mark">SALINAN</p>

        <div class="order-print__meta">
            <span>No : {{ order.number }}</span>
            <span>Tanggal : {{ dateFormat.format(new Date(order.ordered_at)) }} {{ timeFormat.format(new Date(order.ordered_at)) }}</span>
            <span>Kasir : {{ order.created_by_name }}</span>
            <span>Pelanggan : {{ order.customer_name }}</span>
            <span>Telepon : {{ order.customer_phone }}</span>
            <span v-if="order.origin_sale_number">Nota belanja : {{ order.origin_sale_number }}</span>
        </div>

        <ul class="order-print__items">
            <li v-for="(item, index) in order.items" :key="index">
                <span>{{ item.name }}</span>
                <span class="order-print__line"><span>{{ formatQuantity(item.quantity) }} {{ item.unit }} x Rp. {{ figure(item.unit_price) }}</span><span>{{ figure(item.subtotal) }}</span></span>
            </li>
        </ul>

        <div class="order-print__rule"><span>{{ rule }}</span><span>+</span></div>

        <div class="order-print__totals">
            <span>Total : {{ figure(order.total) }}</span>
            <span>Uang muka : {{ figure(order.deposit) }}</span>
            <b>Sisa dibayar saat diambil : {{ figure(order.remaining) }}</b>
        </div>

        <footer>
            <p>Bawa nota ini saat mengambil barang. Harga sesuai nota ini.</p>
            <p class="order-print__footer">{{ order.footer }}</p>
            <p>{{ order.number }}</p>
        </footer>
    </div>
</template>

<style>
/*
 * The paper margin is zero so the browser has no room for its own header and footer on the roll; the space around the
 * text is padding instead (the same as the receipt).
 */
@page {
    size: 80mm auto;
    margin: 0;
}

.order-print {
    box-sizing: border-box;
    padding: 4mm;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.35;
    color: #000;
}

.order-print header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.order-print header b {
    font-size: 16px;
}

.order-print footer {
    margin-top: 8px;
}

.order-print footer p {
    margin: 0 0 6px;
}

.order-print .order-print__footer {
    white-space: pre-line;
}

.order-print__title,
.order-print__mark {
    margin: 6px 0;
    font-size: 16px;
    font-weight: 700;
    text-align: center;
    letter-spacing: 0.2em;
}

.order-print__barcode {
    margin: 4px 0 8px;
    text-align: center;
}

.order-print__barcode small {
    font-size: 11px;
}

.order-print__meta,
.order-print__totals {
    display: flex;
    flex-direction: column;
    margin: 10px 0 12px;
}

.order-print__totals {
    margin: 0 0 8px;
    text-align: right;
}

.order-print__items {
    margin: 0;
    padding: 0;
    list-style: none;
}

.order-print__items li {
    display: flex;
    flex-direction: column;
    margin-bottom: 4px;
}

.order-print__line {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.order-print__rule {
    display: flex;
    margin: 4px 0;
    white-space: nowrap;
}

.order-print__rule span:first-child {
    flex: 1;
    overflow: hidden;
}
</style>
