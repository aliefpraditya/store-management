<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SupplierFormDialog from '../../../Components/SupplierFormDialog.vue';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    suppliers: { type: Object, required: true },
    filters: { type: Object, required: true },
    regions: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const page = usePage();

const canViewPurchases = computed(() => page.props.auth.permissions.includes('purchases.manage'));

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

const statusOptions = [
    { value: '', label: 'Semua status' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

const isFormOpen = ref(false);
const editedSupplier = ref(null);

function applyFilters(extra = {}) {
    router.get(
        page.url.split('?')[0],
        { search: search.value || undefined, status: statusFilter.value || undefined, ...extra },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch(statusFilter, () => applyFilters());

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));

/** @param {object|null} supplier the supplier to edit, or null to add one */
function openForm(supplier) {
    editedSupplier.value = supplier;
    isFormOpen.value = true;
}

/**
 * Switches a supplier on or off from the list. A switched-off supplier stays on the purchases already made from it; it just
 * is not offered for new ones. The update overwrites every detail it carries, so all of them are sent back as they are;
 * `contacts` is left out on purpose, which keeps the salespeople untouched.
 *
 * @param {{name: string, phone: string|null, address: string|null, province: string|null, city: string|null, email: string|null, tax_number: string|null, notes: string|null, update_url: string}} supplier
 * @param {boolean} isActive
 */
function setActive(supplier, isActive) {
    router.put(
        supplier.update_url,
        {
            name: supplier.name,
            phone: supplier.phone,
            address: supplier.address,
            province: supplier.province,
            city: supplier.city,
            email: supplier.email,
            tax_number: supplier.tax_number,
            notes: supplier.notes,
            is_active: isActive,
        },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <StoreLayout title="Supplier">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Supplier" @click="openForm(null)" />
        </template>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nama, telepon, atau kota" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="statusFilter" :options="statusOptions" option-value="value" option-label="label" emit-value map-options behavior="menu" outlined dense class="filters__select" />
        </div>

        <div class="suppliers">
            <table class="suppliers__table">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Telepon</th>
                        <th>Sales</th>
                        <th class="suppliers__right">Nota</th>
                        <th>Aktif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="suppliers.data.length === 0">
                        <td colspan="6" class="suppliers__empty">Belum ada supplier. Tambahkan supplier dulu sebelum membuat nota pembelian.</td>
                    </tr>
                    <tr v-for="supplier in suppliers.data" :key="supplier.id" :class="{ 'suppliers__row--off': !supplier.is_active }">
                        <td>
                            <b>{{ supplier.name }}</b>
                            <small v-if="supplier.address">{{ supplier.address }}</small>
                            <small v-if="supplier.city || supplier.province">{{ [supplier.city, supplier.province].filter(Boolean).join(', ') }}</small>
                            <small v-if="supplier.tax_number">NPWP {{ supplier.tax_number }}</small>
                            <small v-if="supplier.notes">{{ supplier.notes }}</small>
                        </td>
                        <td>
                            {{ supplier.phone ?? '—' }}
                            <small v-if="supplier.email">{{ supplier.email }}</small>
                        </td>
                        <td>
                            <template v-if="supplier.contacts.length > 0">
                                <small v-for="contact in supplier.contacts" :key="contact.id" class="suppliers__contact">
                                    <b>{{ contact.name }}</b>
                                    <template v-if="contact.phone"> · {{ contact.phone }}</template>
                                </small>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="suppliers__right">
                            <Link v-if="canViewPurchases && supplier.purchases_count > 0" :href="supplier.purchases_url" class="suppliers__link">{{ supplier.purchases_count }}</Link>
                            <template v-else>{{ supplier.purchases_count }}</template>
                        </td>
                        <td><q-toggle :model-value="supplier.is_active" :aria-label="`Aktifkan ${supplier.name}`" @update:model-value="setActive(supplier, $event)" /></td>
                        <td class="suppliers__actions"><q-btn flat dense no-caps icon="edit" label="Ubah" @click="openForm(supplier)" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="suppliers.total > 0" class="pager">
            <span class="suppliers__muted">{{ suppliers.from }}–{{ suppliers.to }} dari {{ suppliers.total }} supplier</span>
            <q-pagination
                v-if="suppliers.last_page > 1"
                :model-value="suppliers.current_page"
                :max="suppliers.last_page"
                :max-pages="7"
                direction-links
                boundary-links
                color="primary"
                @update:model-value="applyFilters({ page: $event })"
            />
        </div>

        <SupplierFormDialog v-model="isFormOpen" :supplier="editedSupplier" :regions="regions" :store-url="urls.store" />
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
    flex: 1 1 160px;
}

.suppliers {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.suppliers__table {
    width: 100%;
    border-collapse: collapse;
}

.suppliers__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.suppliers__table td {
    padding: 10px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.suppliers__table td small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.suppliers__table td small.suppliers__contact b {
    font-weight: 600;
}

.suppliers__table tr:last-child td {
    border-bottom: 0;
}

.suppliers__table .suppliers__right {
    text-align: right;
}

.suppliers__row--off td:first-child b {
    color: var(--app-mute);
    text-decoration: line-through;
}

.suppliers__actions {
    text-align: right;
    white-space: nowrap;
}

.suppliers__link {
    color: var(--app-brand);
    font-weight: 600;
    text-decoration: none;
}

.suppliers__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.suppliers__empty {
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
