<script setup>
import { router, useForm } from '@inertiajs/vue3';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    store_url: { type: String, required: true },
    index_url: { type: String, required: true },
    warehouses: { type: Array, required: true },
    categories: { type: Array, required: true },
    brands: { type: Array, required: true },
    scopes: { type: Array, required: true },
    reasons: { type: Array, required: true },
});

const form = useForm({
    warehouse_id: props.warehouses[0]?.id ?? null,
    opname_date: new Date().toLocaleDateString('sv-SE'),
    scope: 'selected',
    category_id: null,
    brand_id: null,
    reason_code: 'routine',
});

const scopeHints = {
    category: 'Semua barang dari kategori dan/atau merek yang dipilih masuk daftar hitung saat opname dimulai. Pilih salah satu atau keduanya.',
    selected: 'Anda memilih barangnya sendiri, cocok untuk opname rutin, barang datang, atau stok awal beberapa barang.',
};

function submit() {
    form.post(props.store_url);
}
</script>

<template>
    <StoreLayout title="Opname Baru">
        <template #actions>
            <q-btn flat no-caps label="Batal" class="q-mr-sm" @click="router.visit(index_url)" />
            <q-btn color="primary" unelevated no-caps label="Buat Opname" :loading="form.processing" @click="submit" />
        </template>

        <q-form class="opname-form" @submit.prevent="submit">
            <section class="card">
                <h2>Opname</h2>

                <div class="grid grid--two">
                    <q-select
                        v-model="form.warehouse_id"
                        :options="warehouses"
                        option-value="id"
                        option-label="name"
                        emit-value
                        map-options
                        label="Gudang"
                        outlined
                        :error="!!form.errors.warehouse_id"
                        :error-message="form.errors.warehouse_id"
                    />
                    <q-input v-model="form.opname_date" type="date" label="Tanggal" outlined :error="!!form.errors.opname_date" :error-message="form.errors.opname_date" />
                </div>

                <div class="field">
                    <span class="field__label">Cakupan</span>
                    <q-btn-toggle
                        v-model="form.scope"
                        :options="scopes.map((scope) => ({ label: scope.label, value: scope.value }))"
                        no-caps
                        unelevated
                        toggle-color="primary"
                        color="grey-3"
                        text-color="grey-8"
                    />
                    <small>{{ scopeHints[form.scope] }}</small>
                    <small v-if="form.errors.scope" class="field__error">{{ form.errors.scope }}</small>
                </div>

                <div v-if="form.scope === 'category'" class="grid grid--two">
                    <q-select
                        v-model="form.category_id"
                        :options="categories"
                        option-value="id"
                        option-label="name"
                        emit-value
                        map-options
                        clearable
                        label="Kategori"
                        outlined
                        :error="!!form.errors.category_id"
                        :error-message="form.errors.category_id"
                    />
                    <q-select
                        v-model="form.brand_id"
                        :options="brands"
                        option-value="id"
                        option-label="name"
                        emit-value
                        map-options
                        clearable
                        label="Merek"
                        outlined
                        :error="!!form.errors.brand_id"
                        :error-message="form.errors.brand_id"
                    />
                </div>

                <div class="grid grid--two">
                    <q-select
                        v-model="form.reason_code"
                        :options="reasons"
                        option-value="value"
                        option-label="label"
                        emit-value
                        map-options
                        label="Alasan"
                        outlined
                        :error="!!form.errors.reason_code"
                        :error-message="form.errors.reason_code"
                    />
                </div>
            </section>

            <p class="note">Setelah dibuat, opname masih draft. Stok baru berubah ketika opname diposting.</p>
        </q-form>
    </StoreLayout>
</template>

<style scoped>
.opname-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 760px;
}

.card {
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0 0 14px;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.4;
}

.grid {
    display: grid;
    gap: 14px;
    margin-bottom: 14px;
}

.grid--two {
    grid-template-columns: 1fr 1fr;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 14px;
}

.field__label {
    color: var(--app-ink-soft);
    font-size: 12.5px;
}

.field small {
    color: var(--app-mute);
    font-size: 12.5px;
}

.field .field__error {
    color: #be123c;
}

.note {
    margin: 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

@media screen and (max-width: 640px) {
    .grid--two {
        grid-template-columns: 1fr;
    }
}
</style>
