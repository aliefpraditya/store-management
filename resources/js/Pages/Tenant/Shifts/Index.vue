<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    shifts: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    differences: { type: Array, required: true },
    pending_difference_count: { type: Number, required: true },
});

const page = usePage();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const difference = ref(props.filters.difference ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const differenceChipClass = { none: 'chip--off', pending_approval: 'chip--warn', approved: 'chip--ok', rejected: 'chip--bad' };

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });
const timeFormat = new Intl.DateTimeFormat('id-ID', { timeStyle: 'short' });

function applyFilters(extra = {}) {
    router.get(
        page.url.split('?')[0],
        { search: search.value || undefined, status: status.value || undefined, difference: difference.value || undefined, from: from.value || undefined, to: to.value || undefined, ...extra },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch([status, difference, from, to], () => applyFilters());
</script>

<template>
    <StoreLayout title="Rekap Shift">
        <div v-if="pending_difference_count > 0" class="waiting">
            <q-icon name="hourglass_top" />
            <span><b>{{ pending_difference_count }}</b> selisih kas menunggu keputusan Anda.</span>
            <q-btn flat no-caps dense color="primary" label="Lihat" @click="difference = 'pending_approval'" />
        </div>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nama kasir" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="status" :options="[{ value: '', label: 'Semua shift' }, ...statuses]" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
            <q-select v-model="difference" :options="[{ value: '', label: 'Semua selisih' }, ...differences]" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
            <DateInput v-model="from" label="Dari" outlined dense clearable class="filters__date" />
            <DateInput v-model="to" label="Sampai" outlined dense clearable class="filters__date" />
        </div>

        <div class="shifts">
            <table class="shifts__table">
                <thead>
                    <tr>
                        <th>Shift</th>
                        <th>Tanggal</th>
                        <th>Kasir</th>
                        <th>Jam</th>
                        <th class="shifts__right">Penjualan</th>
                        <th class="shifts__right">Selisih kas</th>
                        <th>Keputusan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="shifts.data.length === 0">
                        <td colspan="7" class="shifts__empty">Belum ada shift.</td>
                    </tr>
                    <tr v-for="shift in shifts.data" :key="shift.id" class="shifts__row" @click="router.visit(shift.show_url)">
                        <td>
                            <b>{{ shift.label }}</b>
                            <small class="shifts__muted">{{ shift.register_name }}</small>
                        </td>
                        <td>{{ dateFormat.format(new Date(shift.business_date)) }}</td>
                        <td>{{ shift.cashier_name }}</td>
                        <td>
                            {{ timeFormat.format(new Date(shift.opened_at)) }}
                            <template v-if="shift.closed_at">– {{ timeFormat.format(new Date(shift.closed_at)) }}</template>
                            <span v-else class="chip chip--ok">{{ shift.status_label }}</span>
                        </td>
                        <td class="shifts__right">
                            {{ formatRupiah(shift.sales_total) }}
                            <small class="shifts__muted">{{ shift.sale_count }} nota</small>
                        </td>
                        <td class="shifts__right" :class="{ 'shifts__minus': Number(shift.cash_difference) < 0 }">
                            <template v-if="shift.cash_difference !== null">{{ formatRupiah(shift.cash_difference) }}</template>
                            <template v-else>–</template>
                        </td>
                        <td>
                            <span v-if="shift.status !== 'open'" class="chip" :class="differenceChipClass[shift.difference_status]">{{ shift.difference_status_label }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="shifts.total > 0" class="pager">
            <span class="shifts__muted">{{ shifts.from }}–{{ shifts.to }} dari {{ shifts.total }} shift</span>
            <q-pagination
                v-if="shifts.last_page > 1"
                :model-value="shifts.current_page"
                :max="shifts.last_page"
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
    flex: 2 1 220px;
}

.filters__select {
    flex: 1 1 180px;
}

.filters__date {
    flex: 1 1 150px;
}

.shifts {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.shifts__table {
    width: 100%;
    border-collapse: collapse;
}

.shifts__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.shifts__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.shifts__table tr:last-child td {
    border-bottom: 0;
}

.shifts__table .shifts__right {
    text-align: right;
}

.shifts__row {
    cursor: pointer;
}

.shifts__row:hover {
    background: #fafafb;
}

.shifts__muted {
    display: block;
    color: var(--app-mute);
    font-size: 12.5px;
}

.shifts__minus {
    color: #be123c;
}

.shifts__empty {
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
