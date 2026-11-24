<script setup>
import { computed } from 'vue';
import { Deferred, Link, router, usePage } from '@inertiajs/vue3';
import MeterBar from '../../../Components/MeterBar.vue';
import PieChart from '../../../Components/PieChart.vue';
import RankedBars from '../../../Components/RankedBars.vue';
import SegmentedBar from '../../../Components/SegmentedBar.vue';
import StockReportTabs from '../../../Components/StockReportTabs.vue';
import { OTHER_COLOR, SERIES_COLORS } from '../../../Composables/chartColors';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `summary`, `groups`, `best_sellers`, `idle`, `restock` and `urgent` are deferred props, each in a group of its own: the
 * page shows at once and each part fills in when the server has it, so they are undefined until then and only read inside
 * a `<Deferred>` that waits for them.
 */
const props = defineProps({
    summary: { type: Object, default: undefined },
    groups: { type: Object, default: undefined },
    best_sellers: { type: Object, default: undefined },
    idle: { type: Object, default: undefined },
    restock: { type: Object, default: undefined },
    urgent: { type: Array, default: undefined },
    /** The days without a sale that make stock idle here: the default of the tab "Tidak terjual". */
    idle_days: { type: Number, required: true },
    /** The days of sales that set the pace of the restock figures: the default of the tab "Perlu restok". */
    restock_window: { type: Number, required: true },
    /** The days the stock should last in the restock figures: the default of the tab "Perlu restok". */
    restock_cover: { type: Number, required: true },
});

const groupingOptions = [
    { label: 'Kategori', value: 'category' },
    { label: 'Merek', value: 'brand' },
];

const metricOptions = [
    { label: 'Nilai', value: 'value' },
    { label: 'Jumlah stok', value: 'quantity' },
];

const page = usePage();

/** The status colours are fixed (never themed); the label inside a block is white, except near-black on the warning yellow. */
const STATUS_COLORS = { good: '#0ca30c', warning: '#fab219', critical: '#d03b3b' };
const INK_ON_WARNING = '#0b0b0b';

/** The table of positions, narrowed to a stock condition. */
const positionsWith = (status) => `${page.props.store.stock_positions_url}?status=${status}`;

const formatProducts = (count) => `${count} barang`;
const formatDays = (days) => (Number(days) === 0 ? 'Habis' : `${Number(days).toLocaleString('id-ID')} hari`);

const valueSlices = computed(() => {
    if (!props.summary) {
        return [];
    }

    const { dead_stock: deadStock, returns_waiting: returnsWaiting } = props.summary;

    return [
        { key: 'sellable', label: 'Stok jual', value: Number(props.summary.sellable_value), color: SERIES_COLORS[0] },
        { key: 'dead', label: 'Dead stock', value: Number(deadStock.value), color: SERIES_COLORS[1], note: `${deadStock.products_count} barang, tidak bisa dijual` },
        { key: 'returns', label: 'Retur menunggu diperiksa', value: Number(returnsWaiting.value), color: SERIES_COLORS[2], note: `${returnsWaiting.products_count} barang, belum diputuskan` },
    ];
});

const conditionParts = computed(() => {
    if (!props.summary) {
        return [];
    }

    return [
        { key: 'healthy', label: 'Sehat', value: props.summary.healthy_count, color: STATUS_COLORS.good, note: 'di atas minimum atau tanpa minimum' },
        { key: 'low', label: 'Menipis', value: props.summary.low_stock_count, color: STATUS_COLORS.warning, textColor: INK_ON_WARNING, note: 'di bawah minimum, belum habis' },
        { key: 'out', label: 'Habis', value: props.summary.out_of_stock_count, color: STATUS_COLORS.critical, note: 'stok jual 0' },
    ];
});

const isRankedByQuantity = computed(() => props.groups?.metric === 'quantity');

/** Writes an amount of stock in a base unit, as 1.250 kg. */
const formatInUnit = (quantity, unit) => `${formatQuantity(quantity)} ${unit}`;

/** Writes a group's figure as the pie is ranked by it. */
const formatGroupMeasure = (measure) => (isRankedByQuantity.value ? formatInUnit(measure, props.groups.unit) : formatRupiah(measure));

/**
 * The biggest categories (or brands) by what the pie is ranked by, the rest folded into one part so the pie keeps to a
 * handful of parts it can be read in. The rest is what the server says all of them come to, less the ones shown. A quantity
 * is only that of the products held in the chosen unit. Each part names its worth under its name when ranked by quantity.
 */
const groupSlices = computed(() => {
    if (!props.groups) {
        return [];
    }

    const measureOf = (group) => Number(isRankedByQuantity.value ? group.in_stock_quantity : group.value);
    const noteOf = (group) => (isRankedByQuantity.value ? formatRupiah(group.value) : undefined);
    const total = Number(isRankedByQuantity.value ? props.groups.quantity_total : props.groups.value_total);

    const shown = props.groups.data.slice(0, SERIES_COLORS.length).map((group, index) => ({ key: group.id ?? 'none', label: group.name, value: measureOf(group), color: SERIES_COLORS[index], note: noteOf(group) }));
    const rest = total - shown.reduce((sum, slice) => sum + slice.value, 0);

    return rest > 0.005 ? [...shown, { key: 'rest', label: 'Lainnya', value: rest, color: OTHER_COLOR }] : shown;
});

const urgentRows = computed(() => (props.urgent ?? []).map((product) => ({ key: product.id, label: product.name, value: Number(product.days_left) })));

const bestSellerRows = computed(() => (props.best_sellers?.data ?? []).map((product) => ({ key: product.id, label: product.name, value: Number(product.sold_quantity) })));

/** Whether the sales on record are fewer days than the idle limit, so a product may only not have had the time to sell. */
const isHistoryShorterThanLimit = computed(() => props.idle?.history_days != null && props.idle.history_days < props.idle_days);

/**
 * What the screen is showing, as the address holds it, so asking for one part does not undo the choices of the other and the
 * page comes back as it was when it is reloaded. Only what has arrived is there.
 * @param {Record<string, string|undefined>} change What of the query changes.
 */
function currentQuery(change) {
    return { groups_by: props.groups?.grouping, groups_metric: props.groups?.metric, groups_unit: props.groups?.unit, sellers_unit: props.best_sellers?.unit, ...change };
}

/**
 * Asks the server for one part only, so the rest of the report is not worked out again.
 * @param {'groups'|'best_sellers'} part
 * @param {Record<string, string|undefined>} change
 */
const reloadPart = (part, change) => router.get(page.url.split('?')[0], currentQuery(change), { only: [part], preserveState: true, replace: true, preserveScroll: true });

const changeGrouping = (grouping) => reloadPart('groups', { groups_by: grouping });
const changeMetric = (metric) => reloadPart('groups', { groups_metric: metric });
const changeGroupsUnit = (unit) => reloadPart('groups', { groups_unit: unit });
const changeBestSellersUnit = (unit) => reloadPart('best_sellers', { sellers_unit: unit });
</script>

<template>
    <StoreLayout title="Laporan Stok">
        <StockReportTabs current="summary" />

        <Deferred data="summary">
            <template #fallback>
                <div class="kpis">
                    <q-skeleton v-for="placeholder in 3" :key="placeholder" height="86px" />
                </div>
            </template>

            <div class="kpis">
                <div class="kpi">
                    <small>Nilai stok jual (estimasi)</small>
                    <b>{{ formatRupiah(summary.sellable_value) }}</b>
                    <em>{{ summary.products_count }} barang aktif · stok × harga beli terakhir</em>
                    <em v-if="summary.products_without_cost > 0" class="kpi__warn">{{ summary.products_without_cost }} barang berstok belum punya harga beli, jadi tidak ikut dihitung</em>
                </div>
                <div class="kpi">
                    <small>Estimasi biaya restok</small>
                    <Deferred data="restock">
                        <template #fallback><q-skeleton type="text" width="70%" height="30px" /></template>
                        <b>{{ formatRupiah(restock.purchase_cost) }}</b>
                        <em>{{ restock.products_count }} barang perlu dibeli · <Link :href="page.props.store.restock_url">lihat daftar</Link></em>
                        <em v-if="restock.products_without_cost > 0" class="kpi__warn">{{ restock.products_without_cost }} barang belum punya harga beli, jadi biayanya kurang dari itu</em>
                    </Deferred>
                </div>
                <div v-if="summary.negative_count > 0" class="kpi kpi--alert">
                    <small>Stok negatif</small>
                    <b>{{ summary.negative_count }}</b>
                    <em>barang, perlu dicek lewat opname · <Link :href="positionsWith('negative')">lihat barangnya</Link></em>
                </div>
            </div>
        </Deferred>

        <div class="columns">
            <section class="card">
                <h2>Uang tertanam di stok <small>(nilai modal, estimasi)</small></h2>
                <Deferred data="summary">
                    <template #fallback>
                        <q-skeleton height="170px" />
                    </template>

                    <PieChart :slices="valueSlices" :format-value="formatRupiah" label="Nilai stok menurut kondisi" empty-text="Belum ada stok." />
                    <p class="note">Dead stock dan retur yang menunggu diperiksa sudah tidak bisa dijual, jadi uangnya belum kembali lewat penjualan.</p>
                </Deferred>
            </section>

            <section class="card">
                <h2>Kondisi barang aktif</h2>
                <Deferred data="summary">
                    <template #fallback>
                        <q-skeleton height="130px" />
                    </template>

                    <SegmentedBar :parts="conditionParts" :format-value="formatProducts" label="Kondisi stok barang aktif" empty-text="Belum ada barang aktif." />
                    <p class="note">
                        Lihat barang yang <Link :href="positionsWith('below_minimum')">di bawah minimum</Link> atau yang <Link :href="positionsWith('out_of_stock')">habis</Link>.
                        <template v-if="summary.negative_count > 0">Stok negatif ({{ summary.negative_count }} barang) dihitung terpisah, di atas.</template>
                    </p>
                </Deferred>
            </section>
        </div>

        <div class="columns">
            <section class="card">
                <div class="card__head">
                    <h2>Stok jual per {{ groups?.grouping === 'brand' ? 'merek' : 'kategori' }} <small>({{ isRankedByQuantity ? `jumlah, dalam ${groups.unit ?? 'satuan dasar'}` : 'nilai modal, estimasi' }})</small></h2>
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
                <div class="card__options">
                    <q-btn-toggle
                        :model-value="groups?.metric ?? 'value'"
                        :options="metricOptions"
                        no-caps
                        unelevated
                        dense
                        toggle-color="primary"
                        color="grey-3"
                        text-color="grey-8"
                        @update:model-value="changeMetric"
                    />
                    <q-select
                        v-if="isRankedByQuantity && groups.units.length > 0"
                        :model-value="groups.unit"
                        :options="groups.units"
                        outlined
                        dense
                        behavior="menu"
                        label="Satuan"
                        class="card__unit"
                        aria-label="Satuan dasar yang dijumlahkan"
                        @update:model-value="changeGroupsUnit"
                    />
                </div>
                <Deferred data="groups">
                    <template #fallback>
                        <q-skeleton height="170px" />
                    </template>

                    <template #default="{ reloading }">
                        <div :class="{ 'is-reloading': reloading }">
                            <PieChart :slices="groupSlices" :format-value="formatGroupMeasure" :label="`${isRankedByQuantity ? 'Jumlah stok jual' : 'Nilai stok jual'} per ${groups.grouping === 'brand' ? 'merek' : 'kategori'}`" empty-text="Belum ada stok jual." />
                            <p v-if="isRankedByQuantity" class="note">Hanya barang bersatuan dasar {{ groups.unit }} yang dihitung, karena jumlah dengan satuan berbeda (kg, sak, pcs) tidak bisa dijumlahkan. Ganti satuan di atas untuk melihat yang lain; nilai di bawah nama adalah nilai modal barang-barang itu.</p>
                        </div>
                    </template>
                </Deferred>
            </section>

            <section class="card">
                <h2>Stok mengendap <small>(tidak terjual {{ idle_days }} hari)</small></h2>
                <Deferred data="idle">
                    <template #fallback>
                        <q-skeleton height="130px" />
                    </template>

                    <p class="meter-figure"><b>{{ idle.share_percent }}%</b> dari nilai stok jual</p>
                    <MeterBar :percent="idle.share_percent" label="Porsi nilai stok jual yang tidak terjual" />
                    <dl class="rows">
                        <div><dt>Nilai yang mengendap</dt><dd>{{ formatRupiah(idle.stock_value) }}</dd></div>
                        <div><dt>Barang</dt><dd>{{ idle.products_count }}<small v-if="idle.never_sold_count > 0"> · {{ idle.never_sold_count }} belum pernah terjual</small></dd></div>
                    </dl>
                    <p v-if="isHistoryShorterThanLimit" class="note note--warn">Penjualan baru tercatat {{ idle.history_days }} hari, jadi sebagian barang mungkin hanya belum sempat terjual.</p>
                    <p v-if="idle.products_without_cost > 0" class="note note--warn">{{ idle.products_without_cost }} barang belum punya harga beli, jadi nilainya kurang dari itu.</p>
                    <p class="note">Memakai batas {{ idle_days }} hari tanpa penjualan. <Link :href="page.props.store.idle_stock_url">Lihat daftar dan ubah batasnya</Link></p>
                </Deferred>
            </section>
        </div>

        <div class="columns">
            <section class="card">
                <div class="card__head">
                    <h2>Paling cepat habis <small>(5 teratas, terjual {{ restock_window }} hari terakhir)</small></h2>
                    <q-select
                        v-if="best_sellers && best_sellers.units.length > 0"
                        :model-value="best_sellers.unit"
                        :options="best_sellers.units"
                        outlined
                        dense
                        behavior="menu"
                        label="Satuan"
                        class="card__unit"
                        aria-label="Satuan dasar yang dibandingkan"
                        @update:model-value="changeBestSellersUnit"
                    />
                </div>
                <Deferred data="best_sellers">
                    <template #fallback>
                        <q-skeleton height="150px" />
                    </template>

                    <template #default="{ reloading }">
                        <div :class="{ 'is-reloading': reloading }">
                            <RankedBars :rows="bestSellerRows" :format-value="(quantity) => formatInUnit(quantity, best_sellers.unit)" empty-text="Belum ada barang terjual pada satuan ini." />
                            <p class="note">Barang yang paling banyak terjual, dalam satuan dasarnya; hanya barang bersatuan {{ best_sellers.unit ?? 'dasar' }} yang dibandingkan karena satuan berbeda tidak bisa dijumlahkan. Retur belum dikurangkan. Memakai penjualan {{ restock_window }} hari terakhir, seperti tab Perlu restok.</p>
                        </div>
                    </template>
                </Deferred>
            </section>

            <section class="card">
                <div class="card__head">
                    <h2>Perkiraan stok habis <small>(5 teratas, sisa hari stok)</small></h2>
                    <Link :href="page.props.store.restock_url" class="card__more">Lihat semua yang perlu direstok</Link>
                </div>
                <Deferred data="urgent">
                    <template #fallback>
                        <q-skeleton height="150px" />
                    </template>

                    <RankedBars :rows="urgentRows" :format-value="formatDays" empty-text="Tidak ada barang laku yang perlu direstok." />
                    <p class="note">Dihitung dari penjualan {{ restock_window }} hari terakhir, dengan stok dianggap cukup untuk {{ restock_cover }} hari. Barang yang tidak laku tidak ikut. <Link :href="page.props.store.restock_url">Ubah di tab Perlu restok</Link></p>
                </Deferred>
            </section>
        </div>
    </StoreLayout>
</template>

<style scoped>
.kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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

.kpi .kpi__warn {
    margin-top: 4px;
    color: #b45309;
}

.kpi--alert b {
    color: var(--q-negative);
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

/** The measure's own row under the heading, so the heading and the grouping toggle do not shift when the heading's small print changes length. */
.card__options {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.card__unit {
    min-width: 110px;
}

.card__more {
    font-size: 13px;
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

.meter-figure {
    margin: 0 0 8px;
    color: var(--app-ink-soft);
}

.meter-figure b {
    color: var(--app-ink);
    font-size: 26px;
}

.rows {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 14px 0 0;
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
    font-weight: 400;
}

.note {
    margin: 10px 0 0;
    color: var(--app-mute);
    font-size: 12px;
}

.note--warn {
    color: #b45309;
}
</style>
