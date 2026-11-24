<script setup>
import { ref, watch } from 'vue';
import { Deferred, router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import SalesReportTabs from '../../../Components/SalesReportTabs.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

/**
 * `products` and `groups` are deferred props, each in a group of its own: the page shows at once and each part fills in
 * when the server has it, so they are undefined until then and only read inside a `<Deferred>` that waits for them.
 */
const props = defineProps({
    period: { type: Object, required: true },
    products: { type: Object, default: undefined },
    groups: { type: Object, default: undefined },
    /** The page sizes the products table offers. */
    per_page_options: { type: Array, required: true },
    /** The page sizes the table per category or brand offers. */
    group_per_page_options: { type: Array, required: true },
    /** Whether the period asked for was longer than the report allows and was cut back from its start. */
    is_period_cut_back: { type: Boolean, required: true },
    max_days: { type: Number, required: true },
});

const groupingOptions = [
    { label: 'Kategori', value: 'category' },
    { label: 'Merek', value: 'brand' },
];

const page = usePage();

const from = ref(props.period.from);
const to = ref(props.period.to);

/**
 * What the tables are showing, as the address holds it, so a table that is turned does not undo the others and the page
 * comes back as it was when it is reloaded. Only what has arrived is there.
 */
function tableQuery() {
    return {
        products_page: props.products?.current_page,
        products_per_page: props.products?.per_page,
        products_sort: props.products?.sort,
        products_direction: props.products?.direction,
        groups_by: props.groups?.grouping,
        groups_page: props.groups?.current_page,
        groups_per_page: props.groups?.per_page,
        groups_sort: props.groups?.sort,
        groups_direction: props.groups?.direction,
    };
}

/**
 * Asks for the period typed. Skipped when it is the period on screen already, which is also what the server answers when it
 * swapped the dates or cut the period back and the fields were set to that answer. The tables keep their sizes, grouping and
 * order, and start from their first page.
 */
function applyPeriod() {
    if (from.value && to.value && (from.value !== props.period.from || to.value !== props.period.to)) {
        router.get(page.url.split('?')[0], { from: from.value, to: to.value, ...tableQuery(), products_page: undefined, groups_page: undefined }, { preserveState: true, replace: true, preserveScroll: true });
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

/**
 * Turning a table (its page, size, grouping or order) asks the server for that table only, so the rest of the page is not worked out again.
 * @param {'products'|'groups'} table
 * @param {Record<string, string|number>} change What of the table's query changes.
 */
function reloadTable(table, change) {
    router.get(page.url.split('?')[0], { from: props.period.from, to: props.period.to, ...tableQuery(), ...change }, { only: [table], preserveState: true, replace: true, preserveScroll: true });
}

const showProductsPage = (productsPage) => reloadTable('products', { products_page: productsPage });
const changeProductsPerPage = (perPage) => reloadTable('products', { products_page: 1, products_per_page: perPage });

const showGroupsPage = (groupsPage) => reloadTable('groups', { groups_page: groupsPage });
const changeGroupsPerPage = (perPage) => reloadTable('groups', { groups_page: 1, groups_per_page: perPage });
const changeGrouping = (grouping) => reloadTable('groups', { groups_page: 1, groups_by: grouping });

/** The columns of the two tables, each one a way to order it (`key` is what the server calls the order); `isFigure` columns are set to the right. */
const productColumns = [
    { key: 'name', label: 'Barang', isFigure: false },
    { key: 'quantity', label: 'Terjual', isFigure: true },
    { key: 'revenue', label: 'Penjualan', isFigure: true },
    { key: 'cost', label: 'Modal', isFigure: true },
    { key: 'profit', label: 'Laba', isFigure: true },
    { key: 'margin', label: 'Margin', isFigure: true },
];

const groupColumns = [
    { key: 'name', label: null, isFigure: false },
    { key: 'products', label: 'Barang', isFigure: true },
    { key: 'revenue', label: 'Penjualan', isFigure: true },
    { key: 'profit', label: 'Laba', isFigure: true },
    { key: 'margin', label: 'Margin', isFigure: true },
    { key: 'share', label: 'Porsi', isFigure: true },
];

/**
 * Ordering by the column a table is already in turns its direction; a new column starts with names A to Z and figures
 * biggest first. The order is the server's, so it holds across pages, and the table starts from its first page.
 * @param {'products'|'groups'} table
 * @param {string} column
 */
function sortBy(table, column) {
    const current = props[table];
    const isSameColumn = current?.sort === column;
    const direction = isSameColumn ? (current.direction === 'desc' ? 'asc' : 'desc') : column === 'name' ? 'asc' : 'desc';

    reloadTable(table, { [`${table}_page`]: 1, [`${table}_sort`]: column, [`${table}_direction`]: direction });
}

const sortIcon = (table, column) => (props[table]?.sort === column ? (props[table].direction === 'desc' ? 'arrow_downward' : 'arrow_upward') : 'unfold_more');
const sortState = (table, column) => (props[table]?.sort === column ? (props[table].direction === 'desc' ? 'descending' : 'ascending') : 'none');
</script>

<template>
    <StoreLayout title="Laporan Penjualan">
        <SalesReportTabs current="products" />

        <div class="period">
            <DateInput v-model="from" label="Dari" outlined dense :error="!from" error-message="Wajib diisi" class="period__date" />
            <DateInput v-model="to" label="Sampai" outlined dense :error="!to" error-message="Wajib diisi" class="period__date" />
        </div>
        <p v-if="is_period_cut_back" class="notice">Periode terlalu panjang, jadi laporan hanya memuat {{ max_days }} hari terakhir sampai tanggal akhir. Pilih periode yang lebih pendek untuk melihat yang lebih awal.</p>

        <section class="card">
            <h2>Laba kotor per barang <small>(setelah retur; klik judul kolom untuk mengurutkan)</small></h2>
            <Deferred data="products">
                <template #fallback>
                    <q-skeleton height="240px" />
                </template>

                <template #default="{ reloading }">
                    <p v-if="products.total === 0" class="empty">Belum ada penjualan pada periode ini.</p>
                    <div v-else :class="{ 'is-reloading': reloading }">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th v-for="column in productColumns" :key="column.key" :class="{ table__right: column.isFigure }" :aria-sort="sortState('products', column.key)">
                                            <button type="button" class="table__sort" @click="sortBy('products', column.key)">{{ column.label }}<q-icon :name="sortIcon('products', column.key)" size="14px" /></button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="product in products.data" :key="product.product_id">
                                        <td>{{ product.name }}</td>
                                        <td class="table__right">{{ formatQuantity(product.quantity) }} {{ product.unit }}</td>
                                        <td class="table__right">{{ formatRupiah(product.revenue) }}</td>
                                        <td class="table__right">{{ formatRupiah(product.cost) }}</td>
                                        <td class="table__right"><b>{{ formatRupiah(product.profit) }}</b></td>
                                        <td class="table__right">{{ product.margin_percent }}%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pager">
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
                                    @update:model-value="changeProductsPerPage"
                                />
                                <q-pagination
                                    v-if="products.last_page > 1"
                                    :model-value="products.current_page"
                                    :max="products.last_page"
                                    :max-pages="7"
                                    direction-links
                                    boundary-links
                                    color="primary"
                                    @update:model-value="showProductsPage"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Deferred>
            <p class="note">Laba = penjualan − modal, setelah retur. Modal memakai harga beli terakhir saat barang terjual, jadi laba adalah perkiraan; barang yang belum punya harga beli labanya sama dengan penjualannya. Rugi dead stock yang ditemukan pada periode ini ikut mengurangi laba barangnya.</p>
        </section>

        <section class="card">
            <div class="card__head">
                <h2>Penjualan per {{ groups?.grouping === 'brand' ? 'merek' : 'kategori' }} <small>(setelah retur; klik judul kolom untuk mengurutkan)</small></h2>
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
                    <q-skeleton height="200px" />
                </template>

                <template #default="{ reloading }">
                    <p v-if="groups.total === 0" class="empty">Belum ada penjualan pada periode ini.</p>
                    <div v-else :class="{ 'is-reloading': reloading }">
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th v-for="column in groupColumns" :key="column.key" :class="{ table__right: column.isFigure }" :aria-sort="sortState('groups', column.key)">
                                            <button type="button" class="table__sort" @click="sortBy('groups', column.key)">{{ column.label ?? (groups.grouping === 'brand' ? 'Merek' : 'Kategori') }}<q-icon :name="sortIcon('groups', column.key)" size="14px" /></button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="group in groups.data" :key="group.id ?? 'none'">
                                        <td>{{ group.name }}</td>
                                        <td class="table__right">{{ group.product_count }}</td>
                                        <td class="table__right">{{ formatRupiah(group.revenue) }}</td>
                                        <td class="table__right"><b>{{ formatRupiah(group.profit) }}</b></td>
                                        <td class="table__right">{{ group.margin_percent }}%</td>
                                        <td class="table__right">{{ group.share_percent }}%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pager">
                            <span class="pager__range">{{ groups.from }}–{{ groups.to }} dari {{ groups.total }} {{ groups.grouping === 'brand' ? 'merek' : 'kategori' }}</span>
                            <div class="pager__controls">
                                <q-select
                                    :model-value="groups.per_page"
                                    :options="group_per_page_options"
                                    outlined
                                    dense
                                    behavior="menu"
                                    :option-label="(option) => `${option} per halaman`"
                                    class="pager__per-page"
                                    aria-label="Jumlah baris per halaman"
                                    @update:model-value="changeGroupsPerPage"
                                />
                                <q-pagination
                                    v-if="groups.last_page > 1"
                                    :model-value="groups.current_page"
                                    :max="groups.last_page"
                                    :max-pages="7"
                                    direction-links
                                    boundary-links
                                    color="primary"
                                    @update:model-value="showGroupsPage"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Deferred>
            <p class="note">Barang tanpa kategori atau merek dikumpulkan dalam satu baris. Angka tiap baris dihitung seperti tabel barang di atas, jadi semua baris bila dijumlahkan sama dengan jumlah tabel barang. Porsi adalah bagian dari seluruh penjualan periode ini.</p>
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
}

.table .table__right {
    text-align: right;
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

.table__muted {
    color: var(--app-mute);
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
