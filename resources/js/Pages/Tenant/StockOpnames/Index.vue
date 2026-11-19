<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    opnames: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    create_url: { type: String, required: true },
    can_create: { type: Boolean, required: true },
});

const page = usePage();

const status = ref(props.filters.status);

const statusChipClass = {
    counting: 'chip--warn',
    review: 'chip--warn',
    posted: 'chip--ok',
    cancelled: 'chip--bad',
};

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

function applyFilters(extra = {}) {
    router.get(page.url.split('?')[0], { status: status.value || undefined, ...extra }, { preserveState: true, replace: true, preserveScroll: true });
}

watch(status, () => applyFilters());
</script>

<template>
    <StoreLayout title="Stok Opname">
        <template #actions>
            <q-btn v-if="can_create" color="primary" unelevated no-caps icon="add" label="Opname Baru" @click="router.visit(create_url)" />
        </template>

        <div class="filters">
            <q-select
                v-model="status"
                :options="[{ value: '', label: 'Semua status' }, ...statuses]"
                option-value="value"
                option-label="label"
                emit-value
                map-options
                outlined
                dense
                class="filters__select"
            />
        </div>

        <div class="opnames">
            <table class="opnames__table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Gudang</th>
                        <th>Cakupan</th>
                        <th>Alasan</th>
                        <th>Dihitung</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="opnames.data.length === 0">
                        <td colspan="7" class="opnames__empty">Belum ada opname. Buat opname baru untuk memasukkan stok awal, barang datang, atau koreksi stok.</td>
                    </tr>
                    <tr v-for="opname in opnames.data" :key="opname.id" class="opnames__row" @click="router.visit(opname.show_url)">
                        <td><b>{{ opname.number }}</b></td>
                        <td>{{ dateFormat.format(new Date(opname.opname_date)) }}</td>
                        <td>{{ opname.warehouse }}</td>
                        <td>{{ opname.scope_label }}</td>
                        <td>{{ opname.reason_label ?? '—' }}</td>
                        <td>
                            <template v-if="opname.items_count > 0">{{ opname.counted_items_count }} / {{ opname.items_count }}</template>
                            <span v-else class="opnames__muted">—</span>
                        </td>
                        <td><span class="chip" :class="statusChipClass[opname.status]">{{ opname.status_label }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="opnames.total > 0" class="pager">
            <span class="opnames__muted">{{ opnames.from }}–{{ opnames.to }} dari {{ opnames.total }} opname</span>
            <q-pagination
                v-if="opnames.last_page > 1"
                :model-value="opnames.current_page"
                :max="opnames.last_page"
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
    margin-bottom: 14px;
}

.filters__select {
    flex: 0 1 220px;
}

.opnames {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.opnames__table {
    width: 100%;
    border-collapse: collapse;
}

.opnames__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.opnames__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.opnames__table tr:last-child td {
    border-bottom: 0;
}

.opnames__row {
    cursor: pointer;
}

.opnames__row:hover {
    background: #fafafb;
}

.opnames__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.opnames__empty {
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
