<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import BarcodeLabelDialog from '../../../Components/BarcodeLabelDialog.vue';
import BulkPriceDialog from '../../../Components/BulkPriceDialog.vue';
import PriceLabelDialog from '../../../Components/PriceLabelDialog.vue';
import SearchableSelect from '../../../Components/SearchableSelect.vue';
import { useConfirmDelete } from '../../../Composables/useConfirmDelete';
import { formatQuantity, formatStock } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    products: { type: Object, required: true },
    /** Every item the current search and filters match (up to what one bulk change takes). Only present after "select all" asked for it. */
    matching_ids: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    categories: { type: Array, required: true },
    brands: { type: Array, required: true },
    statuses: { type: Array, required: true },
    /** The normal / damaged goods (DG) choices of the condition filter. */
    conditions: { type: Array, required: true },
    create_url: { type: String, required: true },
    /** What the window that prints barcode stickers for the ticked items needs. */
    barcode_label: { type: Object, required: true },
    /** What the window that prints price labels for the ticked items needs. */
    price_label: { type: Object, required: true },
    /** What the window that changes prices of many items at once needs. */
    bulk_price: { type: Object, required: true },
});

const page = usePage();
const $q = useQuasar();
const deleteWithConfirmation = useConfirmDelete();

const search = ref(props.filters.search);
const category = ref(props.filters.category);
const brand = ref(props.filters.brand ?? '');
const status = ref(props.filters.status);
const condition = ref(props.filters.condition);

const statusOptions = computed(() => [{ label: 'Semua status', value: '' }, ...props.statuses]);

const conditionOptions = computed(() => [{ label: 'Semua kondisi', value: '' }, ...props.conditions]);

const priceFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/** What is ticked stays ticked while the list is searched, filtered or paged, so one change can cover many pages. */
const selectedIds = ref([]);
const selectedSet = computed(() => new Set(selectedIds.value));

const pageIds = computed(() => props.products.data.map((product) => product.id));
const isPageAllSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selectedSet.value.has(id)));
const isPageSomeSelected = computed(() => pageIds.value.some((id) => selectedSet.value.has(id)));

const isBulkOpen = ref(false);
const isBarcodeOpen = ref(false);
const isPriceLabelOpen = ref(false);

/**
 * Adds items to the selection, unless that would pass the most one bulk change takes.
 * @param {number[]} ids
 */
function select(ids) {
    const merged = new Set([...selectedIds.value, ...ids]);

    if (merged.size > props.bulk_price.max_products) {
        $q.notify({ type: 'warning', message: `Paling banyak ${props.bulk_price.max_products} barang sekali ubah. Persempit pilihan lewat kategori atau merek.` });

        return;
    }

    selectedIds.value = [...merged];
}

const isSelectingAll = ref(false);

/** How many items "select all" takes: everything the list matches, up to what one bulk change takes. */
const selectableCount = computed(() => Math.min(props.products.total, props.bulk_price.max_products));

/** The ids of everything the list matches are only worked out when asked for, since most visits never select all. */
function selectAllMatching() {
    router.reload({
        only: ['matching_ids'],
        onStart: () => (isSelectingAll.value = true),
        onFinish: () => (isSelectingAll.value = false),
        onSuccess: () => select(props.matching_ids),
    });
}

/** @param {number[]} ids */
function deselect(ids) {
    const removed = new Set(ids);

    selectedIds.value = selectedIds.value.filter((id) => !removed.has(id));
}

function togglePage() {
    if (isPageAllSelected.value) {
        deselect(pageIds.value);
    } else {
        select(pageIds.value);
    }
}

function toggleProduct(productId) {
    if (selectedSet.value.has(productId)) {
        deselect([productId]);
    } else {
        select([productId]);
    }
}

function confirmDelete(product) {
    deleteWithConfirmation(product.delete_url, {
        title: 'Hapus barang?',
        message: `${product.name} akan dihapus permanen. Barang yang sudah punya stok atau transaksi tidak bisa dihapus.`,
    });
}

function applyFilters(extraParams = {}) {
    router.get(
        page.url.split('?')[0],
        { search: search.value || undefined, category: category.value || undefined, brand: brand.value || undefined, status: status.value || undefined, condition: condition.value || undefined, ...extraParams },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch([category, brand, status, condition], () => applyFilters());

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
    <StoreLayout title="Daftar Barang">
        <template #actions>
            <q-btn v-if="selectedIds.length > 0" outline no-caps color="primary" icon="qr_code_2" :label="`Cetak Barcode (${selectedIds.length})`" class="q-mr-sm" @click="isBarcodeOpen = true" />
            <q-btn v-if="selectedIds.length > 0" outline no-caps color="primary" icon="sell" :label="`Cetak Label Harga (${selectedIds.length})`" class="q-mr-sm" @click="isPriceLabelOpen = true" />
            <q-btn v-if="selectedIds.length > 0" outline no-caps color="primary" icon="price_change" :label="`Ubah Harga (${selectedIds.length})`" class="q-mr-sm" @click="isBulkOpen = true" />
            <q-btn color="primary" unelevated no-caps icon="add" label="Tambah Barang" @click="router.visit(create_url)" />
        </template>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nama atau SKU" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <SearchableSelect v-model="category" :options="[{ id: null, name: 'Semua kategori' }, ...categories]" dense class="filters__select" aria-label="Kategori" />
            <SearchableSelect v-model="brand" :options="[{ id: '', name: 'Semua merek' }, ...brands]" dense class="filters__select" aria-label="Merek" />
            <q-select v-model="status" :options="statusOptions" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
            <q-select v-model="condition" :options="conditionOptions" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" aria-label="Kondisi barang" />
        </div>

        <div v-if="products.total > 0" class="selection">
            <span>
                <b>{{ selectedIds.length }}</b> barang dipilih
                <small v-if="products.total > selectableCount" class="items__muted">(pilih semua dibatasi {{ bulk_price.max_products }} barang)</small>
            </span>
            <span class="selection__actions">
                <q-btn flat dense no-caps color="primary" :label="`Pilih semua ${selectableCount} hasil`" :loading="isSelectingAll" @click="selectAllMatching" />
                <q-btn flat dense no-caps color="grey-7" label="Kosongkan pilihan" :disable="selectedIds.length === 0" @click="selectedIds = []" />

            </span>
        </div>

        <div class="items">
            <table class="items__table">
                <thead>
                    <tr>
                        <th class="items__check">
                            <q-checkbox :model-value="isPageAllSelected ? true : isPageSomeSelected ? null : false" dense aria-label="Pilih semua barang di halaman ini" @update:model-value="togglePage" />
                        </th>
                        <th>Barang</th>
                        <th class="items__right">Stok jual</th>
                        <th class="items__right">Dead stock</th>
                        <th class="items__right">Modal</th>
                        <th class="items__right">Harga jual</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="products.data.length === 0">
                        <td colspan="8" class="items__empty">Belum ada barang yang cocok.</td>
                    </tr>
                    <tr v-for="product in products.data" :key="product.id" :class="{ 'items__row--on': selectedSet.has(product.id) }">
                        <td class="items__check">
                            <q-checkbox :model-value="selectedSet.has(product.id)" dense :aria-label="`Pilih ${product.name}`" @update:model-value="toggleProduct(product.id)" />
                        </td>
                        <td>
                            <b>{{ product.name }}<span v-if="product.is_damaged_goods" class="chip chip--warn items__dg">DG</span></b>
                            <small>{{ [product.sku, product.category, product.brand].filter(Boolean).join(' · ') }}</small>
                        </td>
                        <td class="items__right">
                            <span :class="{ 'chip chip--bad': product.is_below_minimum }">
                                {{ formatStock(product.sellable_stock, product.units, product.base_unit, product.sealed_pack?.intact ?? null) }}
                            </span>
                            <small v-if="product.is_below_minimum">min {{ formatQuantity(product.min_stock) }}</small>
                        </td>
                        <td class="items__right">
                            <span v-if="Number(product.dead_stock) > 0" class="chip chip--warn">
                                {{ formatStock(product.dead_stock, product.units, product.base_unit, product.sealed_pack?.dead_intact ?? null) }}
                            </span>
                            <span v-else class="items__muted">—</span>
                            <small v-if="Number(product.returns_stock) > 0" class="items__muted items__returns">+ {{ formatStock(product.returns_stock, product.units, product.base_unit, null) }} retur menunggu</small>
                        </td>
                        <td class="items__right">{{ priceFormat.format(product.cost_price) }}</td>
                        <td class="items__right">
                            <template v-if="product.default_sale">{{ product.default_sale.unit }} {{ priceFormat.format(product.default_sale.price) }}</template>
                            <span v-else class="items__muted">—</span>
                        </td>
                        <td>
                            <span class="chip" :class="product.is_active ? 'chip--ok' : 'chip--off'">{{ product.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </td>
                        <td class="items__right items__actions">
                            <q-btn flat round dense icon="edit" color="grey-7" :aria-label="`Edit ${product.name}`" @click="router.visit(product.edit_url)" />
                            <q-btn
                                v-if="product.can_delete"
                                flat
                                round
                                dense
                                icon="delete_outline"
                                color="negative"
                                :aria-label="`Hapus ${product.name}`"
                                @click="confirmDelete(product)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <BarcodeLabelDialog v-model="isBarcodeOpen" :product-ids="selectedIds" :urls="barcode_label.urls" :limits="barcode_label.limits" />

        <PriceLabelDialog v-model="isPriceLabelOpen" :product-ids="selectedIds" :urls="price_label.urls" :limits="price_label.limits" />

        <BulkPriceDialog
            v-model="isBulkOpen"
            :product-ids="selectedIds"
            :cost-methods="bulk_price.cost_methods"
            :selling-methods="bulk_price.selling_methods"
            :urls="bulk_price.urls"
            @applied="selectedIds = []"
        />

        <div v-if="products.total > 0" class="pager">
            <span class="items__muted">{{ products.from }}–{{ products.to }} dari {{ products.total }} barang</span>
            <q-pagination
                v-if="products.last_page > 1"
                :model-value="products.current_page"
                :max="products.last_page"
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

.filters__search {
    flex: 2 1 260px;
}

.filters__select {
    flex: 1 1 170px;
    min-width: 170px;
}

.selection {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}

.selection__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.items__check {
    width: 44px;
}

.items__row--on {
    background: var(--app-brand-soft);
}

.items {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.items__table {
    width: 100%;
    border-collapse: collapse;
}

.items__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.items__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.items__table tr:last-child td {
    border-bottom: 0;
}

.items__table td b,
.items__table td small {
    display: block;
}

.items__table td small,
.items__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.items__returns {
    display: block;
}

.items__table .items__right {
    text-align: right;
    white-space: nowrap;
}

.items__table .items__actions {
    min-width: 92px;
}

.items__table td b .items__dg {
    display: inline-block;
    margin-left: 8px;
}

.items__empty {
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
