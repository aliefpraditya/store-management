<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import { printSheet } from '../../../Composables/printSheet';
import { formatDeduction, formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    report: { type: Object, required: true },
    /** Whether the period asked for was longer than the report allows and was cut back from its start. */
    is_period_cut_back: { type: Boolean, required: true },
    max_days: { type: Number, required: true },
    urls: { type: Object, required: true },
});

const page = usePage();

const from = ref(props.report.from);
const to = ref(props.report.to);

const dayFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);

/** A day number is written under a bar for at most this many bars, so a long period does not pile them up. */
const MAX_BAR_LABELS = 31;

/** The bars of the daily chart: each day's net sales against the best day, so the tallest bar fills the chart. */
const bars = computed(() => {
    const largest = Math.max(...props.report.days.map((day) => Number(day.net_total)), 1);

    const labelEvery = Math.ceil(props.report.days.length / MAX_BAR_LABELS);

    return props.report.days.map((day, index) => ({ ...day, height: Math.max((Math.max(Number(day.net_total), 0) / largest) * 100, 2), isLabelled: index % labelEvery === 0 }));
});

/**
 * Asks for the period typed. Skipped when it is the period on screen already, which is also what the server answers when it swapped the dates or cut the period back and the fields were set to that answer.
 */
function applyPeriod() {
    if (from.value && to.value && (from.value !== props.report.from || to.value !== props.report.to)) {
        router.get(page.url.split('?')[0], { from: from.value, to: to.value }, { preserveState: true, replace: true, preserveScroll: true });
    }
}

watch([from, to], applyPeriod);

watch(
    () => [props.report.from, props.report.to],
    ([reportFrom, reportTo]) => {
        from.value = reportFrom;
        to.value = reportTo;
    },
);

/** The report prints from a hidden frame, for the period on screen. */
const printReport = () => printSheet(() => props.urls.sheet);
</script>

<template>
    <StoreLayout title="Laporan Penjualan">
        <template #actions>
            <q-btn outline no-caps icon="print" label="Cetak" @click="printReport" />
        </template>

        <div class="period">
            <DateInput v-model="from" label="Dari" outlined dense class="period__date" />
            <DateInput v-model="to" label="Sampai" outlined dense class="period__date" />
        </div>
        <p v-if="is_period_cut_back" class="notice">Periode terlalu panjang, jadi laporan hanya memuat {{ max_days }} hari terakhir sampai tanggal akhir. Pilih periode yang lebih pendek untuk melihat yang lebih awal.</p>

        <div class="kpis">
            <div class="kpi">
                <small>Penjualan bersih</small>
                <b>{{ formatRupiah(report.totals.net_total) }}</b>
                <em>{{ formatRupiah(report.totals.gross_total) }} − retur {{ formatRupiah(report.totals.returns_total) }}</em>
            </div>
            <div class="kpi">
                <small>Nota</small>
                <b>{{ report.totals.receipt_count }}</b>
                <em v-if="report.totals.voids_count > 0">tidak termasuk {{ report.totals.voids_count }} nota void ({{ formatRupiah(report.totals.voids_total) }}) yang di-void pada periode ini</em>
                <em v-else>tidak termasuk nota void</em>
            </div>
            <div class="kpi">
                <small>Laba kotor (estimasi)</small>
                <b>{{ formatRupiah(report.totals.gross_profit) }}</b>
                <em>modal = harga beli terakhir</em>
                <em v-if="Number(report.totals.dead_stock_loss) > 0">sudah dikurangi rugi dead stock {{ formatRupiah(report.totals.dead_stock_loss) }}</em>
                <em v-if="report.products_without_cost > 0" class="kpi__warn">{{ report.products_without_cost }} barang belum punya harga beli, jadi labanya sama dengan penjualannya</em>
            </div>
        </div>

        <section class="card">
            <h2>Penjualan bersih per hari</h2>
            <div class="bars">
                <div v-for="day in bars" :key="day.date" class="bars__day" :title="`${dayFormat.format(asLocalDate(day.date))}: ${formatRupiah(day.net_total)} (${day.receipt_count} nota)`">
                    <i :style="{ height: `${day.height}%` }"></i>
                    <span>{{ day.isLabelled ? asLocalDate(day.date).getDate() : '' }}</span>
                </div>
            </div>
        </section>

        <div class="columns">
            <section class="card">
                <h2>Per hari</h2>
                <table class="table">
                    <thead><tr><th>Tanggal</th><th class="table__right">Nota</th><th class="table__right">Penjualan</th><th class="table__right">Retur</th><th class="table__right">Bersih</th><th v-if="report.totals.voids_count > 0" class="table__right">Void</th></tr></thead>
                    <tbody>
                        <tr v-for="day in report.days" :key="day.date">
                            <td>{{ dayFormat.format(asLocalDate(day.date)) }}</td>
                            <td class="table__right">{{ day.receipt_count }}</td>
                            <td class="table__right">{{ formatRupiah(day.gross_total) }}</td>
                            <td class="table__right table__minus">{{ Number(day.returns_total) > 0 ? formatDeduction(day.returns_total) : '-' }}</td>
                            <td class="table__right"><b>{{ formatRupiah(day.net_total) }}</b></td>
                            <td v-if="report.totals.voids_count > 0" class="table__right">{{ day.voids_count > 0 ? `${day.voids_count} · ${formatRupiah(day.voids_total)}` : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <div class="column">
                <section class="card">
                    <h2>Per metode bayar <small>(penjualan, sebelum retur)</small></h2>
                    <dl class="rows">
                        <div v-for="method in report.methods" :key="method.method"><dt>{{ method.label }}</dt><dd>{{ formatRupiah(method.amount) }}</dd></div>
                        <div class="rows__sum"><dt>Penjualan</dt><dd>{{ formatRupiah(report.totals.gross_total) }}</dd></div>
                        <div><dt>Retur disetujui</dt><dd>{{ formatDeduction(report.totals.returns_total) }}</dd></div>
                        <div class="rows__sum"><dt>Penjualan bersih</dt><dd>{{ formatRupiah(report.totals.net_total) }}</dd></div>
                    </dl>
                    <p class="note">COD ditagih saat diantar, jadi belum uang yang diterima. Retur tidak dipecah per metode karena tidak terikat pada metode bayar nota asalnya.</p>
                </section>

                <section class="card">
                    <h2>Per kasir <small>(penjualan, sebelum retur)</small></h2>
                    <p v-if="report.cashiers.length === 0" class="empty">Belum ada penjualan pada periode ini.</p>
                    <dl v-else class="rows">
                        <div v-for="cashier in report.cashiers" :key="cashier.cashier_name"><dt>{{ cashier.cashier_name }}<small> · {{ cashier.receipt_count }} nota</small></dt><dd>{{ formatRupiah(cashier.total) }}</dd></div>
                    </dl>
                </section>
            </div>
        </div>

        <section class="card">
            <h2>Laba kotor per barang <small>({{ report.product_count > report.products.length ? `${report.product_limit} teratas dari ${report.product_count} barang` : `${report.product_count} barang` }}, setelah retur)</small></h2>
            <p v-if="report.products.length === 0" class="empty">Belum ada penjualan pada periode ini.</p>
            <table v-else class="table">
                <thead>
                    <tr><th>Barang</th><th class="table__right">Terjual</th><th class="table__right">Penjualan</th><th class="table__right">Modal</th><th class="table__right">Laba</th><th class="table__right">Margin</th></tr>
                </thead>
                <tbody>
                    <tr v-for="product in report.products" :key="product.product_id">
                        <td>{{ product.name }}</td>
                        <td class="table__right">{{ formatQuantity(product.quantity) }} {{ product.unit }}</td>
                        <td class="table__right">{{ formatRupiah(product.revenue) }}</td>
                        <td class="table__right">{{ formatRupiah(product.cost) }}</td>
                        <td class="table__right"><b>{{ formatRupiah(product.profit) }}</b></td>
                        <td class="table__right">{{ product.margin_percent }}%</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </StoreLayout>
</template>

<style scoped>
.period {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.period__date {
    width: 170px;
}

.kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.kpi {
    padding: 14px 16px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.kpi small,
.kpi b,
.kpi em {
    display: block;
}

.kpi small {
    color: var(--app-mute);
    font-size: 12px;
}

.kpi b {
    font-size: 22px;
}

.kpi em {
    color: var(--app-mute);
    font-size: 12px;
    font-style: normal;
}

.card {
    margin-bottom: 16px;
    padding: 16px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0 0 12px;
    font-size: 15px;
}

.card h2 small {
    color: var(--app-mute);
    font-weight: 400;
}

.columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 16px;
    align-items: start;
}

.column .card:last-child {
    margin-bottom: 16px;
}

.bars {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    height: 150px;
}

.bars__day {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
    color: var(--app-mute);
    font-size: 11px;
}

.bars__day i {
    display: block;
    width: 100%;
    max-width: 36px;
    border-radius: 4px 4px 0 0;
    background: var(--q-primary);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    padding: 8px 6px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.table td {
    padding: 8px 6px;
    border-bottom: 1px solid var(--app-line);
}

.table .table__right {
    text-align: right;
    white-space: nowrap;
}

.table .table__minus {
    color: #be123c;
}

.rows {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0;
}

.rows div {
    display: flex;
    justify-content: space-between;
    color: var(--app-ink-soft);
}

.rows dd {
    margin: 0;
    color: var(--app-ink);
    font-weight: 600;
}

.rows small {
    color: var(--app-mute);
}

.rows .rows__sum {
    padding-top: 6px;
    border-top: 1px solid var(--app-line);
    font-weight: 700;
}

.empty {
    margin: 0;
    color: var(--app-mute);
}

.notice {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fff8e6;
    font-size: 13px;
}

.note {
    margin: 8px 0 0;
    color: var(--app-mute);
    font-size: 12px;
}

.kpi .kpi__warn {
    margin-top: 4px;
    color: #b45309;
}
</style>