<script setup>
import { computed } from 'vue';
import { formatDeduction, formatRupiah } from '../Composables/useMoneyFormat';
import ThermalPageSize from './ThermalPageSize.vue';

const props = defineProps({
    /**
     * The blind report (`is_blind`) holds what the cashier counted and the number of receipts only; the full one adds
     * the sales, payments, cash movements, expected cash and difference.
     * @type {{is_blind: boolean, label: string, register_name: string, cashier_name: string, business_date: string, opened_at: string, counted_at: string|null, closed_at: string|null, status: string, status_label: string, opening_cash: string, sale_count: number, cod: object, cod_received?: {cash: string, transfer: string, qris: string, edc: string, total: string}, counted_cash: string|null, deposit_amount: string|null, drawer_left_cash: string|null, closing_note: string|null, store: {name: string, address: string|null, phone: string|null}, paper_width: number}}
     */
    report: { type: Object, required: true },
});

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

/**
 * The COD receipts of the shift, none when there were none. COD is not money in the drawer. The blind report counts them
 * (`delivered_count`), the full one adds what they are worth (`delivered.count` and `.amount`).
 */
const codRows = computed(() => {
    const { cod, is_blind: isBlind } = props.report;
    const delivered = isBlind ? cod.delivered_count : cod.delivered.count;
    const undelivered = isBlind ? cod.undelivered_count : cod.undelivered.count;

    if (delivered + undelivered === 0) {
        return [];
    }

    return isBlind
        ? [{ label: 'Nota COD sudah diantar', value: String(delivered) }, { label: 'Nota COD belum diantar', value: String(undelivered) }]
        : [
            { label: `COD sudah diantar (${delivered} nota)`, value: formatRupiah(cod.delivered.amount) },
            { label: `COD belum diantar (${undelivered} nota)`, value: formatRupiah(cod.undelivered.amount) },
        ];
});

const CASH_RECEIVED_METHODS = [
    { key: 'cash', label: 'Tunai' },
    { key: 'transfer', label: 'Transfer' },
    { key: 'qris', label: 'QRIS' },
    { key: 'edc', label: 'EDC' },
];

/**
 * The COD money this shift received (the full report only; the blind one has no amounts), by method, none when nothing
 * was received. Received cash is in the drawer, the rest went to an account.
 */
const codReceivedRows = computed(() => {
    const received = props.report.cod_received;

    if (props.report.is_blind || !(Number(received?.total) > 0)) {
        return [];
    }

    return [
        { label: 'Pelunasan COD diterima', value: formatRupiah(received.total) },
        ...CASH_RECEIVED_METHODS.filter((method) => Number(received[method.key]) > 0).map((method) => ({ label: `· ${method.label}`, value: formatRupiah(received[method.key]) })),
    ];
});
</script>

<template>
    <div class="shift-print">
        <ThermalPageSize :width="report.paper_width" />
        <header>
            <b>{{ report.store.name }}</b>
            <span v-if="report.store.address">{{ report.store.address }}</span>
            <span v-if="report.store.phone">{{ report.store.phone }}</span>
        </header>

        <p class="shift-print__title">LAPORAN AKHIR SHIFT</p>

        <div class="shift-print__meta">
            <span>{{ report.label }} · {{ report.register_name }}</span>
            <span>Kasir : {{ report.cashier_name }}</span>
            <span>Tanggal : {{ dateFormat.format(new Date(`${report.business_date}T00:00:00`)) }}</span>
            <span>Buka : {{ dateTimeFormat.format(new Date(report.opened_at)) }}</span>
            <span v-if="report.counted_at">Dihitung : {{ dateTimeFormat.format(new Date(report.counted_at)) }}</span>
            <span v-if="report.closed_at">Tutup : {{ dateTimeFormat.format(new Date(report.closed_at)) }}</span>
            <span v-else>Status : {{ report.status_label }}</span>
        </div>

        <template v-if="!report.is_blind">
            <p class="shift-print__section">Penjualan</p>
            <dl>
                <div><dt>Jumlah nota</dt><dd>{{ report.sale_count }}</dd></div>
                <div class="shift-print__total"><dt>Total penjualan</dt><dd>{{ formatRupiah(report.total_sales) }}</dd></div>
                <div v-for="payment in report.payments" :key="payment.method"><dt>{{ payment.method }}</dt><dd>{{ formatRupiah(payment.amount) }}</dd></div>
                <div v-if="Number(report.void_deposits) > 0" class="shift-print__note"><dt>Termasuk DP nota void</dt><dd>{{ formatRupiah(report.void_deposits) }}</dd></div>
                <div v-if="Number(report.returns_total) > 0"><dt>Retur disetujui</dt><dd>{{ formatRupiah(report.returns_total) }}</dd></div>
            </dl>
        </template>
        <dl v-else>
            <div><dt>Jumlah nota</dt><dd>{{ report.sale_count }}</dd></div>
        </dl>

        <dl v-if="codRows.length > 0 || codReceivedRows.length > 0">
            <div v-for="row in [...codRows, ...codReceivedRows]" :key="row.label"><dt>{{ row.label }}</dt><dd>{{ row.value }}</dd></div>
        </dl>

        <template v-if="!report.is_blind">
            <p class="shift-print__section">Kas</p>
            <dl>
                <div><dt>Modal kas awal</dt><dd>{{ formatRupiah(report.opening_cash) }}</dd></div>
                <div v-if="Number(report.order_deposit_cash) !== 0"><dt>DP pesanan tunai</dt><dd>{{ formatRupiah(report.order_deposit_cash) }}</dd></div>
                <div v-if="Number(report.cod_received?.cash) > 0"><dt>Pelunasan COD tunai</dt><dd>{{ formatRupiah(report.cod_received.cash) }}</dd></div>
                <div v-if="Number(report.cash_refunds) > 0"><dt>Retur tunai</dt><dd>{{ formatDeduction(report.cash_refunds) }}</dd></div>
                <div v-if="Number(report.void_cash_refunds) > 0"><dt>DP void dikembalikan tunai</dt><dd>{{ formatDeduction(report.void_cash_refunds) }}</dd></div>
                <div v-if="Number(report.drawer_expenses) > 0"><dt>Pengeluaran dari laci</dt><dd>{{ formatDeduction(report.drawer_expenses) }}</dd></div>
                <div><dt>Tunai seharusnya</dt><dd>{{ formatRupiah(report.expected_cash) }}</dd></div>
            </dl>
        </template>
        <p v-else class="shift-print__section">Kas</p>

        <dl>
            <div v-if="report.is_blind"><dt>Modal kas awal</dt><dd>{{ formatRupiah(report.opening_cash) }}</dd></div>
            <div><dt>Tunai dihitung</dt><dd>{{ report.counted_cash === null ? '-' : formatRupiah(report.counted_cash) }}</dd></div>
            <div v-if="!report.is_blind && report.counted_cash !== null" class="shift-print__total"><dt>Selisih</dt><dd>{{ formatRupiah(report.cash_difference) }}</dd></div>
            <div><dt>Ditinggal di laci</dt><dd>{{ report.drawer_left_cash === null ? '-' : formatRupiah(report.drawer_left_cash) }}</dd></div>
            <div class="shift-print__total"><dt>Setoran</dt><dd>{{ report.deposit_amount === null ? '-' : formatRupiah(report.deposit_amount) }}</dd></div>
        </dl>

        <template v-if="!report.is_blind && report.expenses.length > 0">
            <p class="shift-print__section">Pengeluaran dari laci</p>
            <dl>
                <div v-for="expense in report.expenses" :key="expense.number" :class="{ 'shift-print__voided': expense.status === 'voided' }"><dt>{{ expense.description }}</dt><dd>{{ formatRupiah(expense.amount) }}</dd></div>
            </dl>
        </template>

        <p v-if="report.opening_cash_note && !report.is_blind" class="shift-print__text">Alasan modal awal berbeda: {{ report.opening_cash_note }}</p>
        <p v-if="report.closing_note" class="shift-print__text">Catatan kasir: {{ report.closing_note }}</p>

        <p v-if="report.is_blind && report.status === 'pending_review'" class="shift-print__text">Hitungan menunggu review Admin. Serahkan setoran sesuai angka di atas.</p>
        <p v-else-if="!report.is_blind && report.difference_status !== 'none'" class="shift-print__text">
            Selisih: {{ report.difference_status_label }}<template v-if="report.decided_by_name"> oleh {{ report.decided_by_name }}</template>.
            <template v-if="report.approval_note">{{ report.approval_note }}</template>
        </p>

        <div class="shift-print__signs">
            <div><span>Kasir</span></div>
            <div><span>Penerima setoran</span></div>
        </div>
    </div>
</template>

<style>
.shift-print {
    box-sizing: border-box;
    padding: 4mm;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.35;
    color: #000;
}

.shift-print header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.shift-print header b {
    font-size: 15px;
}

.shift-print__title {
    margin: 8px 0;
    font-size: 14px;
    font-weight: 700;
    text-align: center;
    letter-spacing: 0.1em;
}

.shift-print__meta {
    display: flex;
    flex-direction: column;
    margin-bottom: 6px;
}

.shift-print__section {
    margin: 8px 0 2px;
    padding-top: 4px;
    border-top: 1px dashed #000;
    font-weight: 700;
}

.shift-print dl {
    margin: 0;
}

.shift-print dl div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.shift-print dd {
    margin: 0;
    text-align: right;
}

.shift-print dl .shift-print__total {
    font-weight: 700;
}

.shift-print dl .shift-print__note {
    padding-left: 8px;
    font-style: italic;
}

.shift-print dl .shift-print__voided {
    text-decoration: line-through;
}

.shift-print__text {
    margin: 8px 0 0;
}

.shift-print__signs {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 10px;
    text-align: center;
}

.shift-print__signs div {
    flex: 1;
}

.shift-print__signs span {
    display: block;
    padding-top: 40px;
    border-bottom: 1px solid #000;
}
</style>
