<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';

defineProps({
    /** @type {{id: number, name: string, is_active: boolean, update_url: string}[]} */
    drivers: { type: Array, required: true },
    createUrl: { type: String, required: true },
});

const $q = useQuasar();

const newDriver = useForm({ name: '' });

function addDriver(createUrl) {
    newDriver.post(createUrl, { preserveScroll: true, preserveState: true, onSuccess: () => newDriver.reset() });
}

/**
 * Renames a driver or switches them on or off. A switched-off driver is not offered when a delivery note is shipped, and
 * the notes they already delivered keep their name.
 *
 * @param {{name: string, is_active: boolean, update_url: string}} driver
 * @param {{name?: string, is_active?: boolean}} change
 */
function updateDriver(driver, change) {
    router.put(
        driver.update_url,
        { name: driver.name, is_active: driver.is_active, ...change },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => $q.notify({ type: 'negative', message: Object.values(errors)[0], position: 'top' }),
        },
    );
}
</script>

<template>
    <section class="card">
        <h2>Sopir</h2>
        <p class="hint">Dipilih saat surat jalan ditandai dikirim. Sopir tidak dihapus, hanya dinonaktifkan, supaya surat jalan lama tetap memuat namanya.</p>

        <p v-if="drivers.length === 0" class="hint">Belum ada sopir.</p>

        <div v-for="driver in drivers" :key="driver.id" class="driver" :class="{ 'driver--off': !driver.is_active }">
            <span class="driver__name">
                {{ driver.name }}
                <q-icon name="edit" size="14px" color="grey-6" />
                <q-popup-edit v-slot="scope" :model-value="driver.name" buttons label-set="Simpan" label-cancel="Batal" @save="(name) => updateDriver(driver, { name })">
                    <q-input v-model="scope.value" dense autofocus maxlength="100" @keyup.enter="scope.set" />
                </q-popup-edit>
            </span>
            <q-toggle :model-value="driver.is_active" :aria-label="`Aktifkan ${driver.name}`" @update:model-value="updateDriver(driver, { is_active: $event })" />
        </div>

        <q-form class="driver__add" @submit.prevent="addDriver(createUrl)">
            <q-input v-model="newDriver.name" outlined dense label="Nama sopir baru" maxlength="100" :error="!!newDriver.errors.name" :error-message="newDriver.errors.name" class="driver__input" />
            <q-btn type="submit" color="primary" unelevated no-caps icon="add" label="Tambah" :loading="newDriver.processing" />
        </q-form>
    </section>
</template>

<style scoped>
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

.driver {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 4px 0;
    border-bottom: 1px solid var(--app-line);
}

.driver--off .driver__name {
    color: var(--app-mute);
    text-decoration: line-through;
}

.driver__name {
    flex: 1;
    font-weight: 600;
    cursor: pointer;
}

.driver__add {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.driver__input {
    flex: 1;
}
</style>
