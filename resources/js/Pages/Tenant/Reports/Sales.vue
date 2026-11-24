<script setup>
import { computed, ref, watch } from 'vue';
import { Deferred, Link, router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import PieChart from '../../../Components/PieChart.vue';
import RankedBars from '../../../Components/RankedBars.vue';
import SalesReportTabs from '../../../Components/SalesReportTabs.vue';
import { OTHER_COLOR, SERIES_COLORS } from '../../../Composables/chartColors';
import { printSheet } from '../../../Composables/printSheet';
import { urlForRange } from '../../../Composables/reportRange';
import { useHoverPopup } from '../../../Composables/useHoverPopup';
import { formatDeduction, formatRupiah } from '../../../Composables/useMoneyFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `summary`, `comparison`, `payments`, `products` and `groups` are deferred props: the page shows at once and each
 * part fills in when the server has it, so they are undefined until then and only read inside a `<Deferred>` that
 * waits for them.
 */
const props = defineProps({
    period: { type: Object, required: true },
    summary: { type: Object, default: undefined },
    comparison: { type: Object, default: undefined },
    payments: { type: Object, default: undefined },
    products: { type: Object, default: undefined },
    groups: { type: Object, default: undefined },
    /** Whether the period asked for was longer than the report allows and was cut back from its start. */
    is_period_cut_back: { type: Boolean, required: true },
    max_days: { type: Number, required: true },
    urls: { type: Object, required: true },
});

const groupingOptions = [
    { label: 'Kategori', value: 'category' },
    { label: 'Merek', value: 'brand' },
];

const page = usePage();

const from = ref(props.period.from);
const to = ref(props.period.to);

const dayFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);

/** A day number is written under a bar for at most this many bars, so a long period does not pile them up. */
const MAX_BAR_LABELS = 31;

/**
 * The bars of the daily chart: each day's net sales and gross profit against the biggest of either, so the two stand
 * on one scale and the tallest bar fills the chart. A day that lost money has no bar below the line, only the least.
 */
const bars = computed(() => {
    const days = props.summary?.days ?? [];
    const largest = Math.max(...days.flatMap((day) => [Number(day.net_total), Number(day.gross_profit)]), 1);
    const heightOf = (amount) => Math.max((Math.max(Number(amount), 0) / largest) * 100, 2);

    const labelEvery = Math.ceil(days.length / MAX_BAR_LABELS);

    return days.map((day, index) => ({ ...day, salesHeight: heightOf(day.net_total), profitHeight: heightOf(day.gross_profit), isLabelled: index % labelEvery === 0 }));
});

/**
 * How a figure moved against the period before, from the percent the server worked out: a rise or a fall with its
 * size, or nothing to say when there was nothing before to move from.
 * @param {string|null|undefined} percent
 * @returns {{text: string, tone: 'up'|'down'|'flat'|'none'}}
 */
function describeChange(percent) {
    if (percent === null || percent === undefined) {
        return { text: 'tidak ada pembanding', tone: 'none' };
    }

    const change = Number(percent);

    if (change === 0) {
        return { text: 'sama', tone: 'flat' };
    }

    return { text: `${change > 0 ? '▲' : '▼'} ${Math.abs(change).toFixed(1)}%`, tone: change > 0 ? 'up' : 'down' };
}

const fullDayFormat = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

/** The day whose details are open, for its bar to stay lit while the dialog is up; the dialog stays up long enough to reach its link. */
const { openKey: openDay, show: showDay, hideSoon: hideDaySoon, close: closeDay } = useHoverPopup();

/** The receipts of one day, in the receipt list. */
const receiptsOfDay = (day) => urlForRange(page.props.store.sales_receipts_url, day, day);

/** The tables of products and of categories or brands, on the period on screen, opened on the grouping the pie is showing. */
const productsTabUrl = computed(() => urlForRange(page.props.store.sales_products_url, props.period.from, props.period.to, { groups_by: props.groups?.grouping ?? 'category' }));

const comparisonRange = computed(() => (props.comparison ? `${dayFormat.format(asLocalDate(props.comparison.from))} – ${dayFormat.format(asLocalDate(props.comparison.to))}` : ''));

/**
 * The methods the pie of payments names, each with its own colour for good, so a method keeps its colour whatever the
 * others come to. The two COD figures of the report (delivered or not) are one part here.
 */
const PAYMENT_PARTS = [
    { key: 'cash', label: 'Tunai', methods: ['cash'], color: SERIES_COLORS[0] },
    { key: 'transfer', label: 'Transfer', methods: ['transfer'], color: SERIES_COLORS[1] },
    { key: 'qris', label: 'QRIS', methods: ['qris'], color: SERIES_COLORS[2] },
    { key: 'edc', label: 'EDC', methods: ['edc'], color: SERIES_COLORS[3] },
    { key: 'cod', label: 'COD', methods: ['cod_delivered', 'cod_undelivered'], color: SERIES_COLORS[4] },
];

/** What settled a receipt before it was made: an order deposit taken earlier, or the credit of a returned receipt. */
const SETTLED_BEFORE_METHODS = ['order_deposit', 'return_credit'];

const paymentSlices = computed(() => {
    const methods = props.payments?.methods ?? [];
    const amountOf = (keys) => methods.filter((method) => keys.includes(method.method)).reduce((sum, method) => sum + Number(method.amount), 0);

    /** The space inside "Rp 800.000" does not break, so a narrow legend wraps between the two lines and never inside an amount. */
    const unbreakable = (amount) => formatRupiah(amount).replace(' ', ' ');
    const codNote = `belum diantar ${unbreakable(amountOf(['cod_undelivered']))}\nsudah diantar ${unbreakable(amountOf(['cod_delivered']))}`;

    return [
        ...PAYMENT_PARTS.map((part) => ({ key: part.key, label: part.label, value: amountOf(part.methods), color: part.color, note: part.key === 'cod' ? codNote : undefined })),
        { key: 'settled_before', label: 'Uang muka & kredit retur', value: amountOf(SETTLED_BEFORE_METHODS), color: OTHER_COLOR },
    ];
});

/**
 * The biggest categories (or brands) by sales, the rest folded into one part so the pie keeps to a handful of parts it can
 * be read in. The rest is what the server says all of them sold, less the ones shown.
 */
const groupSlices = computed(() => {
    if (!props.groups) {
        return [];
    }

    const shown = props.groups.data.slice(0, SERIES_COLORS.length).map((group, index) => ({ key: group.id ?? 'none', label: group.name, value: Number(group.revenue), color: SERIES_COLORS[index] }));
    const rest = Number(props.groups.revenue_total) - shown.reduce((sum, slice) => sum + slice.value, 0);

    return rest > 0.005 ? [...shown, { key: 'rest', label: 'Lainnya', value: rest, color: OTHER_COLOR }] : shown;
});

const productRows = computed(() => (props.products?.data ?? []).map((product) => ({ key: product.product_id, label: product.name, value: Number(product.profit) })));

/** What the page is showing apart from the period, as the address holds it, so the page comes back as it was when it is reloaded. */
const groupingQuery = () => ({ groups_by: props.groups?.grouping });

/**
 * Asks for the period typed. Skipped when it is the period on screen already, which is also what the server answers when it swapped the dates or cut the period back and the fields were set to that answer.
 */
function applyPeriod() {
    if (from.value && to.value && (from.value !== props.period.from || to.value !== props.period.to)) {
        router.get(page.url.split('?')[0], { from: from.value, to: to.value, ...groupingQuery() }, { preserveState: true, replace: true, preserveScroll: true });
    }
}

watch([from, to], applyPeriod);

watch(
    () => [props.period.from, props.period.to],
    ([periodFrom, periodTo]) => {
        from.value = periodFrom;
        to.value = periodTo;
    },
);

/** Switching between categories and brands asks the server for the groups alone, so the rest of the report is not worked out again. */
const changeGrouping = (grouping) => router.get(page.url.split('?')[0], { from: props.period.from, to: props.period.to, groups_by: grouping }, { only: ['groups'], preserveState: true, replace: true, preserveScroll: true });

/** The report prints from a hidden frame, for the period on screen. */
const printReport = () => printSheet(() => props.urls.sheet);
</script>

<template>
    <StoreLayout title="Laporan Penjualan">
        <template #actions>
            <q-btn outline no-caps icon="print" label="Cetak" @click="printReport" />
        </template>

        <SalesReportTabs current="summary" />

        <div class="period">
            <DateInput v-model="from" label="Dari" outlined dense :error="!from" error-message="Wajib diisi" class="period__date" />
            <DateInput v-model="to" label="Sampai" outlined dense :error="!to" error-message="Wajib diisi" class="period__date" />
        </div>
        <p v-if="is_period_cut_back" class="notice">Periode terlalu panjang, jadi laporan hanya memuat {{ max_days }} hari terakhir sampai tanggal akhir. Pilih periode yang lebih pendek untuk melihat yang lebih awal.</p>

        <Deferred data="summary">
            <template #fallback>
                <div class="kpis">
                    <q-skeleton v-for="placeholder in 3" :key="placeholder" height="86px" />
                </div>
            </template>

            <div class="kpis">
                <div class="kpi">
                    <small>Penjualan bersih</small>
                    <b>{{ formatRupiah(summary.totals.net_total) }}</b>
                    <Deferred data="comparison">
                        <template #fallback><q-skeleton type="text" width="60%" /></template>
                        <em class="kpi__change" :class="`kpi__change--${describeChange(comparison.change_percent.net_total).tone}`">{{ describeChange(comparison.change_percent.net_total).text }} dibanding {{ comparisonRange }}</em>
                    </Deferred>
                    <em>{{ formatRupiah(summary.totals.gross_total) }} − retur {{ formatRupiah(summary.totals.returns_total) }}</em>
                </div>
                <div class="kpi">
                    <small>Nota</small>
                    <b>{{ summary.totals.receipt_count }}</b>
                    <Deferred data="comparison">
                        <template #fallback><q-skeleton type="text" width="60%" /></template>
                        <em class="kpi__change" :class="`kpi__change--${describeChange(comparison.change_percent.receipt_count).tone}`">{{ describeChange(comparison.change_percent.receipt_count).text }} dibanding {{ comparisonRange }}</em>
                    </Deferred>
                    <em>nota biasa {{ formatRupiah(summary.receipts.median_total) }} <abbr title="Nilai tengah: separuh nota lebih kecil dan separuhnya lebih besar, jadi satu nota besar tidak menariknya">(median)</abbr> · rata-rata {{ formatRupiah(summary.receipts.average_total) }}</em>
                    <em>{{ Number(summary.receipts.median_lines) }} jenis barang per nota (median)</em>
                    <em v-if="summary.totals.voids_count > 0">tidak termasuk {{ summary.totals.voids_count }} nota void ({{ formatRupiah(summary.totals.voids_total) }}) yang di-void pada periode ini</em>
                    <em v-else>tidak termasuk nota void</em>
                </div>
                <div class="kpi">
                    <small>Laba kotor (estimasi)</small>
                    <b>{{ formatRupiah(summary.totals.gross_profit) }}</b>
                    <Deferred data="comparison">
                        <template #fallback><q-skeleton type="text" width="60%" /></template>
                        <em class="kpi__change" :class="`kpi__change--${describeChange(comparison.change_percent.gross_profit).tone}`">{{ describeChange(comparison.change_percent.gross_profit).text }} dibanding {{ comparisonRange }}</em>
                    </Deferred>
                    <em>margin {{ summary.totals.margin_percent }}% · modal = harga beli terakhir</em>
                    <em v-if="Number(summary.totals.dead_stock_loss) > 0">sudah dikurangi rugi dead stock {{ formatRupiah(summary.totals.dead_stock_loss) }}</em>
                    <em v-if="summary.products_without_cost > 0" class="kpi__warn">{{ summary.products_without_cost }} barang belum punya harga beli, jadi labanya sama dengan penjualannya</em>
                </div>
            </div>
        </Deferred>

        <section class="card">
            <div class="card__head">
                <h2>Penjualan dan laba per hari</h2>
                <div class="legend">
                    <span><i class="legend__swatch legend__swatch--sales"></i>Penjualan bersih</span>
                    <span><i class="legend__swatch legend__swatch--profit"></i>Laba kotor</span>
                </div>
            </div>
            <Deferred data="summary">
                <template #fallback>
                    <q-skeleton height="150px" />
                </template>

                <div class="bars">
                    <button
                        v-for="day in bars"
                        :key="day.date"
                        type="button"
                        class="bars__day"
                        :class="{ 'bars__day--on': openDay === day.date }"
                        :aria-label="`Rincian ${fullDayFormat.format(asLocalDate(day.date))}`"
                        @mouseenter="showDay(day.date)"
                        @mouseleave="hideDaySoon"
                        @focus="showDay(day.date)"
                        @blur="hideDaySoon"
                        @click="showDay(day.date)"
                    >
                        <span class="bars__track">
                            <i class="bars__sales" :style="{ height: `${day.salesHeight}%` }"></i>
                            <i class="bars__profit" :style="{ height: `${day.profitHeight}%` }"></i>
                        </span>
                        <span class="bars__label">{{ day.isLabelled ? asLocalDate(day.date).getDate() : '' }}</span>

                        <q-menu no-parent-event no-focus no-refocus anchor="top middle" self="bottom middle" :offset="[0, 8]" :model-value="openDay === day.date" @update:model-value="(isOpen) => !isOpen && closeDay(day.date)">
                            <div class="day-pop" @mouseenter="showDay(day.date)" @mouseleave="hideDaySoon">
                                <strong>{{ fullDayFormat.format(asLocalDate(day.date)) }}</strong>
                                <dl class="day-pop__rows">
                                    <div><dt><i class="legend__swatch legend__swatch--sales"></i>Penjualan bersih</dt><dd>{{ formatRupiah(day.net_total) }}</dd></div>
                                    <div><dt><i class="legend__swatch legend__swatch--profit"></i>Laba kotor</dt><dd>{{ formatRupiah(day.gross_profit) }}</dd></div>
                                    <div><dt>Nota</dt><dd>{{ day.receipt_count }}</dd></div>
                                    <div v-if="Number(day.returns_total) > 0"><dt>Retur</dt><dd>{{ formatDeduction(day.returns_total) }}</dd></div>
                                    <div v-if="day.voids_count > 0"><dt>Void</dt><dd>{{ day.voids_count }} nota ({{ formatRupiah(day.voids_total) }})</dd></div>
                                </dl>
                                <Link v-if="day.receipt_count > 0 || day.voids_count > 0" :href="receiptsOfDay(day.date)" class="day-pop__link">Lihat nota hari ini</Link>
                            </div>
                        </q-menu>
                    </button>
                </div>
            </Deferred>
        </section>

        <div class="columns">
            <section class="card">
                <h2>Metode bayar <small>(penjualan, sebelum retur)</small></h2>
                <Deferred data="payments">
                    <template #fallback>
                        <q-skeleton height="170px" />
                    </template>

                    <PieChart :slices="paymentSlices" :format-value="formatRupiah" label="Penjualan per metode bayar" />
                    <p class="note">COD ditagih saat diantar, jadi belum ada uang yang diterima.</p>
                </Deferred>
            </section>

            <section class="card">
                <div class="card__head">
                    <h2>Penjualan per {{ groups?.grouping === 'brand' ? 'merek' : 'kategori' }} <small>(setelah retur)</small></h2>
                    <q-btn-toggle
                        :model-value="groups?.grouping ?? 'category'"
                        :options="groupingOptions"
                        no-caps
                        unelevated
                        dense
                        toggle-color="primary"
                        color="grey-3"
                        text-color="grey-8"
                        @update:model-value="changeGrouping"
                    />
                </div>
                <Deferred data="groups">
                    <template #fallback>
                        <q-skeleton height="170px" />
                    </template>

                    <template #default="{ reloading }">
                        <div :class="{ 'is-reloading': reloading }">
                            <PieChart :slices="groupSlices" :format-value="formatRupiah" :label="`Penjualan per ${groups.grouping === 'brand' ? 'merek' : 'kategori'}`" />
                        </div>
                    </template>
                </Deferred>
                <Link :href="productsTabUrl" class="card__more card__more--below">Lihat tabel lengkap</Link>
            </section>
        </div>

        <section class="card">
            <div class="card__head">
                <h2>Laba kotor terbesar per barang <small>(7 teratas, setelah retur)</small></h2>
                <Link :href="productsTabUrl" class="card__more">Lihat semua barang</Link>
            </div>
            <Deferred data="products">
                <template #fallback>
                    <q-skeleton height="200px" />
                </template>

                <RankedBars :rows="productRows" :format-value="formatRupiah" />
            </Deferred>
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

.kpi .kpi__change {
    font-weight: 600;
}

.kpi .kpi__change--up {
    color: var(--q-positive);
}

.kpi .kpi__change--down {
    color: var(--q-negative);
}

.kpi .kpi__warn {
    margin-top: 4px;
    color: #b45309;
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

.card__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    margin-bottom: 12px;
}

.card__head h2 {
    margin: 0;
}

.legend {
    display: flex;
    gap: 14px;
    color: var(--app-mute);
    font-size: 12px;
}

.legend__swatch {
    display: inline-block;
    width: 10px;
    height: 10px;
    margin-right: 6px;
    border-radius: 2px;
}

.legend__swatch--sales,
.bars__sales {
    background: var(--q-primary);
}

.legend__swatch--profit,
.bars__profit {
    background: var(--q-positive);
}

.columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 16px;
    align-items: start;
}

.is-reloading {
    opacity: 0.5;
    transition: opacity 0.15s;
}

.bars {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    height: 150px;
}

/** A bar is a button: the whole column answers the pointer, so a thin bar is no harder to hit than a wide one. */
.bars__day {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    min-width: 0;
    height: 100%;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: none;
    color: var(--app-mute);
    cursor: pointer;
    font: inherit;
    font-size: 11px;
    transition: background-color 0.15s;
}

.bars__day:hover,
.bars__day--on {
    background: var(--app-brand-soft);
}

.bars__day:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 1px;
}

/** Holds only the bar, so every bar stands on the same baseline whether or not its day is labelled. */
.bars__track {
    display: flex;
    flex: 1;
    align-items: flex-end;
    justify-content: center;
    width: 100%;
    min-height: 0;
}

.bars__label {
    flex: none;
    height: 16px;
    line-height: 16px;
    white-space: nowrap;
}

.day-pop {
    min-width: 230px;
    padding: 12px 14px;
}

.day-pop__rows {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 8px 0 0;
}

.day-pop__rows div {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    font-size: 13px;
}

.day-pop__rows dt {
    color: var(--app-ink-soft);
}

.day-pop__rows dd {
    margin: 0;
    font-weight: 600;
    white-space: nowrap;
}

.card__more {
    font-size: 13px;
}

.card__more--below {
    display: inline-block;
    margin-top: 10px;
}

.day-pop__link {
    display: inline-block;
    margin-top: 10px;
    font-size: 13px;
}

.bars__track {
    gap: 2px;
}

.bars__day i {
    display: block;
    flex: 1;
    min-width: 0;
    max-width: 18px;
    border-radius: 4px 4px 0 0;
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
</style>
