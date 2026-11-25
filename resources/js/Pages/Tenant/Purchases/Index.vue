<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { purchaseStatusChipClass } from '../../../Composables/usePurchaseStatus';
import { visitUnlessNewTab } from '../../../Composables/useVisitLink';
import StoreLayout from '../../../Layouts/StoreLayout.vue';


const props = defineProps({
    purchases: { type: Object, required: true },
    filters: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    carriers: { type: Array, required: true },
    statuses: { type: Array, required: true },
    create_url: { type: String, required: true },
});

const page = usePage();

const search = ref(props.filters.search ?? '');
const supplierFilter = ref(props.filters.supplier ?? '');
const carrierFilter = ref(props.filters.carrier ?? '');
const statusFilter = ref(props.filters.status ?? '');
const fromDate = ref(props.filters.from ?? '');
const toDate = ref(props.filters.to ?? '');

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

function applyFilters(extra = {}) {
    router.get(
        page.url.split('?')[0],
        {
            search: search.value || undefined,
            supplier: supplierFilter.value || undefined,
            carrier: carrierFilter.value || undefined,
            status: statusFilter.value || undefined,
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
            ...extra,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch([supplierFilter, carrierFilter, statusFilter, fromDate, toDate], () => applyFilters());

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
    <StoreLayout title="Nota Pembelian">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Nota Pembelian" :href="create_url" @click="visitUnlessNewTab($event, create_url)" />
        </template>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nomor, nomor nota supplier, atau supplier" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select
                v-model="supplierFilter"
                :options="[{ id: '', name: 'Semua supplier' }, ...suppliers]"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                behavior="menu"
                outlined
                dense
                class="filters__select"
            />
            <q-select
                v-model="carrierFilter"
                :options="[{ id: '', name: 'Semua ekspedisi' }, ...carriers]"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                behavior="menu"
                outlined
                dense
                class="filters__select"
            />
            <q-select v-model="statusFilter" :options="[{ value: '', label: 'Semua status' }, ...statuses]" option-value="value" option-label="label" emit-value map-options behavior="menu" outlined dense class="filters__select" />
            <DateInput v-model="fromDate" label="Dari" outlined dense clearable class="filters__date" />
            <DateInput v-model="toDate" label="Sampai" outlined dense clearable class="filters__date" />
        </div>

        <div class="purchases">
            <table class="purchases__table">
                <thead>
                    <tr>
                        <th>Nota</th>
                        <th>Supplier</th>
                        <th>Ekspedisi</th>
                        <th class="purchases__right">Barang</th>
                        <th class="purchases__right">Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="purchases.data.length === 0">
                        <td colspan="6" class="purchases__empty">Belum ada nota pembelian. Buat nota untuk barang yang dipesan dari supplier.</td>
                    </tr>
                    <tr v-for="purchase in purchases.data" :key="purchase.id" :class="{ 'purchases__row--cancelled': purchase.status === 'cancelled' }">
                        <td>
                            <Link :href="purchase.url" class="purchases__link">{{ purchase.number }}</Link>
                            <small>
                                {{ dateFormat.format(new Date(purchase.purchase_date)) }}
                                <template v-if="purchase.supplier_document_number"> · {{ purchase.supplier_document_number }}</template>
                            </small>
                        </td>
                        <td>{{ purchase.supplier_name }}</td>
                        <td>
                            {{ purchase.carrier_name ?? '—' }}
                            <small v-if="purchase.expected_date">tiba {{ dateFormat.format(new Date(purchase.expected_date)) }}</small>
                        </td>
                        <td class="purchases__right">
                            {{ purchase.items_count }}
                            <q-icon v-if="purchase.attachments_count > 0" name="attach_file" size="14px" color="grey-6" :title="`${purchase.attachments_count} lampiran`" />
                        </td>
                        <td class="purchases__right">{{ formatRupiah(purchase.total_amount) }}</td>
                        <td>
                            <span class="chip" :class="purchaseStatusChipClass[purchase.status]">{{ purchase.status_label }}</span>
                            <span v-if="purchase.is_paid" class="chip chip--ok q-ml-xs">Dibayar</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="purchases.total > 0" class="pager">
            <span class="purchases__muted">{{ purchases.from }}–{{ purchases.to }} dari {{ purchases.total }} nota</span>
            <q-pagination
                v-if="purchases.last_page > 1"
                :model-value="purchases.current_page"
                :max="purchases.last_page"
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
    flex: 1 1 160px;
}

.filters__date {
    flex: 1 1 150px;
}

.purchases {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.purchases__table {
    width: 100%;
    border-collapse: collapse;
}

.purchases__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.purchases__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.purchases__table td small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.purchases__table tr:last-child td {
    border-bottom: 0;
}

.purchases__table .purchases__right {
    text-align: right;
    white-space: nowrap;
}

.purchases__link {
    font-weight: 700;
    color: var(--q-primary);
    text-decoration: none;
}

.purchases__row--cancelled td {
    color: var(--app-mute);
}

.purchases__row--cancelled .purchases__right {
    text-decoration: line-through;
}

.purchases__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.purchases__empty {
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
