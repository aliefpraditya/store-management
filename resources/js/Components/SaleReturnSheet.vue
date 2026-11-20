<script setup>
import { formatRupiah } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';
import ThermalPageSize from './ThermalPageSize.vue';

defineProps({
    /** @type {{number: string, returned_at: string, status: string, status_label: string, cashier_name: string, refund_method_label: string, credit_amount: string, refund_amount: string, cod_offset_amount: string, exchange: {items: object[], total: string, payments: object[]}|null, reason: string|null, sale: {number: string, customer_name: string|null}, store: {name: string, address: string|null, phone: string|null}, paper_width: number, items: object[]}} */
    saleReturn: { type: Object, required: true },
});

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <div class="return-print">
        <ThermalPageSize :width="saleReturn.paper_width" />
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
            <span v-if="saleReturn.refund_account">Rekening: {{ saleReturn.refund_account }}</span>
        </div>

        <ul class="return-print__items">
            <li v-for="item in saleReturn.items" :key="item.id">
                <span>{{ item.name }}</span>
                <span class="return-print__line"><span>{{ formatQuantity(item.quantity) }} {{ item.unit }} × {{ formatRupiah(item.unit_price) }}</span><b>{{ formatRupiah(item.refund_subtotal) }}</b></span>
            </li>
        </ul>

        <template v-if="saleReturn.exchange">
            <p class="return-print__section">Ditukar dengan</p>
            <ul class="return-print__items">
                <li v-for="(item, index) in saleReturn.exchange.items" :key="index">
                    <span>{{ item.name }}</span>
                    <span class="return-print__line"><span>{{ formatQuantity(item.quantity) }} {{ item.unit }} × {{ formatRupiah(item.unit_price) }}</span><b>{{ formatRupiah(item.subtotal) }}</b></span>
                </li>
            </ul>

            <dl class="return-print__totals">
                <div><dt>Nilai retur</dt><dd>{{ formatRupiah(saleReturn.credit_amount) }}</dd></div>
                <div><dt>Nilai pengganti</dt><dd>{{ formatRupiah(saleReturn.exchange.total) }}</dd></div>
                <div v-for="payment in saleReturn.exchange.payments" :key="payment.method"><dt>Tambah ({{ payment.method }})</dt><dd>{{ formatRupiah(payment.amount) }}</dd></div>
                <div v-if="Number(saleReturn.refund_amount) > 0" class="return-print__total"><dt>Dikembalikan ({{ saleReturn.refund_method_label }})</dt><dd>{{ formatRupiah(saleReturn.refund_amount) }}</dd></div>
            </dl>
        </template>

        <dl v-else class="return-print__totals">
            <div v-if="Number(saleReturn.cod_offset_amount) > 0"><dt>Dipotong dari tagihan COD</dt><dd>{{ formatRupiah(saleReturn.cod_offset_amount) }}</dd></div>
            <template v-if="Number(saleReturn.refund_amount) > 0 || Number(saleReturn.cod_offset_amount) <= 0">
                <div class="return-print__total"><dt>Dikembalikan</dt><dd>{{ formatRupiah(saleReturn.refund_amount) }}</dd></div>
                <div><dt>Lewat</dt><dd>{{ saleReturn.refund_method_label }}</dd></div>
            </template>
        </dl>

        <div v-if="Number(saleReturn.refund_amount) > 0 || Number(saleReturn.cod_offset_amount) <= 0" class="return-print__sign"><span>Penerima uang</span></div>

        <footer>Terima kasih</footer>
    </div>
</template>

<style>
.return-print {
    box-sizing: border-box;
    padding: 4mm;
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

.return-print__section {
    margin: 6px 0 2px;
    font-weight: 700;
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
