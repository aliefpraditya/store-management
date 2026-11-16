<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { returnStatusChipClass } from '../../../Composables/useReturnStatus';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    returns: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    pending_count: { type: Number, required: true },
    create_url: { type: String, required: true },
});

const page = usePage();

const status = ref(props.filters.status);
const search = ref(props.filters.search);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

function applyFilters(extra = {}) {
    router.get(
        page.url.split('?')[0],
        { status: status.value || undefined, search: search.value || undefined, from: from.value || undefined, to: to.value || undefined, ...extra },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch([status, from, to], () => applyFilters());
</script>

<template>
    <StoreLayout title="Retur">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Retur Baru" @click="router.visit(create_url)" />
        </template>

        <div v-if="pending_count > 0" class="waiting">
            <q-icon name="hourglass_top" />
            <span><b>{{ pending_count }}</b> retur menunggu persetujuan Anda.</span>
            <q-btn flat no-caps dense color="primary" label="Lihat" @click="status = 'pending'" />
        </div>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nomor retur, nomor nota, atau pelanggan" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="status" :options="[{ value: '', label: 'Semua status' }, ...statuses]" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
            <DateInput v-model="from" label="Dari" outlined dense clearable class="filters__date" />
            <DateInput v-model="to" label="Sampai" outlined dense clearable class="filters__date" />
        </div>

        <div class="returns">
            <table class="returns__table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Waktu</th>
                        <th>Nota</th>
                        <th>Kasir</th>
                        <th>Nilai retur</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="returns.data.length === 0">
                        <td colspan="6" class="returns__empty">Belum ada retur.</td>
                    </tr>
                    <tr v-for="saleReturn in returns.data" :key="saleReturn.id" class="returns__row" @click="router.visit(saleReturn.show_url)">
                        <td><b>{{ saleReturn.number }}</b></td>
                        <td>{{ dateTimeFormat.format(new Date(saleReturn.returned_at)) }}</td>
                        <td>{{ saleReturn.sale_number }}</td>
                        <td>{{ saleReturn.cashier_name }}</td>
                        <td>{{ formatRupiah(saleReturn.credit_amount) }} <small class="returns__muted">{{ saleReturn.resolution_label }}</small></td>
                        <td><span class="chip" :class="returnStatusChipClass[saleReturn.status]">{{ saleReturn.status_label }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="returns.total > 0" class="pager">
            <span class="returns__muted">{{ returns.from }}–{{ returns.to }} dari {{ returns.total }} retur</span>
            <q-pagination
                v-if="returns.last_page > 1"
                :model-value="returns.current_page"
                :max="returns.last_page"
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
.waiting {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    padding: 10px 14px;
    border: 1px solid #fcd9a8;
    border-radius: var(--app-radius);
    background: #fff7ec;
    color: #9a5b00;
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
    flex: 1 1 190px;
}

.filters__date {
    flex: 1 1 150px;
}

.returns {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.returns__table {
    width: 100%;
    border-collapse: collapse;
}

.returns__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.returns__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.returns__table tr:last-child td {
    border-bottom: 0;
}

.returns__row {
    cursor: pointer;
}

.returns__row:hover {
    background: #fafafb;
}

.returns__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.returns__empty {
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
