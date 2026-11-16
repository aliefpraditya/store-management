<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import CentralLayout from '../../../Layouts/CentralLayout.vue';

defineProps({
    stores: { type: Array, required: true },
    create_url: { type: String, required: true },
});

const $q = useQuasar();

/** Id of the store whose request is still running, so its buttons cannot be pressed twice. */
const busyStoreId = ref(null);

/**
 * Sends one store request and keeps that store's controls disabled until it finishes.
 * @param {object} store
 * @param {(options: object) => void} send
 */
function sendForStore(store, send) {
    busyStoreId.value = store.id;
    send({ preserveScroll: true, onFinish: () => (busyStoreId.value = null) });
}

/**
 * Deactivating blocks every user of the store at once, so it asks first;
 * activating again is harmless and goes straight through.
 */
function toggleActive(store, isActive) {
    const save = () => sendForStore(store, (options) => router.patch(store.status_url, { is_active: isActive }, options));

    if (isActive) {
        save();

        return;
    }

    $q.dialog({
        title: 'Nonaktifkan toko?',
        message: `Semua user ${store.name} tidak akan bisa masuk sampai toko diaktifkan kembali.`,
        ok: { label: 'Nonaktifkan', color: 'negative', unelevated: true, noCaps: true },
        cancel: { label: 'Batal', flat: true, noCaps: true },
        persistent: true,
    }).onOk(save);
}

function retryProvisioning(store) {
    sendForStore(store, (options) => router.post(store.provision_url, {}, options));
}
</script>

<template>
    <CentralLayout title="Master Toko">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Tambah Toko" @click="router.visit(create_url)" />
        </template>

        <div class="stores">
            <table class="stores__table">
                <thead>
                    <tr>
                        <th>Toko</th>
                        <th>Database</th>
                        <th>Nota</th>
                        <th>Status</th>
                        <th class="stores__center">Aktif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="store in stores" :key="store.id">
                        <td>
                            <b>{{ store.name }}</b>
                            <small>{{ store.code }}</small>
                        </td>
                        <td>
                            {{ store.db_name }}
                            <small v-if="store.schema_version" class="stores__muted">{{ store.schema_version }}</small>
                        </td>
                        <td>{{ store.receipt_prefix }}</td>
                        <td>
                            <span class="stores__status" :class="`stores__status--${store.status.value}`">
                                <i></i>{{ store.status.label }}
                            </span>
                            <small v-if="store.failure_message" class="stores__failure">{{ store.failure_message }}</small>
                        </td>
                        <td class="stores__center">
                            <q-toggle
                                v-if="store.can_toggle_active"
                                :model-value="store.is_active"
                                :disable="busyStoreId === store.id"
                                color="positive"
                                dense
                                :aria-label="`Aktif: ${store.name}`"
                                @update:model-value="toggleActive(store, $event)"
                            />
                            <span v-else class="stores__muted">—</span>
                        </td>
                        <td class="stores__actions">
                            <q-btn
                                v-if="store.can_retry"
                                outline
                                no-caps
                                dense
                                color="primary"
                                label="Coba lagi"
                                class="q-px-sm"
                                :loading="busyStoreId === store.id"
                                :disable="busyStoreId !== null"
                                @click="retryProvisioning(store)"
                            />
                            <q-btn flat round dense icon="edit" color="grey-7" :aria-label="`Edit ${store.name}`" @click="router.visit(store.edit_url)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </CentralLayout>
</template>

<style scoped>
.stores {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.stores__table {
    width: 100%;
    border-collapse: collapse;
}

.stores__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.stores__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.stores__table tr:last-child td {
    border-bottom: 0;
}

.stores__table td b,
.stores__table td small {
    display: block;
}

.stores__table td small,
.stores__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.stores__center {
    text-align: center !important;
}

.stores__actions {
    white-space: nowrap;
    text-align: right;
}

.stores__status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 10px;
    border-radius: 99px;
    background: #f2f3f5;
    color: var(--app-ink-soft);
    font-size: 12px;
    font-weight: 600;
}

.stores__status i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.stores__status--active {
    background: #e7f8f1;
    color: #059669;
}

.stores__status--provisioning {
    background: #e8f0fe;
    color: #2563eb;
}

.stores__status--failed {
    background: #fde8ee;
    color: #be123c;
}

.stores__table td small.stores__failure {
    max-width: 320px;
    margin-top: 4px;
    color: #be123c;
}
</style>
