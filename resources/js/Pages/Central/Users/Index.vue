<script setup>
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import CentralLayout from '../../../Layouts/CentralLayout.vue';

defineProps({
    users: { type: Array, required: true },
    create_url: { type: String, required: true },
});

const $q = useQuasar();

/**
 * Deactivating locks the person out at once (a cashier mid-shift included), so it asks first;
 * activating again is harmless and goes straight through.
 */
function toggleActive(user, isActive) {
    const save = () => router.patch(user.status_url, { is_active: isActive }, { preserveScroll: true });

    if (isActive) {
        save();

        return;
    }

    $q.dialog({
        title: 'Nonaktifkan user?',
        message: `${user.name} tidak akan bisa masuk lagi sampai diaktifkan kembali.`,
        ok: { label: 'Nonaktifkan', color: 'negative', unelevated: true, noCaps: true },
        cancel: { label: 'Batal', flat: true, noCaps: true },
        persistent: true,
    }).onOk(save);
}
</script>

<template>
    <CentralLayout title="User & Akses">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Tambah User" @click="router.visit(create_url)" />
        </template>

        <div class="users">
            <table class="users__table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Peran</th>
                        <th>Toko</th>
                        <th class="users__center">Aktif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td>
                            <b>{{ user.name }}</b>
                            <small>{{ user.username }}</small>
                        </td>
                        <td>
                            <span v-if="user.role" class="users__role" :class="{ 'users__role--super': !user.role.is_store_role }">
                                {{ user.role.label }}
                            </span>
                            <span v-else class="users__muted">Belum ada toko</span>
                        </td>
                        <td>{{ user.store?.name ?? (user.role && !user.role.is_store_role ? 'Semua' : '—') }}</td>
                        <td class="users__center">
                            <q-toggle
                                :model-value="user.is_active"
                                :disable="user.is_self"
                                color="positive"
                                dense
                                :aria-label="`Aktif: ${user.name}`"
                                @update:model-value="toggleActive(user, $event)"
                            >
                                <q-tooltip v-if="user.is_self">Akun sendiri tidak bisa dinonaktifkan</q-tooltip>
                            </q-toggle>
                        </td>
                        <td class="users__center">
                            <q-btn flat round dense icon="edit" color="grey-7" :aria-label="`Edit ${user.name}`" @click="router.visit(user.edit_url)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </CentralLayout>
</template>

<style scoped>
.users {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.users__table {
    width: 100%;
    border-collapse: collapse;
}

.users__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.users__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
}

.users__table tr:last-child td {
    border-bottom: 0;
}

.users__table td b,
.users__table td small {
    display: block;
}

.users__table td small,
.users__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.users__center {
    text-align: center !important;
}

.users__role {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 99px;
    background: #f2f3f5;
    color: var(--app-ink-soft);
    font-size: 12px;
    font-weight: 600;
}

.users__role--super {
    background: var(--app-brand-soft);
    color: var(--app-brand);
}
</style>
