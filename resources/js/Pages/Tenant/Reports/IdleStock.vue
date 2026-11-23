<script setup>
import { computed, ref, watch } from 'vue';
import { Deferred, router, usePage } from '@inertiajs/vue3';
import SearchableSelect from '../../../Components/SearchableSelect.vue';
import StockReportTabs from '../../../Components/StockReportTabs.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatStock } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `summary` and `products` are deferred props, each in a group of its own: the page shows at once and each part fills
 * in when the server has it, so they are undefined until then and only read inside a `<Deferred>` that waits for them.
 */
const props = defineProps({
    /** What the report is narrowed to, as the address holds it. */
    filters: { type: Object, required: true },
    summary: { type: Object, default: undefined },
    products: { type: Object, default: undefined },
    /** How many days without a sale the report can call idle. */
    days_options: { type: Array, required: true },
    categories: { type: Array, required: true },
    /** The page sizes the table offers. */
    per_page_options: { type: Array, required: true },
});

const page = usePage();

const days = ref(props.filters.days);
const category = ref(props.filters.category);

const daysOptions = props.days_options.map((option) => ({ label: `${option} hari`, value: option }));
const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

/**
 * What the screen is showing, as the address holds it, so the page comes back as it was when it is reloaded.
 * @param {Record<string, string|number|undefined>} change What of the query changes.
 */
function currentQuery(change = {}) {
    return {
        days: days.value,
        category: category.value || undefined,
        page: props.products?.current_page,
        per_page: props.products?.per_page,
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

/** The number of days changes who is idle, so the cards and the table both ask again; `filters` as well so the address and the fields agree. */
watch(days, () => reloadParts(['summary', 'products', 'filters'], { page: undefined }));

watch(category, () => reloadParts(['products', 'filters'], { page: undefined }));

const showPage = (productsPage) => reloadParts(['products'], { page: productsPage });
const changePerPage = (perPage) => reloadParts(['products'], { page: 1, per_page: perPage });

/** The day of a sale as people read it, from the `Y-m-d` the server sends. */
const formatDay = (isoDay) => dateFormat.format(new Date(`${isoDay}T00:00:00`));

/** Whether the sales on record cover fewer days than the report looks back, so "not sold" may only mean "not sold yet". */
const isHistoryShort = computed(() => props.summary.history_days === null || props.summary.history_days < props.filters.days);

/**
 * Whether a product has no purchase price, so the capital it ties up counts for nothing.
 * @param {{cost_price: string}} product
 */
const isWithoutCost = (product) => Number(product.cost_price) === 0;
</script>

<template>
    <StoreLayout title="Laporan Stok">
        <StockReportTabs current="idle" />

        <div class="period">
            <span class="period__label">Tidak terjual dalam</span>
            <q-btn-toggle v-model="days" :options="daysOptions" no-caps unelevated dense toggle-color="primary" color="grey-3" text-color="grey-8" />
        </div>

        <Deferred data="summary">
            <template #fallback>
                <div class="kpis">
                    <q-skeleton v-for="placeholder in 3" :key="placeholder" height="86px" />
                </div>
            </template>

            <p v-if="isHistoryShort" class="history-notice" role="status">
                <template v-if="summary.first_sale_on">
                    Penjualan baru tercatat sejak {{ formatDay(summary.first_sale_on) }} ({{ summary.history_days }} hari), jadi barang yang tidak terjual {{ filters.days }} hari belum bisa dipastikan.
                </template>
                <template v-else>Belum ada penjualan tercatat, jadi semua barang berstok tampil sebagai belum terjual.</template>
            </p>

            <div class="kpis">
                <div class="kpi">
                    <small>Barang tidak terjual</small>
                    <b>{{ summary.products_count }}</b>
                    <em>barang aktif berstok, tanpa penjualan {{ filters.days }} hari terakhir</em>
                </div>
                <div class="kpi">
                    <small>Modal tertahan (estimasi)</small>
                    <b>{{ formatRupiah(summary.stock_value) }}</b>
                    <em>{{ summary.share_percent }}% dari nilai seluruh stok jual</em>
                    <em v-if="summary.products_without_cost > 0" class="kpi__warn">{{ summary.products_without_cost }} barang belum punya harga beli, jadi tidak ikut dihitung</em>
                </div>
                <div class="kpi">
                    <small>Belum pernah terjual</small>
                    <b>{{ summary.never_sold_count }}</b>
                    <em>barang berstok yang belum ada penjualannya</em>
                </div>
            </div>
        </Deferred>

        <section class="card">
            <h2>Barang yang tidak terjual</h2>

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
                                        <th>Barang</th>
                                        <th class="table__right">Stok jual</th>
                                        <th>Terakhir terjual</th>
                                        <th class="table__right">Harga beli</th>
                                        <th class="table__right">Modal tertahan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="products.data.length === 0">
                                        <td colspan="5" class="table__empty">Tidak ada barang yang tidak terjual.</td>
                                    </tr>
                                    <tr v-for="product in products.data" :key="product.id">
                                        <td>
                                            <b>
                                                {{ product.name }}
                                                <span v-if="product.is_damaged_goods" class="chip chip--warn table__chip">DG</span>
                                            </b>
                                            <small class="table__muted">{{ [product.sku, product.category, product.brand].filter(Boolean).join(' · ') }}</small>
                                        </td>
                                        <td class="table__right">{{ formatStock(product.sellable_stock, product.units, product.base_unit, product.sealed_pack?.intact ?? null) }}</td>
                                        <td>
                                            <template v-if="product.last_sold_on">
                                                {{ formatDay(product.last_sold_on) }}
                                                <small class="table__muted">{{ product.days_since_sold }} hari lalu</small>
                                            </template>
                                            <span v-else class="chip chip--warn">Belum pernah terjual</span>
                                        </td>
                                        <td class="table__right">
                                            {{ formatRupiah(product.cost_price) }}
                                            <small v-if="isWithoutCost(product)" class="table__warn">belum ada harga beli</small>
                                        </td>
                                        <td class="table__right"><b>{{ formatRupiah(product.stock_value) }}</b></td>
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
            <p class="note">Hanya barang aktif yang punya stok jual. Yang dihitung terjual hanya nota selesai; stok opname, nota batal, dan retur tidak mengubahnya. Modal tertahan = stok jual × harga beli terakhir; yang terbesar di atas.</p>
        </section>
    </StoreLayout>
</template>

<style scoped>
.period {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
    margin-bottom: 16px;
}

.period__label {
    color: var(--app-mute);
    font-size: 13px;
}

.history-notice {
    margin: 0 0 12px;
    padding: 10px 14px;
    border: 1px solid #fcd34d;
    border-radius: var(--app-radius);
    background: #fffbeb;
    color: #92400e;
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
    margin: 8px 0 0;
    color: var(--app-mute);
    font-size: 12px;
}
</style>
