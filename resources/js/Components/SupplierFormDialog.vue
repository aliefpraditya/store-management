<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The supplier being edited ({ name, phone, notes, is_active, update_url }), or null to add a new one. */
    supplier: { type: Object, default: null },
    storeUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const form = useForm({ name: '', phone: '', notes: '', is_active: true });

/** Each time the window opens it starts from the supplier being edited, or empty for a new one. */
watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.clearErrors();
        form.name = props.supplier?.name ?? '';
        form.phone = props.supplier?.phone ?? '';
        form.notes = props.supplier?.notes ?? '';
        form.is_active = props.supplier?.is_active ?? true;
    },
);

function submit() {
    const options = { preserveScroll: true, preserveState: true, onSuccess: () => emit('update:modelValue', false) };

    if (props.supplier) {
        form.put(props.supplier.update_url, options);
    } else {
        form.post(props.storeUrl, options);
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)">
        <q-card class="supplier-dialog">
            <q-form @submit.prevent="submit">
                <q-card-section>
                    <div class="supplier-dialog__title">{{ supplier ? 'Ubah supplier' : 'Supplier baru' }}</div>
                </q-card-section>

                <q-card-section class="supplier-dialog__fields">
                    <q-input v-model="form.name" outlined label="Nama supplier" maxlength="255" autofocus :error="!!form.errors.name" :error-message="form.errors.name" />
                    <q-input v-model="form.phone" outlined label="Telepon (opsional)" maxlength="50" :error="!!form.errors.phone" :error-message="form.errors.phone" />
                    <q-input v-model="form.notes" outlined type="textarea" autogrow label="Catatan (opsional)" maxlength="1000" :error="!!form.errors.notes" :error-message="form.errors.notes" />
                    <q-toggle v-if="supplier" v-model="form.is_active" label="Aktif (bisa dipilih di nota baru)" />
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Batal" v-close-popup />
                    <q-btn type="submit" color="primary" unelevated no-caps label="Simpan" :loading="form.processing" />
                </q-card-actions>
            </q-form>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.supplier-dialog {
    width: 460px;
    max-width: 94vw;
}

.supplier-dialog__title {
    font-size: 17px;
    font-weight: 700;
}

.supplier-dialog__fields {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
</style>
