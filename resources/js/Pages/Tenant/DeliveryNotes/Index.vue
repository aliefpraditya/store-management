<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import { deliveryNoteStatusChipClass } from '../../../Composables/useDeliveryNoteStatus';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    notes: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    create_url: { type: String, required: true },
});

const page = usePage();

const status = ref(props.filters.status);
const search = ref(props.filters.search);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

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
    <StoreLayout title="Surat Jalan">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Surat Jalan Baru" @click="router.visit(create_url)" />
        </template>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nomor, penerima, atau nomor nota" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="status" :options="[{ value: '', label: 'Semua status' }, ...statuses]" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
            <DateInput v-model="from" label="Dari" outlined dense clearable class="filters__date" />
            <DateInput v-model="to" label="Sampai" outlined dense clearable class="filters__date" />
        </div>

        <div class="notes">
            <table class="notes__table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Penerima</th>
                        <th>Nota</th>
                        <th class="notes__right">Barang</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="notes.data.length === 0">
                        <td colspan="6" class="notes__empty">Belum ada surat jalan. Buat dari nota yang barangnya diantar ke pelanggan.</td>
                    </tr>
                    <tr v-for="note in notes.data" :key="note.id" class="notes__row" @click="router.visit(note.show_url)">
                        <td><b>{{ note.number }}</b></td>
                        <td>{{ dateFormat.format(new Date(note.business_date)) }}</td>
                        <td>{{ note.recipient_name }}</td>
                        <td>{{ [note.sale_number, ...note.other_sale_numbers].join(', ') }}</td>
                        <td class="notes__right">{{ note.items_count }}</td>
                        <td><span class="chip" :class="deliveryNoteStatusChipClass[note.status]">{{ note.status_label }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="notes.total > 0" class="pager">
            <span class="notes__muted">{{ notes.from }}–{{ notes.to }} dari {{ notes.total }} surat jalan</span>
            <q-pagination
                v-if="notes.last_page > 1"
                :model-value="notes.current_page"
                :max="notes.last_page"
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

.filters__date {
    flex: 1 1 150px;
}

.notes {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.notes__table {
    width: 100%;
    border-collapse: collapse;
}

.notes__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.notes__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.notes__table tr:last-child td {
    border-bottom: 0;
}

.notes__table .notes__right {
    text-align: right;
}

.notes__row {
    cursor: pointer;
}

.notes__row:hover {
    background: #fafafb;
}

.notes__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.notes__empty {
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
