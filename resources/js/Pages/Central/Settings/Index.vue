<script setup>
import { useForm } from '@inertiajs/vue3';
import CentralLayout from '../../../Layouts/CentralLayout.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    limits: { type: Object, required: true },
    update_url: { type: String, required: true },
});

const form = useForm({
    password_min_length: props.settings.password_min_length,
});

function submit() {
    form.put(props.update_url, { preserveScroll: true });
}
</script>

<template>
    <CentralLayout title="Pengaturan">
        <q-form class="settings" @submit.prevent="submit">
            <section class="settings__group">
                <h2>Password user</h2>
                <q-input
                    v-model.number="form.password_min_length"
                    type="number"
                    :min="limits.password_min_length.lowest"
                    :max="limits.password_min_length.highest"
                    label="Panjang minimal password (karakter)"
                    :hint="`Berlaku untuk semua toko saat user dibuat atau passwordnya diganti. ${limits.password_min_length.lowest}–${limits.password_min_length.highest}. Password yang sudah ada tidak berubah.`"
                    outlined
                    :error="!!form.errors.password_min_length"
                    :error-message="form.errors.password_min_length"
                />
            </section>

            <div class="settings__actions">
                <q-btn type="submit" color="primary" unelevated no-caps label="Simpan" :loading="form.processing" :disable="!form.isDirty" />
            </div>
        </q-form>
    </CentralLayout>
</template>

<style scoped>
.settings {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 480px;
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.settings__group h2 {
    margin: 0 0 12px;
    font-size: 15px;
    font-weight: 700;
}

.settings__actions {
    display: flex;
    gap: 8px;
}
</style>
