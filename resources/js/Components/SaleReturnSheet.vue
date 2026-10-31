<script setup>
import { formatRupiah } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';

defineProps({
    /** @type {{number: string, returned_at: string, status: string, status_label: string, cashier_name: string, refund_method_label: string, refund_amount: string, reason: string|null, sale: {number: string, customer_name: string|null}, store: {name: string, address: string|null, phone: string|null}, items: object[]}} */
    saleReturn: { type: Object, required: true },
});

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <div class="return-print">
        <header>
            <b>{{ saleReturn.store.name }}</b>
            <span v-if="saleReturn.store.address">{{ saleReturn.store.address }}</span>
            <span v-if="saleReturn.store.phone">{{ saleReturn.store.phone }}</span>
        </header>

        <p class="return-print__mark">BUKTI RETUR</p>
        <p v-if="saleReturn.status !== 'completed'" class="return-print__status">{{ saleReturn.status_label.toUpperCase() }}</p>

        <div class="return-print__meta">
            <span>{{ saleReturn.number }}</span>
            <span>{{ dateTimeFormat.format(new Date(saleReturn.returned_at)) }}</span>
            <span>Nota: {{ saleReturn.sale.number }}</span>
            <span>Kasir: {{ saleReturn.cashier_name }}</span>
            <span v-if="saleReturn.sale.customer_name">Pelanggan: {{ saleReturn.sale.customer_name }}</span>
            <span v-if="saleReturn.reason">Alasan: {{ saleReturn.reason }}</span>
        </div>

        <ul class="return-print__items">
            <li v-for="item in saleReturn.items" :key="item.id">
                <span>{{ item.name }}</span>
                <span class="return-print__line"><span>{{ formatQuantity(item.quantity) }} {{ item.unit }} × {{ formatRupiah(item.unit_price) }}</span><b>{{ formatRupiah(item.refund_subtotal) }}</b></span>
            </li>
        </ul>

        <dl class="return-print__totals">
            <div class="return-print__total"><dt>Dikembalikan</dt><dd>{{ formatRupiah(saleReturn.refund_amount) }}</dd></div>
            <div><dt>Lewat</dt><dd>{{ saleReturn.refund_method_label }}</dd></div>
        </dl>

        <div class="return-print__sign"><span>Penerima uang</span></div>

        <footer>Terima kasih</footer>
    </div>
</template>

<style>
@page {
    size: 80mm auto;
    margin: 4mm;
}

.return-print {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.35;
    color: #000;
}

.return-print header,
.return-print footer {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.return-print footer {
    margin-top: 8px;
}

.return-print__mark {
    margin: 6px 0 2px;
    font-size: 14px;
    font-weight: 700;
    text-align: center;
    letter-spacing: 0.15em;
}

.return-print__status {
    margin: 0 0 4px;
    font-weight: 700;
    text-align: center;
    letter-spacing: 0.15em;
}

.return-print__meta {
    display: flex;
    flex-direction: column;
    margin: 6px 0;
    padding: 4px 0;
    border-top: 1px dashed #000;
    border-bottom: 1px dashed #000;
}

.return-print__items {
    margin: 0;
    padding: 0;
    list-style: none;
}

.return-print__items li {
    display: flex;
    flex-direction: column;
    margin-bottom: 3px;
}

.return-print__line {
    display: flex;
    justify-content: space-between;
}

.return-print__totals {
    margin: 6px 0 0;
    padding-top: 4px;
    border-top: 1px dashed #000;
}

.return-print__totals div {
    display: flex;
    justify-content: space-between;
}

.return-print__totals dd {
    margin: 0;
}

.return-print__total {
    font-weight: 700;
}

.return-print__sign {
    margin-top: 18px;
    text-align: center;
}

.return-print__sign span {
    display: block;
    padding-top: 36px;
    border-bottom: 1px solid #000;
    padding-bottom: 2px;
}
</style>
