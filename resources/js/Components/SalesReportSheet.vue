<script setup>
import { formatDeduction, formatRupiah } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';

defineProps({
    /** @type {{from: string, to: string, store: {name: string, address: string|null, phone: string|null}, totals: Record<string, string|number>, days: object[], methods: object[], cashiers: object[], products: object[]}} */
    report: { type: Object, required: true },
});

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' });
const dayFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);
</script>

<template>
    <div class="sales-print">
        <header>
            <div>
                <b>{{ report.store.name }}</b>
                <span v-if="report.store.address">{{ report.store.address }}</span>
                <span v-if="report.store.phone">{{ report.store.phone }}</span>
            </div>
            <div class="sales-print__title">
                <h1>LAPORAN PENJUALAN</h1>
                <b>{{ dateFormat.format(asLocalDate(report.from)) }} sampai {{ dateFormat.format(asLocalDate(report.to)) }}</b>
            </div>
        </header>

        <h2>Ringkasan</h2>
        <table class="sales-print__rows">
            <tbody>
                <tr><td>Penjualan</td><td>{{ formatRupiah(report.totals.gross_total) }}</td></tr>
                <tr><td>Retur</td><td>{{ formatDeduction(report.totals.returns_total) }}</td></tr>
                <tr class="sales-print__total"><td>Penjualan bersih</td><td>{{ formatRupiah(report.totals.net_total) }}</td></tr>
                <tr><td>Jumlah nota (tanpa nota void)</td><td>{{ report.totals.receipt_count }}</td></tr>
                <tr><td>Laba kotor (estimasi, modal = harga beli terakhir)</td><td>{{ formatRupiah(report.totals.gross_profit) }}</td></tr>
            </tbody>
        </table>

        <h2>Per metode bayar</h2>
        <table class="sales-print__rows">
            <tbody>
                <tr v-for="method in report.methods" :key="method.method"><td>{{ method.label }}<template v-if="method.method === 'cod'"> (ditagih saat diantar)</template></td><td>{{ formatRupiah(method.amount) }}</td></tr>
            </tbody>
        </table>

        <h2>Per kasir</h2>
        <table class="sales-print__rows">
            <tbody>
                <tr v-for="cashier in report.cashiers" :key="cashier.cashier_name"><td>{{ cashier.cashier_name }} · {{ cashier.receipt_count }} nota</td><td>{{ formatRupiah(cashier.total) }}</td></tr>
                <tr v-if="report.cashiers.length === 0"><td colspan="2">Belum ada penjualan pada periode ini.</td></tr>
            </tbody>
        </table>

        <h2>Per hari</h2>
        <table class="sales-print__grid">
            <thead><tr><th>Tanggal</th><th>Nota</th><th>Penjualan</th><th>Retur</th><th>Bersih</th></tr></thead>
            <tbody>
                <tr v-for="day in report.days" :key="day.date">
                    <td>{{ dayFormat.format(asLocalDate(day.date)) }}</td>
                    <td>{{ day.receipt_count }}</td>
                    <td>{{ formatRupiah(day.gross_total) }}</td>
                    <td>{{ Number(day.returns_total) > 0 ? formatDeduction(day.returns_total) : '-' }}</td>
                    <td>{{ formatRupiah(day.net_total) }}</td>
                </tr>
            </tbody>
        </table>

        <h2>Laba kotor per barang (30 teratas, setelah retur)</h2>
        <table class="sales-print__grid">
            <thead><tr><th>Barang</th><th>Terjual</th><th>Penjualan</th><th>Modal</th><th>Laba</th><th>Margin</th></tr></thead>
            <tbody>
                <tr v-for="product in report.products" :key="product.product_id">
                    <td>{{ product.name }}</td>
                    <td>{{ formatQuantity(product.quantity) }} {{ product.unit }}</td>
                    <td>{{ formatRupiah(product.revenue) }}</td>
                    <td>{{ formatRupiah(product.cost) }}</td>
                    <td>{{ formatRupiah(product.profit) }}</td>
                    <td>{{ product.margin_percent }}%</td>
                </tr>
                <tr v-if="report.products.length === 0"><td colspan="6">Belum ada penjualan pada periode ini.</td></tr>
            </tbody>
        </table>
    </div>
</template>

<style>
@page {
    size: A4 portrait;
    margin: 0;
}

.sales-print {
    box-sizing: border-box;
    padding: 12mm;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
}

.sales-print header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #000;
}

.sales-print header b,
.sales-print header span {
    display: block;
}

.sales-print__title {
    text-align: right;
}

.sales-print__title h1 {
    margin: 0;
    font-size: 18px;
    letter-spacing: 0.1em;
}

.sales-print h2 {
    margin: 14px 0 4px;
    font-size: 13px;
    break-after: avoid;
}

.sales-print__rows,
.sales-print__grid {
    width: 100%;
    border-collapse: collapse;
}

.sales-print__rows td {
    padding: 3px 6px;
    border-bottom: 1px solid #bbb;
}

.sales-print__rows td:last-child {
    text-align: right;
}

.sales-print .sales-print__total td {
    border-bottom: 2px solid #000;
    font-weight: 700;
}

.sales-print__grid th,
.sales-print__grid td {
    padding: 3px 6px;
    border: 1px solid #000;
    text-align: right;
}

.sales-print__grid th:first-child,
.sales-print__grid td:first-child {
    text-align: left;
}

.sales-print__grid tr {
    break-inside: avoid;
}
</style>