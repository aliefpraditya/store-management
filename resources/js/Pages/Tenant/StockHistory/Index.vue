<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import ProductSearchSelect from '../../../Components/ProductSearchSelect.vue';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    movements: { type: Object, required: true },
    filters: { type: Object, required: true },
    product: { type: Object, default: null },
    /** The stock of the chosen product at the end of the "Sampai" day, per kind of warehouse; null until both are chosen. */
    stock_on: { type: Object, default: null },
    warehouses: { type: Array, required: true },
    product_search_url: { type: String, required: true },
});

const page = usePage();

const selectedProduct = ref(props.product);
const warehouseId = ref(props.filters.warehouse_id);
const from = ref(props.filters.from);
const to = ref(props.filters.to);

const typeChipClass = {
    opening_balance: 'chip--ok',
    sale: '',
    sale_void: 'chip--warn',
    sale_return: 'chip--warn',
    opname_adjustment: 'chip--warn',
};

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const dayFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' });

/** The day of a record as people read it, from the `Y-m-d` the server sends. */
const formatDay = (isoDay) => dayFormat.format(new Date(`${isoDay}T00:00:00`));

function applyFilters(extra = {}) {
    router.get(
        page.url.split('?')[0],
        {
            product: selectedProduct.value?.id,
            warehouse: warehouseId.value || undefined,
            from: from.value || undefined,
            to: to.value || undefined,
            ...extra,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch([selectedProduct, warehouseId, from, to], () => applyFilters());
</script>

<template>
    <StoreLayout title="Histori Stok">
        <div class="filters">
            <ProductSearchSelect v-model="selectedProduct" :search-url="product_search_url" label="Semua barang" clearable dense class="filters__product" />
            <q-select
                v-model="warehouseId"
                :options="[{ id: null, name: 'Semua gudang' }, ...warehouses]"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                outlined
                dense
                class="filters__select"
            />
            <DateInput v-model="from" label="Dari" outlined dense clearable class="filters__date" />
            <DateInput v-model="to" label="Sampai" outlined dense clearable class="filters__date" />
        </div>

        <section v-if="stock_on && product" class="stock-on">
            <h2>{{ product.name }} per {{ formatDay(stock_on.date) }} <small>(akhir hari)</small></h2>
            <div v-if="stock_on.has_records" class="stock-on__figures">
                <div>
                    <small>Gudang jual</small>
                    <b :class="{ 'chip chip--bad': Number(stock_on.sellable) < 0 }">{{ formatQuantity(stock_on.sellable) }} {{ product.base_unit }}</b>
                </div>
                <div>
                    <small>Dead stock</small>
                    <b>{{ formatQuantity(stock_on.dead_stock) }} {{ product.base_unit }}</b>
                </div>
                <div>
                    <small>Retur menunggu diperiksa</small>
                    <b>{{ formatQuantity(stock_on.returns) }} {{ product.base_unit }}</b>
                </div>
            </div>
            <p v-else class="stock-on__none">
                Belum ada catatan stok barang ini sampai tanggal tersebut, jadi stoknya tidak diketahui.
                <template v-if="stock_on.first_recorded_on">Catatan pertamanya tanggal {{ formatDay(stock_on.first_recorded_on) }}.</template>
                <template v-else>Barang ini belum punya catatan stok sama sekali.</template>
            </p>
        </section>

        <div class="history">
            <table class="history__table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Dokumen</th>
                        <th>Barang</th>
                        <th>Jenis</th>
                        <th class="history__right">Masuk</th>
                        <th class="history__right">Keluar</th>
                        <th class="history__right">Saldo</th>
                        <th>Gudang</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="movements.data.length === 0">
                        <td colspan="8" class="history__empty">Belum ada pergerakan stok yang cocok.</td>
                    </tr>
                    <tr v-for="movement in movements.data" :key="movement.id">
                        <td>{{ dateTimeFormat.format(new Date(movement.moved_at)) }}</td>
                        <td>
                            {{ movement.document_number ?? '—' }}
                            <small v-if="movement.note">{{ movement.note }}</small>
                        </td>
                        <td>
                            <b>{{ movement.product.name }}</b>
                            <small>{{ movement.product.sku }}</small>
                        </td>
                        <td><span class="chip" :class="typeChipClass[movement.type]">{{ movement.type_label }}</span></td>
                        <td class="history__right">
                            <template v-if="Number(movement.quantity) > 0">{{ formatQuantity(movement.quantity) }}</template>
                        </td>
                        <td class="history__right">
                            <template v-if="Number(movement.quantity) < 0">{{ formatQuantity(Math.abs(movement.quantity)) }}</template>
                        </td>
                        <td class="history__right">
                            <b>{{ formatQuantity(movement.balance_after) }}</b> {{ movement.base_unit }}
                            <small v-if="movement.pack_unit && (movement.pack_change !== 0 || movement.packs_after > 0)" class="history__packs">
                                {{ movement.packs_after }} {{ movement.pack_unit }} utuh<template v-if="movement.pack_change !== 0"> ({{ movement.pack_change > 0 ? '+' : '−' }}{{ Math.abs(movement.pack_change) }})</template>
                            </small>
                        </td>
                        <td>{{ movement.warehouse }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="movements.total > 0" class="pager">
            <span class="history__muted">{{ movements.from }}–{{ movements.to }} dari {{ movements.total }} pergerakan · jumlah dalam satuan dasar barang</span>
            <q-pagination
                v-if="movements.last_page > 1"
                :model-value="movements.current_page"
                :max="movements.last_page"
                :max-pages="7"
                direction-links
                boundary-links
                color="primary"
                @update:model-value="applyFilters({ page: $event })"
            />
        </div>
    </StoreLayout>
</template>

<style scoped>
.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.filters__product {
    flex: 2 1 280px;
}

.filters__select {
    flex: 1 1 170px;
}

.filters__date {
    flex: 1 1 150px;
}

.stock-on {
    margin-bottom: 14px;
    padding: 14px 16px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.stock-on h2 {
    margin: 0 0 10px;
    font-size: 15px;
}

.stock-on h2 small,
.stock-on__figures small {
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 400;
}

.stock-on__figures {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 32px;
}

.stock-on__figures small,
.stock-on__figures b {
    display: block;
}

.stock-on__figures b {
    font-size: 20px;
}

.stock-on__figures b.chip {
    display: inline-block;
}

.stock-on__none {
    margin: 0;
    color: #b45309;
    font-size: 13px;
}

.history {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.history__table {
    width: 100%;
    border-collapse: collapse;
}

.history__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.history__table td {
    padding: 10px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.history__table tr:last-child td {
    border-bottom: 0;
}

.history__table td b,
.history__table td small {
    display: block;
}

.history__table td small,
.history__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.history__table .history__right {
    text-align: right;
    white-space: nowrap;
}

.history__table .history__right b {
    display: inline;
}

.history__packs {
    display: block;
    color: #64748b;
}

.history__empty {
    padding: 28px 16px !important;
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
</style>
