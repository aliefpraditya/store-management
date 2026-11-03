<script setup>
import { formatRupiah } from '../Composables/useMoneyFormat';

defineProps({
    /** @type {{business_date: string, closed_by_name: string|null, closed_at: string, notes: string|null, figures: Record<string, string|number>, store: {name: string, address: string|null, phone: string|null}, shifts: {label: string, register_name: string, cashier_name: string, expected_cash: string|null, counted_cash: string|null, cash_difference: string|null, deposit_amount: string|null}[]}} */
    report: { type: Object, required: true },
});

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <div class="daily-print">
        <header>
            <div>
                <b>{{ report.store.name }}</b>
                <span v-if="report.store.address">{{ report.store.address }}</span>
                <span v-if="report.store.phone">{{ report.store.phone }}</span>
            </div>
            <div class="daily-print__title">
                <h1>LAPORAN HARIAN</h1>
                <b>{{ dateFormat.format(new Date(`${report.business_date}T00:00:00`)) }}</b>
            </div>
        </header>

        <h2>Penjualan</h2>
        <table class="daily-print__rows">
            <tbody>
                <tr><td>Jumlah nota</td><td>{{ report.figures.sales_count }}</td></tr>
                <tr><td>Tunai (setelah kembalian)</td><td>{{ formatRupiah(report.figures.sales_cash) }}</td></tr>
                <tr><td>Transfer</td><td>{{ formatRupiah(report.figures.sales_transfer) }}</td></tr>
                <tr><td>QRIS</td><td>{{ formatRupiah(report.figures.sales_qris) }}</td></tr>
                <tr><td>EDC</td><td>{{ formatRupiah(report.figures.sales_edc) }}</td></tr>
                <tr><td>COD (ditagih saat diantar)</td><td>{{ formatRupiah(report.figures.sales_cod) }}</td></tr>
                <tr class="daily-print__total"><td>Total penjualan</td><td>{{ formatRupiah(report.figures.total_sales) }}</td></tr>
            </tbody>
        </table>

        <h2>Retur dan pengeluaran</h2>
        <table class="daily-print__rows">
            <tbody>
                <tr><td>Retur disetujui</td><td>{{ formatRupiah(report.figures.total_returns) }}</td></tr>
                <tr><td>Void ({{ report.figures.voids_count }} nota)</td><td>{{ formatRupiah(report.figures.total_voids) }}</td></tr>
                <tr><td>Pengembalian tunai (retur dan DP void)</td><td>{{ formatRupiah(report.figures.refunds_cash) }}</td></tr>
                <tr><td>Pengeluaran dari laci</td><td>{{ formatRupiah(report.figures.expenses_drawer) }}</td></tr>
                <tr><td>Pengeluaran sumber lain</td><td>{{ formatRupiah(report.figures.expenses_other) }}</td></tr>
            </tbody>
        </table>

        <h2>Kas per shift</h2>
        <table class="daily-print__shifts">
            <thead>
                <tr><th>Shift</th><th>Kasir</th><th>Seharusnya</th><th>Dihitung</th><th>Selisih</th><th>Setoran</th></tr>
            </thead>
            <tbody>
                <tr v-for="shift in report.shifts" :key="shift.label">
                    <td>{{ shift.label }}<small>{{ shift.register_name }}</small></td>
                    <td>{{ shift.cashier_name }}</td>
                    <td>{{ formatRupiah(shift.expected_cash ?? 0) }}</td>
                    <td>{{ formatRupiah(shift.counted_cash ?? 0) }}</td>
                    <td>{{ formatRupiah(shift.cash_difference ?? 0) }}</td>
                    <td>{{ formatRupiah(shift.deposit_amount ?? 0) }}</td>
                </tr>
                <tr v-if="report.shifts.length === 0"><td colspan="6" class="daily-print__empty">Tidak ada shift pada hari ini.</td></tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total ({{ report.figures.shifts_count }} shift)</td>
                    <td>{{ formatRupiah(report.figures.total_expected_cash) }}</td>
                    <td>{{ formatRupiah(report.figures.total_counted_cash) }}</td>
                    <td>{{ formatRupiah(report.figures.total_cash_difference) }}</td>
                    <td>{{ formatRupiah(report.figures.total_deposit) }}</td>
                </tr>
            </tfoot>
        </table>

        <template v-if="report.voids.length > 0">
            <h2>Nota void pada hari ini</h2>
            <table class="daily-print__rows">
                <tbody>
                    <tr v-for="sale in report.voids" :key="sale.number">
                        <td>{{ sale.number }} · dijual {{ sale.sold_on }}<template v-if="sale.reason"> · {{ sale.reason }}</template></td>
                        <td>{{ formatRupiah(sale.total) }}</td>
                    </tr>
                </tbody>
            </table>
        </template>

        <p v-if="report.notes" class="daily-print__notes">Catatan penutupan: {{ report.notes }}</p>
        <p class="daily-print__closed">Ditutup oleh {{ report.closed_by_name }} · {{ dateTimeFormat.format(new Date(report.closed_at)) }}</p>

        <div class="daily-print__signs">
            <div><span>Admin</span></div>
            <div><span>Pemilik</span></div>
        </div>
    </div>
</template>

<style>
@page {
    size: A4 portrait;
    margin: 12mm;
}

.daily-print {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
}

.daily-print header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #000;
}

.daily-print header b,
.daily-print header span {
    display: block;
}

.daily-print__title {
    text-align: right;
}

.daily-print__title h1 {
    margin: 0;
    font-size: 18px;
    letter-spacing: 0.1em;
}

.daily-print h2 {
    margin: 14px 0 4px;
    font-size: 13px;
}

.daily-print__rows,
.daily-print__shifts {
    width: 100%;
    border-collapse: collapse;
}

.daily-print__rows td {
    padding: 3px 6px;
    border-bottom: 1px solid #bbb;
}

.daily-print__rows td:last-child {
    text-align: right;
}

.daily-print .daily-print__total td {
    border-bottom: 2px solid #000;
    font-weight: 700;
}

.daily-print__shifts th,
.daily-print__shifts td {
    padding: 4px 6px;
    border: 1px solid #000;
    text-align: right;
}

.daily-print__shifts th:first-child,
.daily-print__shifts th:nth-child(2),
.daily-print__shifts td:first-child,
.daily-print__shifts td:nth-child(2) {
    text-align: left;
}

.daily-print__shifts td small {
    display: block;
    color: #444;
}

.daily-print__shifts tfoot td {
    font-weight: 700;
}

.daily-print .daily-print__empty {
    text-align: center;
}

.daily-print__notes {
    margin: 12px 0 0;
}

.daily-print__closed {
    margin: 6px 0 0;
    color: #444;
}

.daily-print__signs {
    display: flex;
    justify-content: space-around;
    margin-top: 28px;
    text-align: center;
}

.daily-print__signs div {
    width: 36%;
}

.daily-print__signs span {
    display: block;
    padding-top: 56px;
    padding-bottom: 2px;
    border-bottom: 1px solid #000;
}
</style>
