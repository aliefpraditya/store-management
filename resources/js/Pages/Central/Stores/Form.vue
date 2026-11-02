<script setup>
import { ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import CentralLayout from '../../../Layouts/CentralLayout.vue';

const props = defineProps({
    store: { type: Object, default: null },
    form_action: { type: Object, required: true },
});

const page = usePage();

const isEditing = props.store !== null;
const isDatabaseEditable = props.store?.is_database_editable ?? true;

const form = useForm({
    name: props.store?.name ?? '',
    code: props.store?.code ?? '',
    address: props.store?.address ?? '',
    phone: props.store?.phone ?? '',
    receipt_prefix: props.store?.receipt_prefix ?? '',
    db_name: props.store?.db_name ?? '',
    db_username: props.store?.db_username ?? '',
    db_password: '',
    db_host: props.store?.db_host ?? '',
});

const isPasswordVisible = ref(false);

/**
 * Suggested values stop following the name as soon as the person edits that field by hand.
 */
const wasEditedByHand = { code: false, receipt_prefix: false, db_name: false };

/**
 * "Cabang Pasar Baru" becomes "cabang_pasar_baru": lowercase, punctuation dropped, spaces to underscores.
 * @param {string} name
 * @returns {string}
 */
function suggestCode(name) {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s_]/g, '')
        .trim()
        .replace(/\s+/g, '_')
        .slice(0, 40);
}

/**
 * Initials of the words, e.g. "Cabang Pasar Baru" becomes "CPB".
 * @param {string} name
 * @returns {string}
 */
function suggestReceiptPrefix(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .map((word) => word.charAt(0))
        .join('')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, 4);
}

if (!isEditing) {
    watch(
        () => form.name,
        (name) => {
            if (!wasEditedByHand.code) {
                form.code = suggestCode(name);
            }
            if (!wasEditedByHand.receipt_prefix) {
                form.receipt_prefix = suggestReceiptPrefix(name);
            }
        },
    );

    watch(
        () => form.code,
        (code) => {
            if (!wasEditedByHand.db_name) {
                form.db_name = code ? `store_${code}` : '';
            }
        },
    );
}

function submit() {
    form[props.form_action.method](props.form_action.url);
}
</script>

<template>
    <CentralLayout :title="isEditing ? 'Edit Toko' : 'Tambah Toko'">
        <q-form class="store-form" @submit.prevent="submit">
            <section class="store-form__section">
                <h3>Toko</h3>

                <div class="store-form__row">
                    <q-input v-model="form.name" label="Nama toko" outlined autofocus :error="!!form.errors.name" :error-message="form.errors.name" />
                    <q-input
                        v-model="form.code"
                        label="Kode"
                        :hint="isEditing ? 'Kode tidak bisa diubah' : 'Dipakai di alamat toko dan tidak bisa diubah nanti'"
                        outlined
                        :disable="isEditing"
                        :error="!!form.errors.code"
                        :error-message="form.errors.code"
                        @update:model-value="wasEditedByHand.code = true"
                    />
                </div>

                <div class="store-form__row">
                    <q-input
                        v-model="form.receipt_prefix"
                        label="Prefiks nota"
                        hint="Singkat, contoh MU"
                        outlined
                        :error="!!form.errors.receipt_prefix"
                        :error-message="form.errors.receipt_prefix"
                        @update:model-value="wasEditedByHand.receipt_prefix = true"
                    />
                    <q-input v-model="form.phone" label="Telepon" outlined :error="!!form.errors.phone" :error-message="form.errors.phone" />
                </div>

                <q-input v-model="form.address" label="Alamat" outlined :error="!!form.errors.address" :error-message="form.errors.address" />
            </section>

            <section class="store-form__section">
                <h3>Database</h3>

                <p v-if="!isDatabaseEditable" class="store-form__note">
                    Database terkunci karena toko sudah disiapkan: <b>{{ store.db_name }}</b>.
                </p>
                <p v-else class="store-form__note">
                    Buat database dan user-nya dulu di hPanel. Aplikasi hanya menguji koneksi dan menyiapkan tabelnya.
                </p>

                <template v-if="isDatabaseEditable">
                    <div class="store-form__row">
                        <q-input
                            v-model="form.db_name"
                            label="Nama database"
                            outlined
                            :error="!!form.errors.db_name"
                            :error-message="form.errors.db_name"
                            @update:model-value="wasEditedByHand.db_name = true"
                        />
                        <q-input v-model="form.db_host" label="Host database" hint="Kosongkan untuk server bawaan" outlined :error="!!form.errors.db_host" :error-message="form.errors.db_host" />
                    </div>

                    <div class="store-form__row">
                        <q-input v-model="form.db_username" label="User database" autocomplete="off" outlined :error="!!form.errors.db_username" :error-message="form.errors.db_username" />
                        <q-input
                            v-model="form.db_password"
                            :type="isPasswordVisible ? 'text' : 'password'"
                            label="Password database"
                            :hint="store?.has_db_password ? 'Kosongkan jika tidak diubah' : undefined"
                            autocomplete="new-password"
                            outlined
                            :error="!!form.errors.db_password"
                            :error-message="form.errors.db_password"
                        >
                            <template #append>
                                <q-icon
                                    :name="isPasswordVisible ? 'visibility_off' : 'visibility'"
                                    class="cursor-pointer"
                                    @click="isPasswordVisible = !isPasswordVisible"
                                />
                            </template>
                        </q-input>
                    </div>
                </template>
            </section>

            <div class="store-form__actions">
                <q-btn type="submit" color="primary" unelevated no-caps :label="isEditing ? 'Simpan' : 'Buat & siapkan database'" :loading="form.processing" />
                <q-btn flat no-caps label="Batal" @click="router.visit(page.props.app_urls.central_stores)" />
            </div>
        </q-form>
    </CentralLayout>
</template>

<style scoped>
.store-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 720px;
}

.store-form__section {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.store-form__section h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
}

.store-form__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.store-form__note {
    margin: 0;
    color: var(--app-ink-soft);
    font-size: 13px;
}

.store-form__actions {
    display: flex;
    gap: 8px;
}

@media (max-width: 700px) {
    .store-form__row {
        grid-template-columns: 1fr;
    }
}
</style>
