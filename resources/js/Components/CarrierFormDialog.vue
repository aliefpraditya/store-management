<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ContactListEditor from './ContactListEditor.vue';
import SuggestedTextInput from './SuggestedTextInput.vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The carrier being edited ({ name, phone, address, province, city, transit_days, contacts, notes, is_active, update_url }), or null to add a new one. */
    carrier: { type: Object, default: null },
    /** Provinces and cities already typed on suppliers and carriers, offered as suggestions. */
    regions: { type: Object, default: () => ({ provinces: [], cities: [] }) },
    storeUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const form = useForm({ name: '', phone: '', address: '', province: '', city: '', transit_days: '', contacts: [], notes: '', is_active: true });

/** Each time the window opens it starts from the carrier being edited, or empty for a new one. */
watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.clearErrors();
        form.name = props.carrier?.name ?? '';
        form.phone = props.carrier?.phone ?? '';
        form.address = props.carrier?.address ?? '';
        form.province = props.carrier?.province ?? '';
        form.city = props.carrier?.city ?? '';
        form.transit_days = props.carrier?.transit_days ?? '';
        form.contacts = (props.carrier?.contacts ?? []).map((contact) => ({ id: contact.id, name: contact.name, phone: contact.phone ?? '' }));
        form.notes = props.carrier?.notes ?? '';
        form.is_active = props.carrier?.is_active ?? true;
    },
);

/** A contact row left completely blank is dropped instead of failing on its missing name. */
form.transform((data) => ({ ...data, contacts: data.contacts.filter((contact) => contact.name.trim() !== '' || contact.phone.trim() !== '') }));

function submit() {
    const options = { preserveScroll: true, preserveState: true, onSuccess: () => emit('update:modelValue', false) };

    if (props.carrier) {
        form.put(props.carrier.update_url, options);
    } else {
        form.post(props.storeUrl, options);
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)">
        <q-card class="carrier-dialog">
            <q-form @submit.prevent="submit">
                <q-card-section>
                    <div class="carrier-dialog__title">{{ carrier ? 'Ubah ekspedisi' : 'Ekspedisi baru' }}</div>
                </q-card-section>

                <q-card-section class="carrier-dialog__fields">
                    <q-input v-model="form.name" outlined label="Nama ekspedisi" maxlength="255" autofocus :error="!!form.errors.name" :error-message="form.errors.name" />
                    <q-input v-model="form.phone" outlined label="Telepon (opsional)" maxlength="50" :error="!!form.errors.phone" :error-message="form.errors.phone" />
                    <q-input v-model="form.address" outlined type="textarea" autogrow label="Alamat (opsional)" maxlength="500" :error="!!form.errors.address" :error-message="form.errors.address" />
                    <div class="carrier-dialog__pair">
                        <SuggestedTextInput v-model="form.province" :options="regions.provinces" label="Provinsi tempat barang diterima (opsional)" :error="!!form.errors.province" :error-message="form.errors.province" />
                        <SuggestedTextInput v-model="form.city" :options="regions.cities" label="Kota tempat barang diterima (opsional)" :error="!!form.errors.city" :error-message="form.errors.city" />
                    </div>
                    <q-input
                        v-model="form.transit_days"
                        outlined
                        type="number"
                        min="1"
                        max="365"
                        label="Estimasi lama kirim ke toko, hari (opsional)"
                        :error="!!form.errors.transit_days"
                        :error-message="form.errors.transit_days"
                    />
                    <ContactListEditor v-model="form.contacts" title="Kontak" add-label="Tambah kontak" :errors="form.errors" />
                    <q-input v-model="form.notes" outlined type="textarea" autogrow label="Catatan (opsional)" maxlength="1000" :error="!!form.errors.notes" :error-message="form.errors.notes" />
                    <q-toggle v-if="carrier" v-model="form.is_active" label="Aktif (bisa dipilih di nota baru)" />
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
.carrier-dialog {
    width: 520px;
    max-width: 94vw;
}

.carrier-dialog__title {
    font-size: 17px;
    font-weight: 700;
}

.carrier-dialog__fields {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.carrier-dialog__pair {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
</style>
