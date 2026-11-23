<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Deferred, router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import SearchableSelect from '../../../Components/SearchableSelect.vue';
import StockReportTabs from '../../../Components/StockReportTabs.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `summary` and `products` are deferred props, each in a group of its own: the page shows at once and each part fills
 * in when the server has it, so they are undefined until then and only read inside a `<Deferred>` that waits for them.
 */
const props = defineProps({
    period: { type: Object, required: true },
    /** What the table is narrowed to, as the address holds it. */
    filters: { type: Object, required: true },
    summary: { type: Object, default: undefined },
    products: { type: Object, default: undefined },
    categories: { type: Array, required: true },
    /** The page sizes the table offers. */
    per_page_options: { type: Array, required: true },
    /** Whether the period asked for was longer than the report allows and was cut back from its start. */
    is_period_cut_back: { type: Boolean, required: true },
    max_days: { type: Number, required: true },
});

const page = usePage();

const from = ref(props.period.from);
const to = ref(props.period.to);
const search = ref(props.filters.search);
const category = ref(props.filters.category);

/**
 * What the screen is showing, as the address holds it, so the page comes back as it was when it is reloaded. The
 * period is the one on screen, not a date half typed.
 * @param {Record<string, string|number|undefined>} change What of the query changes.
 */
function currentQuery(change = {}) {
    return {
        from: props.period.from,
        to: props.period.to,
        search: search.value || undefined,
        category: category.value || undefined,
        page: props.products?.current_page,
        per_page: props.products?.per_page,
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

/**
 * Asks for the period typed. Skipped when it is the period on screen already, which is also what the server answers when
 * it swapped the dates or cut the period back and the fields were set to that answer. Both the cards and the table change
 * with the period, and the table starts from its first page.
 */
function applyPeriod() {
    if (from.value && to.value && (from.value !== props.period.from || to.value !== props.period.to)) {
        router.get(page.url.split('?')[0], currentQuery({ from: from.value, to: to.value, page: undefined }), { preserveState: true, replace: true, preserveScroll: true });
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

/** Narrowing the table starts it from its first page; `filters` is asked for as well so the address and the fields agree. */
const applyFilters = () => reloadParts(['products', 'filters'], { page: undefined });

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

watch(category, applyFilters);

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));

/**
 * Ordering by the column the table is already in turns its direction; a new column starts with names A to Z and figures
 * biggest first (for Terjual, the most sold). The order is the server's, so it holds across pages, and the table starts
 * from its first page.
 * @param {string} column
 */
function sortBy(column) {
    const isSameColumn = props.products?.sort === column;
    const direction = isSameColumn ? (props.products.direction === 'desc' ? 'asc' : 'desc') : column === 'name' ? 'asc' : 'desc';

    reloadParts(['products'], { page: 1, sort: column, direction });
}

const sortIcon = (column) => (props.products?.sort === column ? (props.products.direction === 'desc' ? 'arrow_downward' : 'arrow_upward') : 'unfold_more');
const sortState = (column) => (props.products?.sort === column ? (props.products.direction === 'desc' ? 'descending' : 'ascending') : 'none');

const showPage = (productsPage) => reloadParts(['products'], { page: productsPage });
const changePerPage = (perPage) => reloadParts(['products'], { page: 1, per_page: perPage });

/**
 * A change of stock with its sign, so a reader sees which way it went; nothing at all is a dash.
 * @param {string|number} quantity
 * @returns {string} e.g. "+12", "−5" or "—"
 */
function formatChange(quantity) {
    const change = Number(quantity);

    if (change === 0) {
        return '—';
    }

    return `${change < 0 ? '−' : '+'}${formatQuantity(Math.abs(change))}`;
}

/**
 * A money figure that can be a gain or a loss, with a plus in front of a gain so the two are told apart.
 * @param {string} amount
 */
const formatSignedRupiah = (amount) => (Number(amount) > 0 ? `+${formatRupiah(amount)}` : formatRupiah(amount));
</script>

<template>
    <StoreLayout title="Laporan Stok">
        <StockReportTabs current="movements" />

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
                    <small>Barang bergerak</small>
                    <b>{{ summary.products_count }}</b>
                    <em>barang dengan pergerakan di gudang jual</em>
                </div>
                <div class="kpi">
                    <small>Keluar lewat penjualan (nilai modal)</small>
                    <b>{{ formatRupiah(summary.sold_cost) }}</b>
                    <em>terjual dikurangi void dan retur, harga beli terakhir</em>
                </div>
                <div class="kpi">
                    <small>Perubahan karena opname (nilai modal)</small>
                    <b :class="{ 'kpi__bad': Number(summary.counted_cost) < 0 }">{{ formatSignedRupiah(summary.counted_cost) }}</b>
                    <em>plus = stok lebih ditemukan, minus = stok kurang</em>
                </div>
            </div>
        </Deferred>

        <section class="card">
            <h2>Pergerakan stok per barang</h2>

            <div class="filters">
                <q-input v-model="search" outlined dense clearable placeholder="Cari nama atau SKU" class="filters__search">
                    <template #prepend><q-icon name="search" /></template>
                </q-input>
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
                                        <th class="table__right" :aria-sort="sortState('opening_quantity')">
                                            <button type="button" class="table__sort" @click="sortBy('opening_quantity')">Awal periode<q-icon :name="sortIcon('opening_quantity')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('sold_quantity')">
                                            <button type="button" class="table__sort" @click="sortBy('sold_quantity')">Terjual<q-icon :name="sortIcon('sold_quantity')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('voided_quantity')">
                                            <button type="button" class="table__sort" @click="sortBy('voided_quantity')">Void nota<q-icon :name="sortIcon('voided_quantity')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('returned_quantity')">
                                            <button type="button" class="table__sort" @click="sortBy('returned_quantity')">Retur<q-icon :name="sortIcon('returned_quantity')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('counted_quantity')">
                                            <button type="button" class="table__sort" @click="sortBy('counted_quantity')">Opname<q-icon :name="sortIcon('counted_quantity')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('closing_quantity')">
                                            <button type="button" class="table__sort" @click="sortBy('closing_quantity')">Akhir periode<q-icon :name="sortIcon('closing_quantity')" size="14px" /></button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="products.data.length === 0">
                                        <td colspan="7" class="table__empty">Tidak ada barang yang bergerak pada periode ini.</td>
                                    </tr>
                                    <tr v-for="product in products.data" :key="product.id">
                                        <td>
                                            <b>{{ product.name }}</b>
                                            <small class="table__muted">{{ [product.sku, product.category, product.brand].filter(Boolean).join(' · ') }} · {{ product.base_unit }}</small>
                                        </td>
                                        <td class="table__right">{{ formatQuantity(product.opening_quantity) }}</td>
                                        <td class="table__right">{{ formatChange(product.sold_quantity) }}</td>
                                        <td class="table__right">{{ formatChange(product.voided_quantity) }}</td>
                                        <td class="table__right">{{ formatChange(product.returned_quantity) }}</td>
                                        <td class="table__right">{{ formatChange(product.counted_quantity) }}</td>
                                        <td class="table__right">
                                            <b :class="{ 'chip chip--bad': Number(product.closing_quantity) < 0 }">{{ formatQuantity(product.closing_quantity) }}</b>
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
            <p class="note">Jumlah dalam satuan dasar barang, hanya gudang jual. Awal periode + terjual + void + retur + opname = akhir periode. Retur yang masuk gudang retur baru tampil sebagai opname setelah diperiksa. Nilai modal memakai harga beli terakhir.</p>
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

.kpi .kpi__bad {
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

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.filters__search {
    flex: 2 1 260px;
}

.filters__select {
    flex: 1 1 170px;
    min-width: 170px;
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

.table td b.chip {
    display: inline-block;
}

.table .table__right {
    text-align: right;
    white-space: nowrap;
}

.table .table__muted {
    color: var(--app-mute);
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
