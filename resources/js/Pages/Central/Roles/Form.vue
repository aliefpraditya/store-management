<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import CentralLayout from '../../../Layouts/CentralLayout.vue';
import PermissionChecklist from '../../../Components/PermissionChecklist.vue';

const props = defineProps({
    role: { type: Object, default: null },
    permission_groups: { type: Array, required: true },
    form_action: { type: Object, required: true },
});

const page = usePage();

const isEditing = props.role !== null;
const isSystemRole = props.role?.is_system ?? false;

const form = useForm({
    label: props.role?.label ?? '',
    permissions: props.role?.permission_names ?? [],
});

function submit() {
    form[props.form_action.method](props.form_action.url);
}
</script>

<template>
    <CentralLayout :title="isEditing ? 'Edit Peran' : 'Tambah Peran'">
        <q-form class="role-form" @submit.prevent="submit">
            <q-input
                v-model="form.label"
                label="Nama peran"
                outlined
                :disable="isSystemRole"
                :hint="isSystemRole ? 'Peran bawaan sistem: nama tidak bisa diubah' : undefined"
                :error="!!form.errors.label"
                :error-message="form.errors.label"
                :autofocus="!isSystemRole"
            />

            <div>
                <div class="role-form__label">Izin</div>
                <PermissionChecklist v-model="form.permissions" :groups="permission_groups" />
                <div v-if="form.errors.permissions" class="role-form__error">{{ form.errors.permissions }}</div>
            </div>

            <div class="role-form__actions">
                <q-btn type="submit" color="primary" unelevated no-caps label="Simpan" :loading="form.processing" />
                <q-btn flat no-caps label="Batal" @click="router.visit(page.props.app_urls.central_roles)" />
            </div>
        </q-form>
    </CentralLayout>
</template>

<style scoped>
.role-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 560px;
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.role-form__label {
    margin-bottom: 8px;
    color: var(--app-ink-soft);
    font-size: 12.5px;
    font-weight: 600;
}

.role-form__error {
    margin-top: 4px;
    color: var(--q-negative);
    font-size: 12px;
}

.role-form__actions {
    display: flex;
    gap: 8px;
    margin-top: 6px;
}
</style>
