<script setup>
import { ref, watch } from 'vue';
import { Deferred, router, usePage } from '@inertiajs/vue3';
import SearchableSelect from '../../../Components/SearchableSelect.vue';
import StockReportTabs from '../../../Components/StockReportTabs.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity, formatStock } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `summary`, `products` and `idle_out_of_stock` are deferred props, each in a group of its own: the page shows at once and
 * each part fills in when the server has it, so they are undefined until then and only read inside a `<Deferred>` that
 * waits for them.
 */
const props = defineProps({
    /** What the report is worked out with and narrowed to, as the address holds it. */
    filters: { type: Object, required: true },
    summary: { type: Object, default: undefined },
    products: { type: Object, default: undefined },
    /** The products that are out of stock and did not sell, which only their minimum asks to buy: a short list apart from the table. */
    idle_out_of_stock: { type: Object, default: undefined },
    /** How many days of sales the pace can be read from. */
    window_options: { type: Array, required: true },
    /** How many days the stock can be wanted to last. */
    cover_options: { type: Array, required: true },
    categories: { type: Array, required: true },
    /** The page sizes the table offers. */
    per_page_options: { type: Array, required: true },
});

const page = usePage();

const salesDays = ref(props.filters.window);
const coverDays = ref(props.filters.cover);
const category = ref(props.filters.category);

const byDays = (options) => [...options].sort((first, second) => first - second).map((option) => ({ label: `${option} hari`, value: option }));
const windowOptions = byDays(props.window_options);
const coverOptions = byDays(props.cover_options);

/**
 * What the screen is showing, as the address holds it, so the page comes back as it was when it is reloaded.
 * @param {Record<string, string|number|undefined>} change What of the query changes.
 */
function currentQuery(change = {}) {
    return {
        window: salesDays.value,
        cover: coverDays.value,
        category: category.value || undefined,
        page: props.products?.current_page,
        per_page: props.products?.per_page,
        idle_page: props.idle_out_of_stock?.current_page,
        sort: props.products?.sort,
        direction: props.products?.direction,
        ...change,
    };
}

/**
 * Asks the server for the named parts only, so the rest of the report is not worked out again.
 * @param {string[]} only
 * @param {Record<string, string|number|undefined>} change
 */
function reloadParts(only, change) {
    router.get(page.url.split('?')[0], currentQuery(change), { only, preserveState: true, replace: true, preserveScroll: true });
}

/** The pace and the days wanted change who has to be bought, so the cards and the table both ask again; `filters` as well so the address and the fields agree. */
watch([salesDays, coverDays], () => reloadParts(['summary', 'products', 'idle_out_of_stock', 'filters'], { page: undefined, idle_page: undefined }));

watch(category, () => reloadParts(['products', 'idle_out_of_stock', 'filters'], { page: undefined, idle_page: undefined }));

/**
 * Ordering by the column the table is already in turns its direction; a new column starts with the most urgent first
 * (or names A to Z) and figures biggest first. The order is the server's, so it holds across pages, and the table starts
 * from its first page.
 * @param {string} column
 */
function sortBy(column) {
    const isSameColumn = props.products?.sort === column;
    const startsAscending = column === 'name' || column === 'days_left';
    const direction = isSameColumn ? (props.products.direction === 'desc' ? 'asc' : 'desc') : startsAscending ? 'asc' : 'desc';

    reloadParts(['products'], { page: 1, sort: column, direction });
}

const sortIcon = (column) => (props.products?.sort === column ? (props.products.direction === 'desc' ? 'arrow_downward' : 'arrow_upward') : 'unfold_more');
const sortState = (column) => (props.products?.sort === column ? (props.products.direction === 'desc' ? 'descending' : 'ascending') : 'none');

const showPage = (productsPage) => reloadParts(['products'], { page: productsPage });
const changePerPage = (perPage) => reloadParts(['products'], { page: 1, per_page: perPage });
const showIdlePage = (idlePage) => reloadParts(['idle_out_of_stock'], { idle_page: idlePage });

/**
 * How long the stock lasts, for a reader: out of stock, no way to tell (the product did not sell in the window), or the
 * days.
 * @param {string|null} daysLeft
 */
function describeDaysLeft(daysLeft) {
    if (daysLeft === null) {
        return '—';
    }

    return Number(daysLeft) === 0 ? 'Habis' : `${formatQuantity(daysLeft)} hari`;
}

/** The status colours are fixed (never themed), the same as in the stock summary. */
const DAYS_COLORS = { good: '#0ca30c', warning: '#fab219', critical: '#d03b3b' };

/**
 * The bar under "Cukup untuk": how much of the days wanted the stock lasts. Full and green when it lasts as long as wanted,
 * yellow under that, red under half of it. Nothing for a product that did not sell (no way to tell), and nothing to fill when
 * the stock is out.
 * @param {string|null} daysLeft
 * @returns {{width: string, color: string}|null}
 */
function daysBar(daysLeft) {
    if (daysLeft === null) {
        return null;
    }

    const share = Math.min(Number(daysLeft) / props.filters.cover, 1);
    const color = share >= 1 ? DAYS_COLORS.good : share >= 0.5 ? DAYS_COLORS.warning : DAYS_COLORS.critical;

    return { width: `${Math.round(share * 100)}%`, color };
}

/**
 * Whether a product to buy has no purchase price, so the cost of buying it counts for nothing.
 * @param {{cost_price: string}} product
 */
const isWithoutCost = (product) => Number(product.cost_price) === 0;
</script>

<template>
    <StoreLayout title="Laporan Stok">
        <StockReportTabs current="restock" />

        <div class="settings">
            <div class="settings__field">
                <span class="settings__label">Rata-rata terjual per hari dihitung dari penjualan</span>
                <q-btn-toggle v-model="salesDays" :options="windowOptions" no-caps unelevated dense toggle-color="primary" color="grey-3" text-color="grey-8" />
            </div>
            <div class="settings__field">
                <span class="settings__label">Stok harus cukup untuk</span>
                <q-btn-toggle v-model="coverDays" :options="coverOptions" no-caps unelevated dense toggle-color="primary" color="grey-3" text-color="grey-8" />
            </div>
        </div>

        <Deferred data="summary">
            <template #fallback>
                <div class="kpis">
                    <q-skeleton v-for="placeholder in 4" :key="placeholder" height="86px" />
                </div>
            </template>

            <div class="kpis">
                <div class="kpi">
                    <small>Perlu dibeli</small>
                    <b>{{ summary.products_count }}</b>
                    <em>barang aktif yang stoknya tidak akan cukup</em>
                </div>
                <div class="kpi">
                    <small>Perkiraan biaya beli</small>
                    <b>{{ formatRupiah(summary.purchase_cost) }}</b>
                    <em>jumlah saran × harga beli terakhir</em>
                    <em v-if="summary.products_without_cost > 0" class="kpi__warn">{{ summary.products_without_cost }} barang belum punya harga beli, jadi tidak ikut dihitung</em>
                </div>
                <div class="kpi" :class="{ 'kpi--alert': summary.out_of_stock_selling_count > 0 }">
                    <small>Habis padahal laku</small>
                    <b>{{ summary.out_of_stock_selling_count }}</b>
                    <em>barang stok 0 yang terjual dalam {{ filters.window }} hari terakhir</em>
                </div>
                <div class="kpi">
                    <small>Di bawah minimum</small>
                    <b>{{ summary.below_minimum_count }}</b>
                    <em>barang, termasuk yang habis</em>
                </div>
            </div>
        </Deferred>

        <section class="card">
            <h2>Saran pembelian</h2>

            <div class="filters">
                <SearchableSelect v-model="category" :options="[{ id: null, name: 'Semua kategori' }, ...categories]" dense class="filters__select" aria-label="Kategori" />
            </div>

            <Deferred data="products">
                <template #fallback>
                    <q-skeleton height="300px" />
                </template>

                <template #default="{ reloading }">
                    <div :class="{ 'is-reloading': reloading }">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th :aria-sort="sortState('name')">
                                            <button type="button" class="table__sort" @click="sortBy('name')">Barang<q-icon :name="sortIcon('name')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('sellable_stock')">
                                            <button type="button" class="table__sort" @click="sortBy('sellable_stock')">Stok jual<q-icon :name="sortIcon('sellable_stock')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('sold_quantity')">
                                            <button type="button" class="table__sort" @click="sortBy('sold_quantity')">Terjual {{ filters.window }} hari<q-icon :name="sortIcon('sold_quantity')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('days_left')">
                                            <button type="button" class="table__sort" @click="sortBy('days_left')">Cukup untuk<q-icon :name="sortIcon('days_left')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('suggested_quantity')">
                                            <button type="button" class="table__sort" @click="sortBy('suggested_quantity')">Saran beli<q-icon :name="sortIcon('suggested_quantity')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('suggested_cost')">
                                            <button type="button" class="table__sort" @click="sortBy('suggested_cost')">Perkiraan biaya<q-icon :name="sortIcon('suggested_cost')" size="14px" /></button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="products.data.length === 0">
                                        <td colspan="6" class="table__empty">Tidak ada barang yang perlu dibeli.</td>
                                    </tr>
                                    <tr v-for="product in products.data" :key="product.id">
                                        <td>
                                            <b>
                                                {{ product.name }}
                                                <span v-if="Number(product.sellable_stock) <= 0" class="chip chip--bad table__chip">Habis</span>
                                                <span v-else-if="product.is_below_minimum" class="chip chip--warn table__chip">Di bawah minimum</span>
                                            </b>
                                            <small class="table__muted">{{ [product.sku, product.category, product.brand].filter(Boolean).join(' · ') }}</small>
                                        </td>
                                        <td class="table__right">
                                            {{ formatStock(product.sellable_stock, product.units, product.base_unit, null) }}
                                            <small v-if="Number(product.min_stock) > 0" class="table__muted">min {{ formatQuantity(product.min_stock) }}</small>
                                        </td>
                                        <td class="table__right">
                                            <template v-if="Number(product.sold_quantity) > 0">
                                                {{ formatQuantity(product.sold_quantity) }} {{ product.base_unit }}
                                                <small class="table__muted">±{{ formatQuantity(Math.round((Number(product.sold_quantity) / filters.window) * 100) / 100) }} per hari</small>
                                            </template>
                                            <span v-else class="table__muted">tidak terjual</span>
                                        </td>
                                        <td class="table__right">
                                            {{ describeDaysLeft(product.days_left) }}
                                            <span v-if="daysBar(product.days_left)" class="days-bar" aria-hidden="true">
                                                <span class="days-bar__fill" :style="{ width: daysBar(product.days_left).width, background: daysBar(product.days_left).color }"></span>
                                            </span>
                                        </td>
                                        <td class="table__right"><b>{{ formatStock(product.suggested_quantity, product.units, product.base_unit, null) }}</b></td>
                                        <td class="table__right">
                                            {{ formatRupiah(product.suggested_cost) }}
                                            <small v-if="isWithoutCost(product)" class="table__warn">belum ada harga beli</small>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="products.total > 0" class="pager">
                            <span class="pager__range">{{ products.from }}–{{ products.to }} dari {{ products.total }} barang</span>
                            <div class="pager__controls">
                                <q-select
                                    :model-value="products.per_page"
                                    :options="per_page_options"
                                    outlined
                                    dense
                                    behavior="menu"
                                    :option-label="(option) => `${option} per halaman`"
                                    class="pager__per-page"
                                    aria-label="Jumlah barang per halaman"
                                    @update:model-value="changePerPage"
                                />
                                <q-pagination
                                    v-if="products.last_page > 1"
                                    :model-value="products.current_page"
                                    :max="products.last_page"
                                    :max-pages="7"
                                    direction-links
                                    boundary-links
                                    color="primary"
                                    @update:model-value="showPage"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Deferred>
            <ul class="note">
                <li><b>Terjual {{ filters.window }} hari</b>: jumlah yang terjual dalam {{ filters.window }} hari terakhir. Dibagi {{ filters.window }}, itulah rata-rata terjual per hari.</li>
                <li><b>Cukup untuk</b>: stok sekarang dibagi rata-rata terjual per hari. Contoh: stok 5, terjual 1 per hari, cukup untuk 5 hari. Batangnya penuh bila stok cukup selama {{ filters.cover }} hari yang diminta: hijau bila penuh, kuning di bawahnya, merah di bawah setengahnya.</li>
                <li><b>Saran beli</b>: yang perlu dibeli supaya stok cukup untuk {{ filters.cover }} hari, dan tidak kurang dari stok minimum barang.</li>
                <li>Tidak ikut: barang rusak (DG) dan barang nonaktif. Barang yang tidak terjual hanya muncul bila stoknya di bawah minimum. Kalau stoknya juga sudah habis, ia pindah ke daftar di bawah.</li>
            </ul>
        </section>

        <section class="card">
            <h2>Habis dan tidak terjual dalam {{ filters.window }} hari terakhir <small class="card__hint">hanya muncul karena stok minimum</small></h2>

            <Deferred data="idle_out_of_stock">
                <template #fallback>
                    <q-skeleton height="120px" />
                </template>

                <template #default="{ reloading }">
                    <div :class="{ 'is-reloading': reloading }">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Barang</th>
                                        <th class="table__right">Stok minimum</th>
                                        <th class="table__right">Saran beli</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="idle_out_of_stock.data.length === 0">
                                        <td colspan="3" class="table__empty">Tidak ada barang yang habis dan tidak terjual.</td>
                                    </tr>
                                    <tr v-for="product in idle_out_of_stock.data" :key="product.id">
                                        <td>
                                            <b>{{ product.name }}</b>
                                            <small class="table__muted">{{ [product.sku, product.category, product.brand].filter(Boolean).join(' · ') }}</small>
                                        </td>
                                        <td class="table__right">{{ formatStock(product.min_stock, product.units, product.base_unit, null) }}</td>
                                        <td class="table__right">{{ formatStock(product.suggested_quantity, product.units, product.base_unit, null) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="idle_out_of_stock.total > 0" class="pager">
                            <span class="pager__range">{{ idle_out_of_stock.from }}–{{ idle_out_of_stock.to }} dari {{ idle_out_of_stock.total }} barang</span>
                            <q-pagination
                                v-if="idle_out_of_stock.last_page > 1"
                                :model-value="idle_out_of_stock.current_page"
                                :max="idle_out_of_stock.last_page"
                                :max-pages="7"
                                direction-links
                                boundary-links
                                color="primary"
                                @update:model-value="showIdlePage"
                            />
                        </div>
                    </div>
                </template>
            </Deferred>
            <p class="note note--plain">Tidak ikut hitungan biaya dan jumlah di atas. Kalau barangnya tidak akan dijual lagi, nonaktifkan atau kosongkan stok minimumnya.</p>
        </section>
    </StoreLayout>
</template>

<style scoped>
.settings {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 28px;
    margin-bottom: 16px;
}

.settings__field {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 10px;
}

.settings__label {
    color: var(--app-mute);
    font-size: 13px;
}

.kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
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

.kpi--alert b {
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

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.filters__select {
    flex: 1 1 200px;
    max-width: 320px;
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

.table__sort {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 0;
    border: 0;
    background: none;
    color: inherit;
    cursor: pointer;
    font: inherit;
    white-space: nowrap;
}

.table__sort:hover {
    color: var(--app-ink);
}

.table td {
    padding: 8px 6px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.table td b,
.table td small {
    display: block;
}

.table .table__right {
    text-align: right;
    white-space: nowrap;
}

.table td b .table__chip {
    display: inline-block;
    margin-left: 8px;
}

.table .table__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.table .table__warn {
    color: #b45309;
    font-size: 12px;
}

.table .table__empty {
    padding: 28px 6px;
    color: var(--app-mute);
    text-align: center;
}

.days-bar {
    display: block;
    width: 90px;
    height: 6px;
    margin: 4px 0 0 auto;
    overflow: hidden;
    border-radius: 3px;
    background: var(--app-line);
}

.days-bar__fill {
    display: block;
    height: 100%;
    border-radius: 3px;
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

.is-reloading {
    opacity: 0.5;
    transition: opacity 0.15s;
}

.note {
    margin: 12px 0 0;
    padding-left: 18px;
    color: var(--app-mute);
    font-size: 12px;
}

.note--plain {
    padding-left: 0;
}

.card__hint {
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 400;
}

.note li + li {
    margin-top: 4px;
}
</style>
