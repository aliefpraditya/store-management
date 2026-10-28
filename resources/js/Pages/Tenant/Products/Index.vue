<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useConfirmDelete } from '../../../Composables/useConfirmDelete';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
    categories: { type: Array, required: true },
    create_url: { type: String, required: true },
});

const page = usePage();
const deleteWithConfirmation = useConfirmDelete();

const search = ref(props.filters.search);
const category = ref(props.filters.category);
const status = ref(props.filters.status);

const statusOptions = [
    { label: 'Semua status', value: '' },
    { label: 'Aktif', value: 'active' },
    { label: 'Nonaktif', value: 'inactive' },
];

const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });
const priceFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/**
 * Shows a base-unit quantity in the larger units first, e.g. 30 base units with 1 DUS = 4 become "7 DUS + 2 PAIL".
 * @param {string|number} quantity quantity in the base unit
 * @param {{code: string, conversion_factor: string}[]} units the product's units
 * @param {string} baseUnit code of the base unit
 * @returns {string}
 */
function formatStock(quantity, units, baseUnit) {
    let rest = Math.round(Number(quantity) * 1000) / 1000;

    if (rest === 0) {
        return `0 ${baseUnit}`;
    }

    const largerUnits = units.filter((unit) => Number(unit.conversion_factor) > 1).sort((a, b) => b.conversion_factor - a.conversion_factor);
    const parts = [];

    for (const unit of largerUnits) {
        const factor = Number(unit.conversion_factor);
        const count = Math.floor(rest / factor + 1e-9);

        if (count > 0) {
            parts.push(`${count} ${unit.code}`);
            rest = Math.round((rest - count * factor) * 1000) / 1000;
        }
    }

    if (rest > 0 || parts.length === 0) {
        parts.push(`${numberFormat.format(rest)} ${baseUnit}`);
    }

    return parts.join(' + ');
}

function confirmDelete(product) {
    deleteWithConfirmation(product.delete_url, {
        title: 'Hapus barang?',
        message: `${product.name} akan dihapus permanen. Barang yang sudah punya stok atau transaksi tidak bisa dihapus.`,
    });
}

function applyFilters(extra = {}) {
    router.get(
        page.url.split('?')[0],
        { search: search.value || undefined, category: category.value || undefined, status: status.value || undefined, ...extra },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch([category, status], () => applyFilters());
</script>

<template>
    <StoreLayout title="Daftar Barang">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Tambah Barang" @click="router.visit(create_url)" />
        </template>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nama, SKU, atau barcode" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select
                v-model="category"
                :options="[{ id: null, name: 'Semua kategori' }, ...categories]"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                outlined
                dense
                class="filters__select"
            />
            <q-select v-model="status" :options="statusOptions" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
        </div>

        <div class="items">
            <table class="items__table">
                <thead>
                    <tr>
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
                        <td colspan="7" class="items__empty">Belum ada barang yang cocok.</td>
                    </tr>
                    <tr v-for="product in products.data" :key="product.id">
                        <td>
                            <b>{{ product.name }}</b>
                            <small>{{ [product.sku, product.category, product.brand].filter(Boolean).join(' · ') }}</small>
                        </td>
                        <td class="items__right">
                            <span :class="{ 'chip chip--bad': product.is_below_minimum }">
                                {{ formatStock(product.sellable_stock, product.units, product.base_unit) }}
                            </span>
                            <small v-if="product.is_below_minimum">min {{ numberFormat.format(product.min_stock) }}</small>
                        </td>
                        <td class="items__right">
                            <span v-if="Number(product.dead_stock) > 0" class="chip chip--warn">
                                {{ formatStock(product.dead_stock, product.units, product.base_unit) }}
                            </span>
                            <span v-else class="items__muted">—</span>
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

.items__table .items__right {
    text-align: right;
    white-space: nowrap;
}

.items__table .items__actions {
    min-width: 92px;
}

.items__empty {
    padding: 28px 16px !important;
    color: var(--app-mute);
    text-align: center;
}

.chip {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 99px;
    background: #f2f3f5;
    color: var(--app-ink-soft);
    font-size: 12px;
    font-weight: 600;
}

.chip--ok {
    background: #e7f8f1;
    color: #059669;
}

.chip--warn {
    background: #fdf1e0;
    color: #c2710c;
}

.chip--bad {
    background: #fde8ee;
    color: #be123c;
}

.chip--off {
    background: #f2f3f5;
    color: var(--app-ink-soft);
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
