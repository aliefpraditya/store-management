<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DateInput from './DateInput.vue';
import { formatRupiah } from '../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The nota being confirmed ({ number, supplier_name, total_amount, urls }). */
    purchase: { type: Object, required: true },
    /** The ways a supplier can be paid, as { value, label }. */
    paymentMethods: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue']);

const form = useForm({ payment_method: 'transfer', paid_date: '', payment_notes: '' });

/** Each time the window opens it starts from today and a transfer, the usual way. */
watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.clearErrors();
        form.payment_method = props.paymentMethods[0]?.value ?? 'transfer';
        form.paid_date = new Date().toLocaleDateString('sv-SE');
        form.payment_notes = '';
    },
);

/** A note left empty is not sent: form data would turn it into an empty string. */
form.transform((data) => ({ ...data, payment_notes: data.payment_notes.trim() || undefined }));

function submit() {
    form.post(props.purchase.urls.pay, { preserveScroll: true, onSuccess: () => emit('update:modelValue', false) });
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)">
        <q-card class="pay-dialog">
            <q-form @submit.prevent="submit">
                <q-card-section>
                    <div class="pay-dialog__title">Konfirmasi & Bayar</div>
                    <p class="pay-dialog__lead">
                        {{ purchase.number }} untuk {{ purchase.supplier_name }} dibayar penuh sekarang. Setelah ini barang dan harganya terkunci.
                    </p>
                    <div class="pay-dialog__total">
                        <span>Dibayar</span>
                        <b>{{ formatRupiah(purchase.total_amount) }}</b>
                    </div>
                </q-card-section>

                <q-card-section class="pay-dialog__fields">
                    <q-select
                        v-model="form.payment_method"
                        :options="paymentMethods"
                        option-value="value"
                        option-label="label"
                        emit-value
                        map-options
                        outlined
                        label="Cara bayar"
                        :error="!!form.errors.payment_method"
                        :error-message="form.errors.payment_method"
                    />
                    <DateInput v-model="form.paid_date" outlined label="Tanggal bayar" :error="!!form.errors.paid_date" :error-message="form.errors.paid_date" />
                    <q-input v-model="form.payment_notes" outlined label="Catatan (opsional)" maxlength="255" :error="!!form.errors.payment_notes" :error-message="form.errors.payment_notes" />
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Batal" v-close-popup />
                    <q-btn type="submit" color="primary" unelevated no-caps label="Konfirmasi & Bayar" :loading="form.processing" />
                </q-card-actions>
            </q-form>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.pay-dialog {
    width: 440px;
    max-width: 94vw;
}

.pay-dialog__title {
    font-size: 17px;
    font-weight: 700;
}

.pay-dialog__lead {
    margin: 6px 0 12px;
    color: var(--app-mute);
    font-size: 13px;
}

.pay-dialog__total {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 10px 14px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.pay-dialog__total b {
    font-size: 20px;
}

.pay-dialog__fields {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
</style>
