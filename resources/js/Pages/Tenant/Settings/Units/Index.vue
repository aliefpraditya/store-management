<script setup>
import { ref } from 'vue';
import UnitFormDialog from '../../../../Components/UnitFormDialog.vue';
import { useConfirmDelete } from '../../../../Composables/useConfirmDelete';
import StoreLayout from '../../../../Layouts/StoreLayout.vue';

defineProps({
    units: { type: Array, required: true },
    create_url: { type: String, required: true },
});

const deleteWithConfirmation = useConfirmDelete();

const isDialogOpen = ref(false);
const editedUnit = ref(null);

function openDialog(unit = null) {
    editedUnit.value = unit;
    isDialogOpen.value = true;
}

function confirmDelete(unit) {
    deleteWithConfirmation(unit.delete_url, {
        title: 'Hapus satuan?',
        message: `Satuan ${unit.code} akan dihapus. Satuan yang masih dipakai barang tidak bisa dihapus.`,
    });
}
</script>

<template>
    <StoreLayout title="Satuan">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Tambah Satuan" @click="openDialog()" />
        </template>

        <div class="units">
            <table class="units__table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Dipakai</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="units.length === 0">
                        <td colspan="4" class="units__empty">Belum ada satuan. Tambahkan satuan pertama.</td>
                    </tr>
                    <tr v-for="unit in units" :key="unit.code">
                        <td><b>{{ unit.code }}</b></td>
                        <td>{{ unit.name }}</td>
                        <td>
                            <span v-if="unit.products_count > 0">{{ unit.products_count }} barang</span>
                            <span v-else class="units__muted">Belum dipakai</span>
                        </td>
                        <td class="units__actions">
                            <q-btn flat round dense icon="edit" color="grey-7" :aria-label="`Ubah ${unit.code}`" @click="openDialog(unit)" />
                            <q-btn
                                v-if="unit.can_delete"
                                flat
                                round
                                dense
                                icon="delete_outline"
                                color="negative"
                                :aria-label="`Hapus ${unit.code}`"
                                @click="confirmDelete(unit)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <UnitFormDialog v-model="isDialogOpen" :unit="editedUnit" :create-url="create_url" />
    </StoreLayout>
</template>

<style scoped>
.units {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.units__table {
    width: 100%;
    border-collapse: collapse;
}

.units__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.units__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.units__table tr:last-child td {
    border-bottom: 0;
}

.units__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.units__actions {
    min-width: 92px;
    text-align: right;
    white-space: nowrap;
}

.units__empty {
    padding: 28px 16px !important;
    color: var(--app-mute);
    text-align: center;
}
</style>
