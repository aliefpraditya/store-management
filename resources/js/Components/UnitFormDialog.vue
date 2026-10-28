<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The unit being edited, or null to add a new one. */
    unit: { type: Object, default: null },
    /** Where a new unit is posted. */
    createUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const form = useForm({ code: '', name: '' });

const isEditing = computed(() => props.unit !== null);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.clearErrors();
        form.code = props.unit?.code ?? '';
        form.name = props.unit && props.unit.name !== props.unit.code ? props.unit.name : '';
    },
);

/**
 * @param {string} code
 * @returns {string} the code the way the server stores it: upper case, single spaces
 */
const normalize = (code) => code.trim().replace(/\s+/g, ' ').toUpperCase();

function submit() {
    const savedCode = normalize(form.code);
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('saved', savedCode);
            emit('update:modelValue', false);
        },
    };

    if (isEditing.value) {
        form.put(props.unit.update_url, options);
    } else {
        form.post(props.createUrl, options);
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)">
        <q-card class="unit-dialog">
            <q-form @submit.prevent="submit">
                <q-card-section>
                    <div class="unit-dialog__title">{{ isEditing ? 'Ubah satuan' : 'Tambah satuan' }}</div>
                </q-card-section>

                <q-card-section class="unit-dialog__fields">
                    <q-input
                        v-model="form.code"
                        label="Kode (mis. PCS)"
                        :hint="isEditing && unit.products_count > 0 ? 'Mengganti kode ikut mengubahnya di semua barang yang memakai satuan ini' : 'Huruf besar, maksimal 20 karakter'"
                        outlined
                        autofocus
                        :error="!!form.errors.code"
                        :error-message="form.errors.code"
                    />
                    <q-input
                        v-model="form.name"
                        label="Nama (mis. Pieces)"
                        hint="Boleh dikosongkan, nama ikut kodenya"
                        outlined
                        :error="!!form.errors.name"
                        :error-message="form.errors.name"
                    />
                </q-card-section>

                <q-card-actions align="right" class="q-pa-md">
                    <q-btn flat no-caps label="Batal" @click="emit('update:modelValue', false)" />
                    <q-btn type="submit" color="primary" unelevated no-caps label="Simpan" :loading="form.processing" />
                </q-card-actions>
            </q-form>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.unit-dialog {
    width: 420px;
    max-width: 92vw;
    border-radius: var(--app-radius);
}

.unit-dialog__title {
    font-size: 17px;
    font-weight: 700;
}

.unit-dialog__fields {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
</style>
