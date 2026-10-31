<script setup>
import { formatRupiah } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';

defineProps({
    /** @type {{number: string, sold_at: string, cashier_name: string, customer_name: string|null, status: string, is_copy: boolean, store: {name: string, address: string|null, phone: string|null}, items: object[], payments: object[], total: string, change_amount: string}} */
    receipt: { type: Object, required: true },
});

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <div class="receipt-print">
        <header>
            <b>{{ receipt.store.name }}</b>
            <span v-if="receipt.store.address">{{ receipt.store.address }}</span>
            <span v-if="receipt.store.phone">{{ receipt.store.phone }}</span>
        </header>

        <p v-if="receipt.status === 'voided'" class="receipt-print__mark">VOID</p>
        <p v-else-if="receipt.is_copy" class="receipt-print__mark">SALINAN</p>

        <div class="receipt-print__meta">
            <span>{{ receipt.number }}</span>
            <span>{{ dateTimeFormat.format(new Date(receipt.sold_at)) }}</span>
            <span>Kasir: {{ receipt.cashier_name }}</span>
            <span v-if="receipt.customer_name">Pelanggan: {{ receipt.customer_name }}</span>
        </div>

        <ul class="receipt-print__items">
            <li v-for="(item, index) in receipt.items" :key="index">
                <span>{{ item.name }}</span>
                <span class="receipt-print__line"><span>{{ formatQuantity(item.quantity) }} {{ item.unit }} × {{ formatRupiah(item.unit_price) }}</span><b>{{ formatRupiah(item.subtotal) }}</b></span>
            </li>
        </ul>

        <dl class="receipt-print__totals">
            <div class="receipt-print__total"><dt>Total</dt><dd>{{ formatRupiah(receipt.total) }}</dd></div>
            <div v-for="(payment, index) in receipt.payments" :key="index"><dt>{{ payment.method }}</dt><dd>{{ formatRupiah(payment.amount) }}</dd></div>
            <div><dt>Kembalian</dt><dd>{{ formatRupiah(receipt.change_amount) }}</dd></div>
        </dl>

        <footer>Terima kasih</footer>
    </div>
</template>

<style>
@page {
    size: 80mm auto;
    margin: 4mm;
}

.receipt-print {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.35;
    color: #000;
}

.receipt-print header,
.receipt-print footer {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.receipt-print footer {
    margin-top: 8px;
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
    margin: 6px 0;
    padding: 4px 0;
    border-top: 1px dashed #000;
    border-bottom: 1px dashed #000;
}

.receipt-print__items {
    margin: 0;
    padding: 0;
    list-style: none;
}

.receipt-print__items li {
    display: flex;
    flex-direction: column;
    margin-bottom: 3px;
}

.receipt-print__line {
    display: flex;
    justify-content: space-between;
}

.receipt-print__totals {
    margin: 6px 0 0;
    padding-top: 4px;
    border-top: 1px dashed #000;
}

.receipt-print__totals div {
    display: flex;
    justify-content: space-between;
}

.receipt-print__totals dd {
    margin: 0;
}

.receipt-print__total {
    font-weight: 700;
}
</style>
