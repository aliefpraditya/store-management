<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';

defineProps({
    /** @type {{id: number, name: string, is_system: boolean, is_active: boolean, update_url: string}[]} */
    reasons: { type: Array, required: true },
    createUrl: { type: String, required: true },
});

const $q = useQuasar();

const newReason = useForm({ name: '' });

function addReason(createUrl) {
    newReason.post(createUrl, { preserveScroll: true, preserveState: true, onSuccess: () => newReason.reset() });
}

/**
 * Renames a reason or switches it on or off. A switched-off reason stays on the opnames that used it; it just is not
 * offered for new variances. The reasons every store starts with cannot be switched off.
 *
 * @param {{name: string, is_active: boolean, update_url: string}} reason
 * @param {{name?: string, is_active?: boolean}} change
 */
function updateReason(reason, change) {
    router.put(
        reason.update_url,
        { name: reason.name, is_active: reason.is_active, ...change },
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
        <h2>Alasan selisih opname</h2>
        <p class="hint">Dipilih di tiap barang yang selisih saat opname. Alasan tidak dihapus, hanya dinonaktifkan, supaya opname lama tetap punya alasannya. Rusak meminta keputusan barang rusak; alasan yang Anda tambah hanya dicatat.</p>

        <div v-for="reason in reasons" :key="reason.id" class="reason" :class="{ 'reason--off': !reason.is_active }">
            <span class="reason__name">
                {{ reason.name }}
                <q-icon name="edit" size="14px" color="grey-6" />
                <q-popup-edit v-slot="scope" :model-value="reason.name" buttons label-set="Simpan" label-cancel="Batal" @save="(name) => updateReason(reason, { name })">
                    <q-input v-model="scope.value" dense autofocus maxlength="100" @keyup.enter="scope.set" />
                </q-popup-edit>
            </span>
            <small v-if="reason.is_system" class="reason__tag">bawaan</small>
            <q-toggle :model-value="reason.is_active" :disable="reason.is_system" :aria-label="`Aktifkan ${reason.name}`" @update:model-value="updateReason(reason, { is_active: $event })" />
        </div>

        <q-form class="reason__add" @submit.prevent="addReason(createUrl)">
            <q-input v-model="newReason.name" outlined dense label="Alasan baru" maxlength="100" :error="!!newReason.errors.name" :error-message="newReason.errors.name" class="reason__input" />
            <q-btn type="submit" color="primary" unelevated no-caps icon="add" label="Tambah" :loading="newReason.processing" />
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

.reason {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 4px 0;
    border-bottom: 1px solid var(--app-line);
}

.reason--off .reason__name {
    color: var(--app-mute);
    text-decoration: line-through;
}

.reason__name {
    flex: 1;
    font-weight: 600;
    cursor: pointer;
}

.reason__tag {
    color: var(--app-mute);
}

.reason__add {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.reason__input {
    flex: 1;
}
</style>
