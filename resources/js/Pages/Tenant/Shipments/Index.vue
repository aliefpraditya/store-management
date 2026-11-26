<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { shipmentStatusChipClass } from '../../../Composables/useShipmentStatus';
import { visitUnlessNewTab } from '../../../Composables/useVisitLink';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    shipments: { type: Object, required: true },
    filters: { type: Object, required: true },
    carriers: { type: Array, required: true },
    statuses: { type: Array, required: true },
    create_url: { type: String, required: true },
});

const page = usePage();

const search = ref(props.filters.search ?? '');
const carrierFilter = ref(props.filters.carrier ?? '');
const statusFilter = ref(props.filters.status ?? '');

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

function applyFilters(extra = {}) {
    router.get(
        page.url.split('?')[0],
        { search: search.value || undefined, carrier: carrierFilter.value || undefined, status: statusFilter.value || undefined, ...extra },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch([carrierFilter, statusFilter], () => applyFilters());

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
    <StoreLayout title="Pengiriman">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Pengiriman" :href="create_url" @click="visitUnlessNewTab($event, create_url)" />
        </template>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nomor, kapal, atau nomor container" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
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
        </div>

        <div class="shipments">
            <table class="shipments__table">
                <thead>
                    <tr>
                        <th>Pengiriman</th>
                        <th>Ekspedisi</th>
                        <th class="shipments__right">Nota</th>
                        <th class="shipments__right">Container</th>
                        <th class="shipments__right">Biaya</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="shipments.data.length === 0">
                        <td colspan="6" class="shipments__empty">Belum ada pengiriman. Buat pengiriman untuk nota yang sudah dipesan.</td>
                    </tr>
                    <tr v-for="shipment in shipments.data" :key="shipment.id" :class="{ 'shipments__row--cancelled': shipment.status === 'cancelled' }">
                        <td>
                            <Link :href="shipment.url" class="shipments__link">{{ shipment.number }}</Link>
                            <small>
                                <template v-if="shipment.vessel_name">{{ shipment.vessel_name }}</template>
                                <template v-if="shipment.departure_date"> · berangkat {{ dateFormat.format(new Date(shipment.departure_date)) }}</template>
                                <template v-if="shipment.expected_arrival_date"> · tiba {{ dateFormat.format(new Date(shipment.expected_arrival_date)) }}</template>
                            </small>
                        </td>
                        <td>{{ shipment.carrier_name }}</td>
                        <td class="shipments__right">{{ shipment.purchases_count }}</td>
                        <td class="shipments__right">{{ shipment.containers_count }}</td>
                        <td class="shipments__right">{{ formatRupiah(shipment.total_freight_cost) }}</td>
                        <td><span class="chip" :class="shipmentStatusChipClass[shipment.status]">{{ shipment.status_label }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="shipments.total > 0" class="pager">
            <span class="shipments__muted">{{ shipments.from }}–{{ shipments.to }} dari {{ shipments.total }} pengiriman</span>
            <q-pagination
                v-if="shipments.last_page > 1"
                :model-value="shipments.current_page"
                :max="shipments.last_page"
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

.shipments {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.shipments__table {
    width: 100%;
    border-collapse: collapse;
}

.shipments__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.shipments__table td {
    padding: 10px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.shipments__table td small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.shipments__table tr:last-child td {
    border-bottom: 0;
}

.shipments__table .shipments__right {
    text-align: right;
}

.shipments__row--cancelled td {
    color: var(--app-mute);
}

.shipments__link {
    color: var(--app-brand);
    font-weight: 600;
    text-decoration: none;
}

.shipments__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.shipments__empty {
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
