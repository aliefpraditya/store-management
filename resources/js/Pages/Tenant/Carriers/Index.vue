<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CarrierFormDialog from '../../../Components/CarrierFormDialog.vue';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    carriers: { type: Object, required: true },
    filters: { type: Object, required: true },
    regions: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const page = usePage();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

const statusOptions = [
    { value: '', label: 'Semua status' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

const isFormOpen = ref(false);
const editedCarrier = ref(null);

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

/** @param {object|null} carrier the carrier to edit, or null to add one */
function openForm(carrier) {
    editedCarrier.value = carrier;
    isFormOpen.value = true;
}

/**
 * Switches a carrier on or off from the list. A switched-off carrier stays on the purchases already sent with it; it
 * just is not offered for new ones. The update overwrites every detail it carries, so all of them are sent back as they are;
 * `contacts` is left out on purpose, which keeps the contacts untouched.
 *
 * @param {{name: string, phone: string|null, address: string|null, province: string|null, city: string|null, transit_days: number|null, notes: string|null, update_url: string}} carrier
 * @param {boolean} isActive
 */
function setActive(carrier, isActive) {
    router.put(
        carrier.update_url,
        {
            name: carrier.name,
            phone: carrier.phone,
            address: carrier.address,
            province: carrier.province,
            city: carrier.city,
            transit_days: carrier.transit_days,
            notes: carrier.notes,
            is_active: isActive,
        },
        { preserveScroll: true, preserveState: true },
    );
}

const canViewPurchases = computed(() => page.props.auth.permissions.includes('purchases.manage'));
</script>

<template>
    <StoreLayout title="Ekspedisi">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Ekspedisi" @click="openForm(null)" />
        </template>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nama, telepon, kota, atau kontak" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="statusFilter" :options="statusOptions" option-value="value" option-label="label" emit-value map-options behavior="menu" outlined dense class="filters__select" />
        </div>

        <div class="carriers">
            <table class="carriers__table">
                <thead>
                    <tr>
                        <th>Ekspedisi</th>
                        <th>Telepon</th>
                        <th>Kontak</th>
                        <th class="carriers__right">Lama kirim</th>
                        <th class="carriers__right">Nota</th>
                        <th>Aktif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="carriers.data.length === 0">
                        <td colspan="7" class="carriers__empty">Belum ada ekspedisi. Tambahkan ekspedisi agar nota pembelian bisa memilih ke mana barang dikirim.</td>
                    </tr>
                    <tr v-for="carrier in carriers.data" :key="carrier.id" :class="{ 'carriers__row--off': !carrier.is_active }">
                        <td>
                            <b>{{ carrier.name }}</b>
                            <small v-if="carrier.address">{{ carrier.address }}</small>
                            <small v-if="carrier.city || carrier.province">{{ [carrier.city, carrier.province].filter(Boolean).join(', ') }}</small>
                            <small v-if="carrier.notes">{{ carrier.notes }}</small>
                        </td>
                        <td>{{ carrier.phone ?? '—' }}</td>
                        <td>
                            <template v-if="carrier.contacts.length > 0">
                                <small v-for="contact in carrier.contacts" :key="contact.id" class="carriers__contact">
                                    <b>{{ contact.name }}</b>
                                    <template v-if="contact.phone"> · {{ contact.phone }}</template>
                                </small>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="carriers__right">{{ carrier.transit_days ? `${carrier.transit_days} hari` : '—' }}</td>
                        <td class="carriers__right">
                            <Link v-if="canViewPurchases && carrier.purchases_count > 0" :href="carrier.purchases_url" class="carriers__link">{{ carrier.purchases_count }}</Link>
                            <template v-else>{{ carrier.purchases_count }}</template>
                        </td>
                        <td><q-toggle :model-value="carrier.is_active" :aria-label="`Aktifkan ${carrier.name}`" @update:model-value="setActive(carrier, $event)" /></td>
                        <td class="carriers__actions"><q-btn flat dense no-caps icon="edit" label="Ubah" @click="openForm(carrier)" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="carriers.total > 0" class="pager">
            <span class="carriers__muted">{{ carriers.from }}–{{ carriers.to }} dari {{ carriers.total }} ekspedisi</span>
            <q-pagination
                v-if="carriers.last_page > 1"
                :model-value="carriers.current_page"
                :max="carriers.last_page"
                :max-pages="7"
                direction-links
                boundary-links
                color="primary"
                @update:model-value="applyFilters({ page: $event })"
            />
        </div>

        <CarrierFormDialog v-model="isFormOpen" :carrier="editedCarrier" :regions="regions" :store-url="urls.store" />
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

.carriers {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.carriers__table {
    width: 100%;
    border-collapse: collapse;
}

.carriers__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.carriers__table td {
    padding: 10px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.carriers__table td small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.carriers__table td small.carriers__contact b {
    font-weight: 600;
}

.carriers__link {
    color: var(--app-brand);
    font-weight: 600;
    text-decoration: none;
}

.carriers__table tr:last-child td {
    border-bottom: 0;
}

.carriers__table .carriers__right {
    text-align: right;
}

.carriers__row--off td:first-child b {
    color: var(--app-mute);
    text-decoration: line-through;
}

.carriers__actions {
    text-align: right;
    white-space: nowrap;
}

.carriers__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.carriers__empty {
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
