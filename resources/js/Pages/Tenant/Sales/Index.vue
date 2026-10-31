<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    sales: { type: Object, required: true },
    totals: { type: Object, required: true },
    filters: { type: Object, required: true },
    cashiers: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const page = usePage();

const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const search = ref(props.filters.search ?? '');
const cashier = ref(props.filters.cashier_id);
const status = ref(props.filters.status ?? '');

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

/**
 * Empty dates are sent as empty values on purpose: a missing date means "today" to the server.
 */
function applyFilters(extra = {}) {
    router.get(
        page.url.split('?')[0],
        { from: from.value ?? '', to: to.value ?? '', search: search.value || undefined, cashier: cashier.value || undefined, status: status.value || undefined, ...extra },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch([from, to, cashier, status], () => applyFilters());
</script>

<template>
    <StoreLayout title="Riwayat Nota">
        <div class="filters">
            <DateInput v-model="from" label="Dari" outlined dense clearable class="filters__date" />
            <DateInput v-model="to" label="Sampai" outlined dense clearable class="filters__date" />
            <q-input v-model="search" outlined dense clearable placeholder="Cari nomor atau nama pelanggan" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select
                v-model="cashier"
                :options="[{ id: null, name: 'Semua kasir' }, ...cashiers]"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                outlined
                dense
                class="filters__select"
            />
            <q-select v-model="status" :options="[{ value: '', label: 'Semua status' }, ...statuses]" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
        </div>

        <div class="totals">
            <span>{{ totals.receipt_count }} nota selesai</span>
            <b>{{ formatRupiah(totals.total) }}</b>
        </div>

        <div class="receipts">
            <table class="receipts__table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Waktu</th>
                        <th>Kasir</th>
                        <th>Pelanggan</th>
                        <th class="receipts__right">Barang</th>
                        <th>Pembayaran</th>
                        <th class="receipts__right">Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="sales.data.length === 0">
                        <td colspan="8" class="receipts__empty">Tidak ada nota pada pilihan ini.</td>
                    </tr>
                    <tr v-for="sale in sales.data" :key="sale.id" class="receipts__row" @click="router.visit(sale.show_url)">
                        <td><b>{{ sale.number }}</b></td>
                        <td>{{ dateTimeFormat.format(new Date(sale.sold_at)) }}</td>
                        <td>{{ sale.cashier_name }}</td>
                        <td>{{ sale.customer_name ?? '—' }}</td>
                        <td class="receipts__right">{{ sale.items_count }}</td>
                        <td>{{ sale.payment_methods.join(', ') }}</td>
                        <td class="receipts__right"><b>{{ formatRupiah(sale.total) }}</b></td>
                        <td><span class="chip" :class="sale.status === 'completed' ? 'chip--ok' : 'chip--bad'">{{ sale.status_label }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="sales.total > 0" class="pager">
            <span class="receipts__muted">{{ sales.from }}–{{ sales.to }} dari {{ sales.total }} nota</span>
            <q-pagination
                v-if="sales.last_page > 1"
                :model-value="sales.current_page"
                :max="sales.last_page"
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
    margin-bottom: 12px;
}

.filters__date {
    flex: 1 1 150px;
}

.filters__search {
    flex: 2 1 260px;
}

.filters__select {
    flex: 1 1 160px;
}

.totals {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 12px;
    color: var(--app-ink-soft);
}

.totals b {
    color: var(--app-ink);
    font-size: 20px;
}

.receipts {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.receipts__table {
    width: 100%;
    border-collapse: collapse;
}

.receipts__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.receipts__table td {
    padding: 11px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.receipts__table tr:last-child td {
    border-bottom: 0;
}

.receipts__table .receipts__right {
    text-align: right;
    white-space: nowrap;
}

.receipts__row {
    cursor: pointer;
}

.receipts__row:hover {
    background: #fafafb;
}

.receipts__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.receipts__empty {
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
