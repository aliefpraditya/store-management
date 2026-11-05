<script setup>
import { ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import UnitFormDialog from '../../../Components/UnitFormDialog.vue';
import { useConfirmDelete } from '../../../Composables/useConfirmDelete';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    /** What the store itself has, shown as the placeholder of the paper fields that were left blank. */
    store_letterhead: { type: Object, required: true },
    paper_widths: { type: Array, required: true },
    /** One page of the units, with the paginator's from, to and total. */
    units: { type: Object, required: true },
    unit_filters: { type: Object, required: true },
    unit_per_page_options: { type: Array, required: true },
    urls: { type: Object, required: true },
});

const page = usePage();

const form = useForm({ ...props.settings });

const deleteWithConfirmation = useConfirmDelete();

const isUnitDialogOpen = ref(false);
const editedUnit = ref(null);

function save() {
    form.put(props.urls.update, { preserveScroll: true });
}

const unitSearch = ref(props.unit_filters.search);

/**
 * Asks the server for another look at the unit list (a page, a sort, a search, a page size). Only the unit props are
 * fetched, so what is typed in the rest of the page stays as it is.
 *
 * @param {Record<string, string|number|undefined>} change what is different from the list as it is now
 */
function loadUnits(change = {}) {
    router.get(
        page.url.split('?')[0],
        {
            unit_search: unitSearch.value || undefined,
            unit_sort: props.unit_filters.sort,
            unit_direction: props.unit_filters.direction,
            unit_per_page: props.unit_filters.per_page,
            ...change,
        },
        { preserveState: true, preserveScroll: true, replace: true, only: ['units', 'unit_filters'] },
    );
}

let unitSearchTimer;

watch(unitSearch, () => {
    clearTimeout(unitSearchTimer);
    unitSearchTimer = setTimeout(() => loadUnits({ unit_page: undefined }), 300);
});

/** A click on a column heading sorts by it; a second click turns the order around. */
function sortUnitsBy(column) {
    const isSameColumn = props.unit_filters.sort === column;

    loadUnits({ unit_sort: column, unit_direction: isSameColumn && props.unit_filters.direction === 'asc' ? 'desc' : 'asc', unit_page: undefined });
}

const sortIcon = (column) => (props.unit_filters.sort === column ? (props.unit_filters.direction === 'asc' ? 'arrow_upward' : 'arrow_downward') : null);

function openUnitDialog(unit = null) {
    editedUnit.value = unit;
    isUnitDialogOpen.value = true;
}

function confirmDeleteUnit(unit) {
    deleteWithConfirmation(unit.delete_url, {
        title: 'Hapus satuan?',
        message: `Satuan ${unit.code} akan dihapus. Satuan yang masih dipakai barang tidak bisa dihapus.`,
    });
}
</script>

<template>
    <StoreLayout title="Pengaturan Toko">
        <template #actions>
            <q-btn color="primary" unelevated no-caps label="Simpan" :loading="form.processing" @click="save" />
        </template>

        <div class="settings">
            <section class="card">
                <h2>Nota dan cetakan</h2>
                <p class="hint">Dipakai di nota, surat jalan, bukti retur, dan laporan. Yang dikosongkan memakai data toko.</p>

                <q-input v-model="form.receipt_name" outlined dense maxlength="255" label="Nama di nota" :placeholder="store_letterhead.name" :error="!!form.errors.receipt_name" :error-message="form.errors.receipt_name" />
                <q-input v-model="form.receipt_address" outlined dense maxlength="500" label="Alamat" :placeholder="store_letterhead.address ?? ''" :error="!!form.errors.receipt_address" :error-message="form.errors.receipt_address" />
                <q-input v-model="form.receipt_phone" outlined dense maxlength="100" label="Telepon" :placeholder="store_letterhead.phone ?? ''" :error="!!form.errors.receipt_phone" :error-message="form.errors.receipt_phone" />
                <q-input v-model="form.receipt_footer" outlined dense type="textarea" autogrow maxlength="1000" label="Teks penutup nota" hint="Satu baris per baris teks. Nomor nota selalu dicetak di bawahnya." :error="!!form.errors.receipt_footer" :error-message="form.errors.receipt_footer" />

                <div class="field">
                    <span>Lebar kertas nota</span>
                    <q-btn-toggle v-model="form.paper_width" :options="paper_widths.map((width) => ({ label: `${width} mm`, value: width }))" no-caps unelevated toggle-color="primary" color="grey-3" text-color="grey-8" />
                    <small v-if="form.errors.paper_width" class="error">{{ form.errors.paper_width }}</small>
                </div>
            </section>

            <section class="card">
                <h2>Stok opname</h2>

                <q-input
                    v-model="form.large_variance_threshold"
                    type="number"
                    min="0"
                    step="any"
                    outlined
                    dense
                    prefix="Rp"
                    label="Ambang selisih besar"
                    hint="Selisih yang nilainya di atas ini wajib diberi alasan saat opname diposting. Kosongkan untuk tanpa batas."
                    :error="!!form.errors.large_variance_threshold"
                    :error-message="form.errors.large_variance_threshold"
                />

                <q-toggle v-model="form.lock_sales_during_opname" label="Kunci penjualan barang yang sedang dihitung opname" />
                <p class="hint">Barang yang ada di opname berstatus Menghitung atau Review tidak bisa dijual di kasir sampai opname selesai.</p>
            </section>
        </div>

        <section class="card units">
            <div class="units__head">
                <div>
                    <h2>Satuan</h2>
                    <p class="hint">Kode dan nama satuan barang. Satuan yang sudah dipakai barang tidak bisa dihapus.</p>
                </div>
                <q-btn color="primary" unelevated no-caps icon="add" label="Tambah Satuan" @click="openUnitDialog()" />
            </div>

            <q-input v-model="unitSearch" outlined dense clearable placeholder="Cari kode atau nama satuan" class="units__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>

            <div class="units__table-wrap">
                <table class="units__table">
                    <thead>
                        <tr>
                            <th v-for="column in [{ key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama' }, { key: 'products_count', label: 'Dipakai' }]" :key="column.key" class="units__sortable" @click="sortUnitsBy(column.key)">
                                {{ column.label }}<q-icon v-if="sortIcon(column.key)" :name="sortIcon(column.key)" size="14px" class="units__sort-icon" />
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="units.data.length === 0">
                            <td colspan="4" class="units__empty">{{ unit_filters.search ? 'Tidak ada satuan yang cocok.' : 'Belum ada satuan. Tambahkan satuan pertama.' }}</td>
                        </tr>
                        <tr v-for="unit in units.data" :key="unit.code">
                            <td><b>{{ unit.code }}</b></td>
                            <td>{{ unit.name }}</td>
                            <td>
                                <span v-if="unit.products_count > 0">{{ unit.products_count }} barang</span>
                                <span v-else class="hint">Belum dipakai</span>
                            </td>
                            <td class="units__actions">
                                <q-btn flat round dense icon="edit" color="grey-7" :aria-label="`Ubah ${unit.code}`" @click="openUnitDialog(unit)" />
                                <q-btn v-if="unit.can_delete" flat round dense icon="delete_outline" color="negative" :aria-label="`Hapus ${unit.code}`" @click="confirmDeleteUnit(unit)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="units.total > 0" class="units__pager">
                <span class="hint">{{ units.from }}–{{ units.to }} dari {{ units.total }} satuan</span>
                <div class="units__pager-controls">
                    <q-select
                        :model-value="unit_filters.per_page"
                        :options="unit_per_page_options"
                        outlined
                        dense
                        behavior="menu"
                        :option-label="(option) => `${option} per halaman`"
                        class="units__per-page"
                        aria-label="Jumlah satuan per halaman"
                        @update:model-value="loadUnits({ unit_per_page: $event, unit_page: undefined })"
                    />
                    <q-pagination
                        v-if="units.last_page > 1"
                        :model-value="units.current_page"
                        :max="units.last_page"
                        :max-pages="5"
                        direction-links
                        boundary-links
                        color="primary"
                        @update:model-value="loadUnits({ unit_page: $event })"
                    />
                </div>
            </div>
        </section>

        <UnitFormDialog v-model="isUnitDialogOpen" :unit="editedUnit" :create-url="urls.unit_create" />
    </StoreLayout>
</template>

<style scoped>
.settings {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
    gap: 16px;
    align-items: start;
    margin-bottom: 16px;
}

.card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0;
    font-size: 16px;
}

.hint {
    margin: 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field > span {
    color: var(--app-ink-soft);
    font-size: 13px;
}

.error {
    color: #be123c;
}

.units__head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.units__search {
    max-width: 360px;
}

.units__sortable {
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}

.units__sort-icon {
    margin-left: 4px;
    vertical-align: -2px;
}

.units__pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.units__pager-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.units__per-page {
    width: 170px;
}

.units__table-wrap {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
}

.units__table {
    width: 100%;
    border-collapse: collapse;
}

.units__table th {
    padding: 10px 14px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.units__table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.units__table tr:last-child td {
    border-bottom: 0;
}

.units__actions {
    min-width: 92px;
    text-align: right;
    white-space: nowrap;
}

.units__empty {
    padding: 24px 14px !important;
    color: var(--app-mute);
    text-align: center;
}
</style>
