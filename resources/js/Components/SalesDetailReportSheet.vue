<script setup>
import { formatDeduction, formatRupiah } from '../Composables/useMoneyFormat';
import { formatQuantity } from '../Composables/useQuantityFormat';
import SalesReceiptDay from './SalesReceiptDay.vue';
import SalesReturnList from './SalesReturnList.vue';

const props = defineProps({
    /** @type {{from: string, to: string, filter: string|null, store: {name: string, address: string|null, phone: string|null}, totals: Record<string, string|number>, methods: object[], days: object[], goods: object[], voids: object[], returns: object[]}} */
    report: { type: Object, required: true },
    /** When the sheet was made, `Y-m-d H:i`, for the line under the signatures. */
    printedAt: { type: String, required: true },
});

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeStyle: 'short' });
const printedAtText = dateTimeFormat.format(new Date(props.printedAt.replace(' ', 'T')));
const dayFormat = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const shortDayFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);
</script>

<template>
    <div class="detail-print">
        <header>
            <div>
                <b>{{ report.store.name }}</b>
                <span v-if="report.store.address">{{ report.store.address }}</span>
                <span v-if="report.store.phone">{{ report.store.phone }}</span>
            </div>
            <div class="detail-print__title">
                <h1>LAPORAN PENJUALAN RINCI</h1>
                <b>{{ dateFormat.format(asLocalDate(report.from)) }} sampai {{ dateFormat.format(asLocalDate(report.to)) }}</b>
            </div>
        </header>

        <p v-if="report.filter" class="detail-print__filter">Hanya nota yang dibayar dengan {{ report.filter }} (nota yang dibayar dengan beberapa metode tampil utuh). Retur dan void tidak dimuat, dan total di bawah adalah total nota yang tercantum.</p>

        <h2>Total periode</h2>
        <table class="detail-print__rows">
            <tbody>
                <tr><td>Jumlah nota (tanpa nota void)</td><td>{{ report.totals.receipt_count }}</td></tr>
                <tr><td>Penjualan</td><td>{{ formatRupiah(report.totals.gross_total) }}</td></tr>
                <template v-if="!report.filter">
                    <tr><td>Retur</td><td>{{ formatDeduction(report.totals.returns_total) }}</td></tr>
                    <tr class="detail-print__total"><td>Penjualan bersih</td><td>{{ formatRupiah(report.totals.net_total) }}</td></tr>
                </template>
            </tbody>
        </table>

        <h2>Per metode bayar (penjualan, sebelum retur)</h2>
        <table class="detail-print__rows">
            <tbody>
                <tr v-for="method in report.methods" :key="method.method"><td>{{ method.label }}</td><td>{{ formatRupiah(method.amount) }}</td></tr>
                <tr class="detail-print__total"><td>Penjualan</td><td>{{ formatRupiah(report.totals.gross_total) }}</td></tr>
            </tbody>
        </table>

        <h2 class="detail-print__section">Rincian nota per hari</h2>
        <p v-if="report.days.length === 0" class="detail-print__empty">Belum ada penjualan pada periode ini.</p>

        <section v-for="day in report.days" :key="day.date" class="detail-print__day">
            <h3>{{ dayFormat.format(asLocalDate(day.date)) }}</h3>

            <SalesReceiptDay
                :receipts="day.receipts"
                :methods="day.methods"
                :summary-title="`${shortDayFormat.format(asLocalDate(day.date))}: ${day.receipt_count} nota, penjualan`"
                :gross-total="day.gross_total"
                :returns-total="day.returns_total"
                :net-total="day.net_total"
            />
        </section>

        <template v-if="report.goods.length > 0">
            <h2 class="detail-print__section">Rekap barang terjual ({{ report.goods.length }} barang, sebelum retur)</h2>
            <table class="detail-print__grid">
                <thead><tr><th>Barang</th><th>Jumlah</th><th>Satuan</th><th>Subtotal</th></tr></thead>
                <tbody>
                    <tr v-for="(row, index) in report.goods" :key="index">
                        <td>{{ row.name }}</td>
                        <td>{{ formatQuantity(row.quantity) }}</td>
                        <td>{{ row.unit }}</td>
                        <td>{{ formatRupiah(row.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>
        </template>

        <template v-if="report.voids.length > 0">
            <h2 class="detail-print__section">Nota void pada periode ini (tidak termasuk di atas)</h2>
            <table class="detail-print__grid">
                <thead><tr><th>Nota</th><th>Tanggal nota</th><th>Tanggal void</th><th>Alasan</th><th>Total</th></tr></thead>
                <tbody>
                    <tr v-for="voided in report.voids" :key="voided.number">
                        <td>{{ voided.number }}</td>
                        <td>{{ shortDayFormat.format(asLocalDate(voided.business_date)) }}</td>
                        <td>{{ shortDayFormat.format(asLocalDate(voided.void_date)) }}</td>
                        <td>{{ voided.reason ?? '-' }}</td>
                        <td>{{ formatRupiah(voided.total) }}</td>
                    </tr>
                </tbody>
            </table>
        </template>

        <template v-if="report.returns.length > 0">
            <h2 class="detail-print__section">Retur disetujui pada periode ini</h2>
            <SalesReturnList :returns="report.returns" />
        </template>

        <footer class="detail-print__signatures">
            <div><span>Dibuat oleh</span><i></i></div>
            <div><span>Diperiksa oleh</span><i></i></div>
            <p>Dicetak pada {{ printedAtText }}</p>
        </footer>
    </div>
</template>

<style>
/* The margin lives on the page (not as padding) so the page number has a margin to stand in; a browser that does not know margin boxes just prints without it. */
@page {
    size: A4 portrait;
    margin: 12mm 12mm 16mm;

    @bottom-right {
        content: 'Hal. ' counter(page) ' dari ' counter(pages);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
    }
}

.detail-print {
    box-sizing: border-box;
    padding: 12mm;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    line-height: 1.35;
    color: #000;
}

.detail-print header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #000;
}

.detail-print header b,
.detail-print header span {
    display: block;
}

.detail-print__title {
    text-align: right;
}

.detail-print__title h1 {
    margin: 0;
    font-size: 17px;
    letter-spacing: 0.08em;
}

.detail-print h2 {
    margin: 14px 0 4px;
    font-size: 13px;
    break-after: avoid;
}

.detail-print h2.detail-print__section {
    margin-top: 20px;
    padding-bottom: 2px;
    border-bottom: 2px solid #000;
}

.detail-print h3 {
    margin: 14px 0 6px;
    padding: 3px 6px;
    background: #e5e5e5;
    font-size: 12px;
    break-after: avoid;
}

.detail-print__empty {
    margin: 4px 0;
}

.detail-print .detail-print__filter {
    margin: 0 0 8px;
    padding: 4px 8px;
    border: 1px solid #000;
    font-weight: 700;
}

.detail-print__signatures {
    display: flex;
    flex-wrap: wrap;
    gap: 0 40px;
    margin-top: 28px;
    break-inside: avoid;
}

.detail-print__signatures div {
    flex: 1;
    text-align: center;
}

.detail-print__signatures span,
.detail-print__signatures i {
    display: block;
}

.detail-print__signatures i {
    height: 56px;
    border-bottom: 1px solid #000;
}

.detail-print__signatures p {
    flex-basis: 100%;
    margin: 10px 0 0;
    font-size: 10px;
}

@media print {
    .detail-print {
        padding: 0;
    }
}

.detail-print__rows,
.detail-print__grid {
    width: 100%;
    border-collapse: collapse;
}

.detail-print__rows td {
    padding: 2px 6px;
    border-bottom: 1px solid #bbb;
}

.detail-print__rows td:last-child {
    text-align: right;
}

.detail-print .detail-print__total td {
    border-bottom: 2px solid #000;
    font-weight: 700;
}

.detail-print__grid th,
.detail-print__grid td {
    padding: 3px 6px;
    border: 1px solid #000;
    text-align: left;
}

.detail-print__grid th:last-child,
.detail-print__grid td:last-child {
    text-align: right;
}

.detail-print__grid tr {
    break-inside: avoid;
}
</style>
