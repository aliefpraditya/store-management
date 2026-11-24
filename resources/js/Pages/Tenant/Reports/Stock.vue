<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Deferred, router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import SearchableSelect from '../../../Components/SearchableSelect.vue';
import StockReportTabs from '../../../Components/StockReportTabs.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity, formatStock } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `summary` and `positions` are deferred props, each in a group of its own: the page shows at once and each part fills
 * in when the server has it, so they are undefined until then and only read inside a `<Deferred>` that waits for them.
 */
const props = defineProps({
    /** What the table of products is narrowed to, and the day the stock is shown as of (`as_of`, null for now), as the address holds it. */
    filters: { type: Object, required: true },
    /** The day the stock ledger starts (`YYYY-MM-DD`); only sent when a past day is asked for. */
    first_recorded_on: { type: String, default: null },
    summary: { type: Object, default: undefined },
    positions: { type: Object, default: undefined },
    /** The stock conditions the table can be narrowed to, `{value, label}`. */
    statuses: { type: Array, required: true },
    categories: { type: Array, required: true },
    brands: { type: Array, required: true },
    /** The page sizes the table of products offers. */
    per_page_options: { type: Array, required: true },
});

const page = usePage();

const search = ref(props.filters.search);
const category = ref(props.filters.category);
const brand = ref(props.filters.brand ?? '');
const status = ref(props.filters.status);
const asOf = ref(props.filters.as_of ?? '');
const positionsCard = ref(null);

/**
 * A day as people read it, from the `YYYY-MM-DD` the address holds.
 * @param {string} day
 */
const readableDay = (day) => new Date(`${day}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

/** Whether the day asked for is before the ledger started, so the stock then reads 0 only on paper. */
const isBeforeLedger = computed(() => Boolean(props.filters.as_of && props.first_recorded_on && props.filters.as_of < props.first_recorded_on));

const statusOptions = [{ label: 'Semua kondisi', value: '' }, ...props.statuses];

/**
 * What the screen is showing, as the address holds it, so a table that is turned does not undo the others and the page
 * comes back as it was when it is reloaded. Only what has arrived is there.
 * @param {Record<string, string|number|undefined>} change What of the query changes.
 */
function currentQuery(change = {}) {
    return {
        search: search.value || undefined,
        category: category.value || undefined,
        brand: brand.value || undefined,
        status: status.value || undefined,
        as_of: asOf.value || undefined,
        positions_page: props.positions?.current_page,
        positions_per_page: props.positions?.per_page,
        positions_sort: props.positions?.sort,
        positions_direction: props.positions?.direction,
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

/** Narrowing the table starts it from its first page; `filters` is asked for as well so the address and the fields agree. */
const applyFilters = () => reloadParts(['positions', 'filters'], { positions_page: undefined });

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

watch([category, brand, status], applyFilters);

/** A new day changes the figures as well as the table, so both are asked for again. */
watch(asOf, () => reloadParts(['summary', 'positions', 'filters', 'first_recorded_on'], { positions_page: undefined }));

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));

/**
 * A card that counts a stock condition narrows the table to it, and a second click on the same card lifts the
 * narrowing. The table is below the cards, so the page takes the reader to it.
 * @param {string} condition
 */
function toggleStatus(condition) {
    status.value = status.value === condition ? '' : condition;
    positionsCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Ordering by the column the table is already in turns its direction; a new column starts with names A to Z and figures
 * biggest first. The order is the server's, so it holds across pages, and the table starts from its first page.
 * @param {string} column
 */
function sortPositionsBy(column) {
    const isSameColumn = props.positions?.sort === column;
    const direction = isSameColumn ? (props.positions.direction === 'desc' ? 'asc' : 'desc') : column === 'name' ? 'asc' : 'desc';

    reloadParts(['positions'], { positions_page: 1, positions_sort: column, positions_direction: direction });
}

const sortIcon = (column) => (props.positions?.sort === column ? (props.positions.direction === 'desc' ? 'arrow_downward' : 'arrow_upward') : 'unfold_more');
const sortState = (column) => (props.positions?.sort === column ? (props.positions.direction === 'desc' ? 'descending' : 'ascending') : 'none');

const showPositionsPage = (positionsPage) => reloadParts(['positions'], { positions_page: positionsPage });
const changePositionsPerPage = (perPage) => reloadParts(['positions'], { positions_page: 1, positions_per_page: perPage });

/**
 * Whether a product with stock has no purchase price, so it counts for nothing in the value.
 * @param {{cost_price: string, sellable_stock: string, dead_stock: string, returns_stock: string}} product
 */
const isWithoutCost = (product) => Number(product.cost_price) === 0 && (Number(product.sellable_stock) > 0 || Number(product.dead_stock) > 0 || Number(product.returns_stock) > 0);
</script>

<template>
    <StoreLayout title="Laporan Stok">
        <StockReportTabs current="positions" />

        <section class="card as-of">
            <DateInput v-model="asOf" label="Posisi per tanggal" outlined dense clearable class="as-of__date" />
            <p v-if="filters.as_of" class="as-of__note">
                Posisi stok akhir hari <b>{{ readableDay(filters.as_of) }}</b>, dihitung dari catatan pergerakan stok. Nilai memakai harga beli terakhir (hari ini), jadi hanya perkiraan.
            </p>
            <p v-else class="as-of__note">Kosongkan untuk melihat posisi stok sekarang.</p>
            <p v-if="isBeforeLedger" class="as-of__warn">Catatan stok baru dimulai {{ readableDay(first_recorded_on) }}, jadi stok sebelum itu tampil 0 dan belum bisa dipercaya.</p>
        </section>

        <Deferred data="summary">
            <template #fallback>
                <div class="kpis">
                    <q-skeleton v-for="placeholder in 7" :key="placeholder" height="86px" />
                </div>
            </template>

            <div class="kpis">
                <div class="kpi">
                    <small>Barang aktif</small>
                    <b>{{ summary.products_count }}</b>
                    <em>termasuk barang DG</em>
                </div>
                <div class="kpi">
                    <small>Nilai stok jual (estimasi)</small>
                    <b>{{ formatRupiah(summary.sellable_value) }}</b>
                    <em>stok × harga beli terakhir</em>
                    <em v-if="summary.products_without_cost > 0" class="kpi__warn">{{ summary.products_without_cost }} barang berstok belum punya harga beli, jadi tidak ikut dihitung</em>
                </div>
                <button type="button" class="kpi kpi--link" :class="{ 'kpi--on': status === 'below_minimum' }" @click="toggleStatus('below_minimum')">
                    <small>Di bawah minimum</small>
                    <b>{{ summary.below_minimum_count }}</b>
                    <em>barang, termasuk yang habis</em>
                </button>
                <button type="button" class="kpi kpi--link" :class="{ 'kpi--on': status === 'out_of_stock' }" @click="toggleStatus('out_of_stock')">
                    <small>Stok habis</small>
                    <b>{{ summary.out_of_stock_count }}</b>
                    <em>barang aktif dengan stok 0</em>
                </button>
                <button type="button" class="kpi kpi--link" :class="{ 'kpi--on': status === 'negative', 'kpi--alert': summary.negative_count > 0 }" @click="toggleStatus('negative')">
                    <small>Stok negatif</small>
                    <b>{{ summary.negative_count }}</b>
                    <em>barang, perlu dicek lewat opname</em>
                </button>
                <button type="button" class="kpi kpi--link" :class="{ 'kpi--on': status === 'dead_stock' }" @click="toggleStatus('dead_stock')">
                    <small>Dead stock</small>
                    <b>{{ formatRupiah(summary.dead_stock.value) }}</b>
                    <em>{{ summary.dead_stock.products_count }} barang · nilai modal</em>
                </button>
                <button type="button" class="kpi kpi--link" :class="{ 'kpi--on': status === 'returns_waiting' }" @click="toggleStatus('returns_waiting')">
                    <small>Retur menunggu diperiksa</small>
                    <b>{{ formatRupiah(summary.returns_waiting.value) }}</b>
                    <em>{{ summary.returns_waiting.products_count }} barang · nilai modal</em>
                </button>
            </div>
        </Deferred>

        <section ref="positionsCard" class="card">
            <h2>Posisi stok per barang</h2>

            <div class="filters">
                <q-input v-model="search" outlined dense clearable placeholder="Cari nama atau SKU" class="filters__search">
                    <template #prepend><q-icon name="search" /></template>
                </q-input>
                <SearchableSelect v-model="category" :options="[{ id: null, name: 'Semua kategori' }, ...categories]" dense class="filters__select" aria-label="Kategori" />
                <SearchableSelect v-model="brand" :options="[{ id: '', name: 'Semua merek' }, ...brands]" dense class="filters__select" aria-label="Merek" />
                <q-select v-model="status" :options="statusOptions" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" aria-label="Kondisi stok" />
            </div>

            <Deferred data="positions">
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
                                            <button type="button" class="table__sort" @click="sortPositionsBy('name')">Barang<q-icon :name="sortIcon('name')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('sellable_stock')">
                                            <button type="button" class="table__sort" @click="sortPositionsBy('sellable_stock')">Stok jual<q-icon :name="sortIcon('sellable_stock')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('dead_stock')">
                                            <button type="button" class="table__sort" @click="sortPositionsBy('dead_stock')">Dead stock<q-icon :name="sortIcon('dead_stock')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('returns_stock')">
                                            <button type="button" class="table__sort" @click="sortPositionsBy('returns_stock')">Retur menunggu<q-icon :name="sortIcon('returns_stock')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('cost_price')">
                                            <button type="button" class="table__sort" @click="sortPositionsBy('cost_price')">Harga beli<q-icon :name="sortIcon('cost_price')" size="14px" /></button>
                                        </th>
                                        <th class="table__right" :aria-sort="sortState('sellable_value')">
                                            <button type="button" class="table__sort" @click="sortPositionsBy('sellable_value')">Nilai stok jual<q-icon :name="sortIcon('sellable_value')" size="14px" /></button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="positions.data.length === 0">
                                        <td colspan="6" class="table__empty">Tidak ada barang yang cocok.</td>
                                    </tr>
                                    <tr v-for="product in positions.data" :key="product.id">
                                        <td>
                                            <b>
                                                {{ product.name }}
                                                <span v-if="product.is_damaged_goods" class="chip chip--warn table__chip">DG</span>
                                                <span v-if="!product.is_active" class="chip chip--off table__chip">Nonaktif</span>
                                            </b>
                                            <small class="table__muted">{{ [product.sku, product.category, product.brand].filter(Boolean).join(' · ') }}</small>
                                        </td>
                                        <td class="table__right">
                                            <span :class="{ 'chip chip--bad': product.is_below_minimum || product.has_negative_stock }">
                                                {{ formatStock(product.sellable_stock, product.units, product.base_unit, product.sealed_pack?.intact ?? null) }}
                                            </span>
                                            <small v-if="product.has_negative_stock" class="table__muted">negatif</small>
                                            <small v-else-if="product.is_below_minimum" class="table__muted">min {{ formatQuantity(product.min_stock) }}</small>
                                        </td>
                                        <td class="table__right">
                                            <span v-if="Number(product.dead_stock) > 0" class="chip chip--warn">
                                                {{ formatStock(product.dead_stock, product.units, product.base_unit, product.sealed_pack?.dead_intact ?? null) }}
                                            </span>
                                            <span v-else class="table__muted">—</span>
                                        </td>
                                        <td class="table__right">
                                            <template v-if="Number(product.returns_stock) > 0">{{ formatStock(product.returns_stock, product.units, product.base_unit, null) }}</template>
                                            <span v-else class="table__muted">—</span>
                                        </td>
                                        <td class="table__right">
                                            {{ formatRupiah(product.cost_price) }}
                                            <small v-if="isWithoutCost(product)" class="table__warn">belum ada harga beli</small>
                                        </td>
                                        <td class="table__right"><b>{{ formatRupiah(product.sellable_value) }}</b></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="positions.total > 0" class="pager">
                            <span class="pager__range">{{ positions.from }}–{{ positions.to }} dari {{ positions.total }} barang</span>
                            <div class="pager__controls">
                                <q-select
                                    :model-value="positions.per_page"
                                    :options="per_page_options"
                                    outlined
                                    dense
                                    behavior="menu"
                                    :option-label="(option) => `${option} per halaman`"
                                    class="pager__per-page"
                                    aria-label="Jumlah barang per halaman"
                                    @update:model-value="changePositionsPerPage"
                                />
                                <q-pagination
                                    v-if="positions.last_page > 1"
                                    :model-value="positions.current_page"
                                    :max="positions.last_page"
                                    :max-pages="7"
                                    direction-links
                                    boundary-links
                                    color="primary"
                                    @update:model-value="showPositionsPage"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Deferred>
            <p class="note">Nilai memakai harga beli terakhir per satuan dasar. Stok negatif tidak mengurangi nilai.</p>
        </section>
    </StoreLayout>
</template>

<style scoped>
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
    color: inherit;
    font: inherit;
    text-align: left;
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

.kpi--link {
    cursor: pointer;
    transition: border-color 0.15s;
}

.kpi--link:hover,
.kpi--on {
    border-color: var(--q-primary);
}

.kpi--on {
    background: var(--app-brand-soft);
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

.card h2 small {
    color: var(--app-mute);
    font-weight: 400;
}

.as-of {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 16px;
}

.as-of__date {
    width: 200px;
}

.as-of__note,
.as-of__warn {
    margin: 0;
    color: var(--app-mute);
    font-size: 12px;
}

.as-of__warn {
    flex-basis: 100%;
    color: #b45309;
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
