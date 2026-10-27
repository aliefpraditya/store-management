<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import CentralLayout from '../../../Layouts/CentralLayout.vue';

const props = defineProps({
    user: { type: Object, default: null },
    roles: { type: Array, required: true },
    stores: { type: Array, required: true },
    form_action: { type: Object, required: true },
});

const page = usePage();

const isEditing = props.user !== null;
const isOwnAccount = props.user?.is_self ?? false;

const form = useForm({
    name: props.user?.name ?? '',
    username: props.user?.username ?? '',
    password: '',
    role: props.user?.role?.value ?? 'cashier',
    store_id: props.user?.store?.id ?? null,
    is_active: props.user?.is_active ?? true,
});

const isPasswordVisible = ref(false);
const isStoreNeeded = computed(() => form.role !== 'super_admin');

const roleOptions = computed(() => props.roles.map((role) => ({ label: role.label, value: role.value })));

function submit() {
    form.transform((data) => ({ ...data, store_id: isStoreNeeded.value ? data.store_id : null }))[props.form_action.method](
        props.form_action.url,
    );
}
</script>

<template>
    <CentralLayout :title="isEditing ? 'Edit User' : 'Tambah User'">
        <q-form class="user-form" @submit.prevent="submit">
            <q-input v-model="form.name" label="Nama" outlined :error="!!form.errors.name" :error-message="form.errors.name" autofocus />

            <q-input
                v-model="form.username"
                label="Username"
                hint="Huruf kecil, angka, titik, garis bawah, atau strip"
                outlined
                :error="!!form.errors.username"
                :error-message="form.errors.username"
            />

            <q-input
                v-model="form.password"
                :type="isPasswordVisible ? 'text' : 'password'"
                label="Password"
                :hint="isEditing ? 'Kosongkan jika tidak diubah' : undefined"
                autocomplete="new-password"
                outlined
                :error="!!form.errors.password"
                :error-message="form.errors.password"
            >
                <template #append>
                    <q-icon
                        :name="isPasswordVisible ? 'visibility_off' : 'visibility'"
                        class="cursor-pointer"
                        @click="isPasswordVisible = !isPasswordVisible"
                    />
                </template>
            </q-input>

            <div>
                <div class="user-form__label">Peran</div>
                <q-btn-toggle
                    v-model="form.role"
                    :options="roleOptions"
                    :disable="isOwnAccount"
                    unelevated
                    no-caps
                    toggle-color="primary"
                    color="white"
                    text-color="grey-8"
                    class="user-form__roles"
                />
                <div v-if="form.errors.role" class="user-form__error">{{ form.errors.role }}</div>
                <div v-else-if="isOwnAccount" class="user-form__hint">Peran akun sendiri tidak bisa diubah.</div>
            </div>

            <q-select
                v-if="isStoreNeeded"
                v-model="form.store_id"
                :options="stores"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                label="Toko"
                outlined
                :error="!!form.errors.store_id"
                :error-message="form.errors.store_id"
            />

            <q-toggle v-model="form.is_active" label="Aktif" color="positive" :disable="isOwnAccount" />

            <div class="user-form__actions">
                <q-btn type="submit" color="primary" unelevated no-caps label="Simpan" :loading="form.processing" />
                <q-btn flat no-caps label="Batal" @click="router.visit(page.props.urls.central_users)" />
            </div>
        </q-form>
    </CentralLayout>
</template>

<style scoped>
.user-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 480px;
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.user-form__label {
    margin-bottom: 6px;
    color: var(--app-ink-soft);
    font-size: 12.5px;
    font-weight: 600;
}

.user-form__roles {
    border: 1px solid var(--app-line);
    border-radius: 10px;
}

.user-form__error {
    margin-top: 4px;
    color: var(--q-negative);
    font-size: 12px;
}

.user-form__hint {
    margin-top: 4px;
    color: var(--app-mute);
    font-size: 12px;
}

.user-form__actions {
    display: flex;
    gap: 8px;
    margin-top: 6px;
}
</style>
