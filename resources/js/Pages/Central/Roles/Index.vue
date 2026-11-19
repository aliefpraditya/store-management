<script setup>
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import CentralLayout from '../../../Layouts/CentralLayout.vue';

defineProps({
    roles: { type: Array, required: true },
    permission_count: { type: Number, required: true },
    create_url: { type: String, required: true },
});

const $q = useQuasar();

/**
 * Deleting is only offered for a role nobody holds (the server refuses otherwise), but it still asks first.
 */
function confirmDelete(role) {
    $q.dialog({
        title: 'Hapus peran?',
        message: `Peran ${role.label} akan dihapus.`,
        ok: { label: 'Hapus', color: 'negative', unelevated: true, noCaps: true },
        cancel: { label: 'Batal', flat: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.delete(role.delete_url));
}
</script>

<template>
    <CentralLayout title="Peran & Izin">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Tambah Peran" @click="router.visit(create_url)" />
        </template>

        <div class="roles">
            <table class="roles__table">
                <thead>
                    <tr>
                        <th>Peran</th>
                        <th>Izin</th>
                        <th class="roles__center">User</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="role in roles" :key="role.id">
                        <td>
                            <b>{{ role.label }}</b>
                            <small v-if="role.is_system">Bawaan sistem</small>
                        </td>
                        <td>{{ role.permission_names.length }} dari {{ permission_count }}</td>
                        <td class="roles__center">{{ role.users_count }}</td>
                        <td class="roles__actions">
                            <q-btn flat round dense icon="edit" color="grey-7" :aria-label="`Edit ${role.label}`" @click="router.visit(role.edit_url)" />
                            <q-btn
                                v-if="!role.is_system"
                                flat
                                round
                                dense
                                icon="delete_outline"
                                color="grey-7"
                                :disable="role.users_count > 0"
                                :aria-label="`Hapus ${role.label}`"
                                @click="confirmDelete(role)"
                            >
                                <q-tooltip v-if="role.users_count > 0">Masih dipakai user</q-tooltip>
                            </q-btn>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </CentralLayout>
</template>

<style scoped>
.roles {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.roles__table {
    width: 100%;
    border-collapse: collapse;
}

.roles__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.roles__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
}

.roles__table tr:last-child td {
    border-bottom: 0;
}

.roles__table td b,
.roles__table td small {
    display: block;
}

.roles__table td small {
    color: var(--app-mute);
    font-size: 12px;
}

.roles__center {
    text-align: center !important;
}

.roles__actions {
    text-align: right;
    white-space: nowrap;
}
</style>
