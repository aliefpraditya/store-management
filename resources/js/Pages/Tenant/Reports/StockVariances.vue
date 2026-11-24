<script setup>
import { computed, ref, watch } from 'vue';
import { Deferred, Link, router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import PieChart from '../../../Components/PieChart.vue';
import RankedBars from '../../../Components/RankedBars.vue';
import StockReportTabs from '../../../Components/StockReportTabs.vue';
import { OTHER_COLOR, SERIES_COLORS } from '../../../Composables/chartColors';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `summary`, `reasons`, `products` and `opnames` are deferred props, each in a group of its own: the page shows at once and
 * each part fills in when the server has it, so they are undefined until then and only read inside a `<Deferred>` that waits
 * for them.
 */
const props = defineProps({
    period: { type: Object, required: true },
    summary: { type: Object, default: undefined },
    reasons: { type: Array, default: undefined },
    products: { type: Array, default: undefined },
    opnames: { type: Object, default: undefined },
    /** The page sizes the list of counts offers. */
    per_page_options: { type: Array, required: true },
    /** Whether the period asked for was longer than the report allows and was cut back from its start. */
    is_period_cut_back: { type: Boolean, required: true },
    max_days: { type: Number, required: true },
});

const page = usePage();

const from = ref(props.period.from);
const to = ref(props.period.to);

const shortDayFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
const asLocalDate = (isoDay) => new Date(`${isoDay}T00:00:00`);

/** The count's own page is for those who may count or post; the report is for those who may see reports, so the link is theirs alone. */
const canOpenCounts = computed(() => ['stock_opnames.count', 'stock_opnames.post'].some((permission) => page.props.auth.permissions.includes(permission)));

/** The space inside "Rp 800.000" does not break, so a narrow legend wraps between the lines and never inside an amount. */
const unbreakable = (amount) => formatRupiah(amount).replace(' ', ' ');

/**
 * What the shortage of a reason came to once the admin decided where the units went, as the lines under its name in the
 * pie: only the parts it has, so a reason without a decision (lost, say) has no note.
 */
function decisionNote(reason) {
    return [
        Number(reason.dead_stock_value) > 0 ? `${unbreakable(reason.dead_stock_value)} jadi dead stock` : null,
        Number(reason.damaged_goods_value) > 0 ? `${unbreakable(reason.damaged_goods_value)} jadi barang DG` : null,
        Number(reason.full_price_value) > 0 ? `${unbreakable(reason.full_price_value)} kembali ke rak` : null,
    ]
        .filter(Boolean)
        .join('\n');
}

/**
 * The reasons with the biggest shortage, the rest folded into one part so the pie keeps to a handful of parts it can be read in.
 * A reason that lost nothing (only found stock) has no part.
 */
const shortageSlices = computed(() => {
    const losing = (props.reasons ?? []).filter((reason) => Number(reason.shortage_value) > 0);
    const shown = losing.slice(0, SERIES_COLORS.length).map((reason, index) => ({ key: reason.label, label: reason.label, value: Number(reason.shortage_value), color: SERIES_COLORS[index], note: decisionNote(reason) || undefined }));
    const rest = losing.slice(SERIES_COLORS.length).reduce((sum, reason) => sum + Number(reason.shortage_value), 0);

    return rest > 0 ? [...shown, { key: 'rest', label: 'Lainnya', value: rest, color: OTHER_COLOR }] : shown;
});

const productRows = computed(() => (props.products ?? []).map((product) => ({ key: product.product_id, label: product.name, value: Number(product.shortage_value) })));

/** The list's size and page, as the address holds it, so a list that is turned does not undo the period and the page comes back as it was when reloaded. */
const tableQuery = () => ({ opnames_page: props.opnames?.current_page, opnames_per_page: props.opnames?.per_page });

/**
 * Asks for the period typed. Skipped when it is the period on screen already, which is also what the server answers when it
 * swapped the dates or cut the period back and the fields were set to that answer. The list keeps its size and starts from its first page.
 */
function applyPeriod() {
    if (from.value && to.value && (from.value !== props.period.from || to.value !== props.period.to)) {
        router.get(page.url.split('?')[0], { from: from.value, to: to.value, ...tableQuery(), opnames_page: undefined }, { preserveState: true, replace: true, preserveScroll: true });
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

/** Turning the list (its page or size) asks the server for that list only, so the rest of the page is not worked out again. */
const reloadOpnames = (change) => router.get(page.url.split('?')[0], { from: props.period.from, to: props.period.to, ...tableQuery(), ...change }, { only: ['opnames'], preserveState: true, replace: true, preserveScroll: true });

const showOpnamesPage = (opnamesPage) => reloadOpnames({ opnames_page: opnamesPage });
const changeOpnamesPerPage = (perPage) => reloadOpnames({ opnames_page: 1, opnames_per_page: perPage });
</script>

<template>
    <StoreLayout title="Laporan Stok">
        <StockReportTabs current="variances" />

        <div class="period">
            <DateInput v-model="from" label="Dari" outlined dense :error="!from" error-message="Wajib diisi" class="period__date" />
            <DateInput v-model="to" label="Sampai" outlined dense :error="!to" error-message="Wajib diisi" class="period__date" />
        </div>
        <p v-if="is_period_cut_back" class="notice">Periode terlalu panjang, jadi laporan hanya memuat {{ max_days }} hari terakhir sampai tanggal akhir. Pilih periode yang lebih pendek untuk melihat yang lebih awal.</p>

        <Deferred data="summary">
            <template #fallback>
                <div class="kpis">
                    <q-skeleton v-for="placeholder in 4" :key="placeholder" height="86px" />
                </div>
            </template>

            <div class="kpis">
                <div class="kpi">
                    <small>Selisih kurang (nilai modal)</small>
                    <b>{{ formatRupiah(summary.shortage_value) }}</b>
                    <em>{{ summary.shortage_items }} barang</em>
                </div>
                <div class="kpi">
                    <small>Selisih lebih (nilai modal)</small>
                    <b>{{ formatRupiah(summary.surplus_value) }}</b>
                    <em>{{ summary.surplus_items }} barang</em>
                </div>
                <div class="kpi">
                    <small>Bersih</small>
                    <b :class="{ 'is-loss': Number(summary.net_value) < 0 }">{{ formatRupiah(summary.net_value) }}</b>
                    <em>dari {{ summary.opnames_count }} opname yang diposting</em>
                </div>
                <div class="kpi">
                    <small>Kurang yang belum mengurangi laba</small>
                    <b>{{ formatRupiah(summary.unbooked_value) }}</b>
                    <em>selisih kurang selain yang jadi dead stock (sudah mengurangi laba), barang DG, atau kembali ke rak</em>
                </div>
            </div>
            <p v-if="summary.items_without_cost > 0" class="notice">{{ summary.items_without_cost }} barang selisih tidak punya harga beli saat diposting, jadi nilainya kurang dari itu.</p>
        </Deferred>

        <div class="columns">
            <section class="card">
                <h2>Selisih kurang per alasan <small>(nilai modal)</small></h2>
                <Deferred data="reasons">
                    <template #fallback>
                        <q-skeleton height="170px" />
                    </template>

                    <PieChart :slices="shortageSlices" :format-value="formatRupiah" label="Selisih kurang per alasan" empty-text="Tidak ada selisih kurang pada periode ini." />
                </Deferred>
            </section>

            <section class="card">
                <h2>Barang dengan selisih kurang terbesar <small>(nilai modal)</small></h2>
                <Deferred data="products">
                    <template #fallback>
                        <q-skeleton height="170px" />
                    </template>

                    <RankedBars :rows="productRows" :format-value="formatRupiah" empty-text="Tidak ada selisih kurang pada periode ini." />
                </Deferred>
            </section>
        </div>

        <section class="card">
            <h2>Per alasan</h2>
            <Deferred data="reasons">
                <template #fallback>
                    <q-skeleton height="120px" />
                </template>

                <p v-if="reasons.length === 0" class="empty">Tidak ada selisih pada periode ini.</p>
                <div v-else class="table-scroll">
                    <table class="table">
                        <thead>
                            <tr><th>Alasan</th><th class="table__right">Barang</th><th class="table__right">Kurang</th><th class="table__right">Lebih</th><th class="table__right">Bersih</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="reason in reasons" :key="reason.label">
                                <td>
                                    {{ reason.label }}
                                    <small v-if="decisionNote(reason)" class="table__muted">{{ decisionNote(reason).replace(/\n/g, ' · ') }}</small>
                                </td>
                                <td class="table__right">{{ reason.items_count }}</td>
                                <td class="table__right">{{ formatRupiah(reason.shortage_value) }}</td>
                                <td class="table__right">{{ formatRupiah(reason.surplus_value) }}</td>
                                <td class="table__right" :class="{ 'is-loss': Number(reason.net_value) < 0 }"><b>{{ formatRupiah(reason.net_value) }}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Deferred>
            <p class="note">Nilai memakai harga beli saat opname diposting. Satuan antar-barang tidak dijumlahkan, hanya nilainya. Hanya opname yang menghitung isi rak yang masuk; stok awal, penambahan stok, dan pemeriksaan retur bukan selisih.</p>
        </section>

        <section class="card">
            <div class="card__head">
                <h2>Opname yang diposting</h2>
                <span v-if="opnames" class="card__sum">{{ opnames.total }} opname</span>
            </div>
            <Deferred data="opnames">
                <template #fallback>
                    <q-skeleton height="200px" />
                </template>

                <template #default="{ reloading }">
                    <p v-if="opnames.total === 0" class="empty">Tidak ada opname yang diposting pada periode ini.</p>
                    <div v-else :class="{ 'is-reloading': reloading }">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr><th>Opname</th><th>Diposting</th><th>Gudang</th><th>Jenis</th><th class="table__right">Barang selisih</th><th class="table__right">Kurang</th><th class="table__right">Lebih</th><th class="table__right">Bersih</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="opname in opnames.data" :key="opname.id">
                                        <td><Link v-if="canOpenCounts" :href="opname.url" class="table__link">{{ opname.number }}</Link><b v-else>{{ opname.number }}</b></td>
                                        <td class="table__date">{{ shortDayFormat.format(asLocalDate(opname.posted_on)) }}</td>
                                        <td>{{ opname.warehouse }}</td>
                                        <td>{{ opname.reason }}</td>
                                        <td class="table__right">{{ opname.items_count === 0 ? 'Tanpa selisih' : opname.items_count }}</td>
                                        <td class="table__right">{{ formatRupiah(opname.shortage_value) }}</td>
                                        <td class="table__right">{{ formatRupiah(opname.surplus_value) }}</td>
                                        <td class="table__right" :class="{ 'is-loss': Number(opname.net_value) < 0 }"><b>{{ formatRupiah(opname.net_value) }}</b></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pager">
                            <span class="pager__range">{{ opnames.from }}–{{ opnames.to }} dari {{ opnames.total }} opname</span>
                            <div class="pager__controls">
                                <q-select
                                    :model-value="opnames.per_page"
                                    :options="per_page_options"
                                    outlined
                                    dense
                                    behavior="menu"
                                    :option-label="(option) => `${option} per halaman`"
                                    class="pager__per-page"
                                    aria-label="Jumlah opname per halaman"
                                    @update:model-value="changeOpnamesPerPage"
                                />
                                <q-pagination
                                    v-if="opnames.last_page > 1"
                                    :model-value="opnames.current_page"
                                    :max="opnames.last_page"
                                    :max-pages="7"
                                    direction-links
                                    boundary-links
                                    color="primary"
                                    @update:model-value="showOpnamesPage"
                                />
                            </div>
                        </div>
                    </div>
                </template>
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

.notice {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fff8e6;
    font-size: 13px;
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

.is-loss {
    color: var(--q-negative);
}

.columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 16px;
    align-items: start;
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

.card__sum {
    color: var(--app-ink-soft);
    font-size: 13px;
    font-weight: 600;
}

.is-reloading {
    opacity: 0.5;
    transition: opacity 0.15s;
}

.table-scroll {
    overflow-x: auto;
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
    vertical-align: top;
}

.table td small {
    display: block;
}

.table .table__right {
    text-align: right;
    white-space: nowrap;
}

.table .table__date {
    white-space: nowrap;
}

.table .table__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 14px;
}

.pager__controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.pager__per-page {
    min-width: 150px;
}

.pager__range {
    color: var(--app-mute);
    font-size: 12px;
}

.empty {
    margin: 0;
    color: var(--app-mute);
}

.note {
    margin: 12px 0 0;
    color: var(--app-mute);
    font-size: 12px;
}
</style>
