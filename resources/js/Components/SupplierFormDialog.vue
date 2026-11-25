<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ContactListEditor from './ContactListEditor.vue';
import RegionInput from './RegionInput.vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The supplier being edited ({ name, phone, address, province, city, email, tax_number, contacts, notes, is_active, update_url }), or null to add a new one. */
    supplier: { type: Object, default: null },
    /** Provinces and cities already typed on suppliers and carriers, offered as suggestions. */
    regions: { type: Object, default: () => ({ provinces: [], cities: [] }) },
    storeUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const form = useForm({ name: '', phone: '', address: '', province: '', city: '', email: '', tax_number: '', contacts: [], notes: '', is_active: true });

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
        form.address = props.supplier?.address ?? '';
        form.province = props.supplier?.province ?? '';
        form.city = props.supplier?.city ?? '';
        form.email = props.supplier?.email ?? '';
        form.tax_number = props.supplier?.tax_number ?? '';
        form.contacts = (props.supplier?.contacts ?? []).map((contact) => ({ id: contact.id, name: contact.name, phone: contact.phone ?? '' }));
        form.notes = props.supplier?.notes ?? '';
        form.is_active = props.supplier?.is_active ?? true;
    },
);

/** A contact row left completely blank is dropped instead of failing on its missing name. */
form.transform((data) => ({ ...data, contacts: data.contacts.filter((contact) => contact.name.trim() !== '' || contact.phone.trim() !== '') }));

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
                    <q-input v-model="form.email" outlined type="email" label="Email (opsional)" maxlength="255" :error="!!form.errors.email" :error-message="form.errors.email" />
                    <q-input v-model="form.address" outlined type="textarea" autogrow label="Alamat (opsional)" maxlength="500" :error="!!form.errors.address" :error-message="form.errors.address" />
                    <div class="supplier-dialog__pair">
                        <RegionInput v-model="form.province" :options="regions.provinces" label="Provinsi (opsional)" :error="!!form.errors.province" :error-message="form.errors.province" />
                        <RegionInput v-model="form.city" :options="regions.cities" label="Kota (opsional)" :error="!!form.errors.city" :error-message="form.errors.city" />
                    </div>
                    <q-input v-model="form.tax_number" outlined label="NPWP (opsional)" maxlength="30" :error="!!form.errors.tax_number" :error-message="form.errors.tax_number" />
                    <ContactListEditor v-model="form.contacts" title="Kontak sales" add-label="Tambah kontak sales" :errors="form.errors" />
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
    width: 520px;
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

.supplier-dialog__pair {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
</style>
