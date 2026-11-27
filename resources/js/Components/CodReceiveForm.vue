<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { newUuid, roundMoney } from '../Composables/useCart';

const props = defineProps({
    /** What the form needs of a COD receipt: amount_due, methods, can_approve and the receive_url to post to. @type {object} */
    cod: { type: Object, required: true },
    /** Money paid before the goods leave: it may be part of what is due or all of it, never more, and there is no difference to explain. @type {boolean} */
    isAdvance: { type: Boolean, default: false },
});

const emit = defineEmits(['received']);

const $q = useQuasar();

const isWorking = ref(false);
const method = ref('cash');
const amount = ref(props.isAdvance ? null : Number(props.cod.amount_due));
const reference = ref('');
const notes = ref('');
const differenceReason = ref('');

/**
 * Sent with every save of one receipt of money. A retry of the same attempt (a lost answer, a second tap) carries the
 * same uuid, so the server does not record the money twice; it is renewed once the money is recorded.
 */
let receiveUuid = newUuid();

const receivedAmount = computed(() => roundMoney(Number(amount.value) || 0));

/** Whether the money received is not what is due, which asks for a reason (and, without the approval permission, for an admin). */
const isDifferent = computed(() => !props.isAdvance && receivedAmount.value !== roundMoney(Number(props.cod.amount_due)));

const isWithinBill = computed(() => !props.isAdvance || receivedAmount.value <= roundMoney(Number(props.cod.amount_due)));

const canSave = computed(() => receivedAmount.value > 0 && isWithinBill.value && (!isDifferent.value || differenceReason.value.trim() !== '') && !isWorking.value);

/** A return made on the page changes what is due, so the amount offered follows it. */
watch(
    () => props.cod.amount_due,
    (due) => (amount.value = props.isAdvance ? null : Number(due)),
);

function receive() {
    router.post(
        props.cod.receive_url,
        {
            client_uuid: receiveUuid,
            method: method.value,
            amount: receivedAmount.value,
            reference: method.value === 'cash' ? null : reference.value.trim() || null,
            notes: notes.value.trim() || null,
            difference_reason: isDifferent.value ? differenceReason.value.trim() : null,
        },
        {
            preserveScroll: true,
            onStart: () => (isWorking.value = true),
            onFinish: () => (isWorking.value = false),
            onSuccess: () => {
                receiveUuid = newUuid();
                if (props.isAdvance) {
                    amount.value = null;
                }

                reference.value = '';
                notes.value = '';
                differenceReason.value = '';
                emit('received');
            },
            onError: (errors) => $q.notify({ type: 'negative', message: Object.values(errors)[0], position: 'top' }),
        },
    );
}
</script>

<template>
    <form class="cod-form" @submit.prevent="receive">
        <q-option-group v-model="method" :options="cod.methods" type="radio" inline color="primary" aria-label="Cara uang diterima" />
        <p v-if="method === 'cash'" class="cod-form__note">Uang tunai masuk ke laci shift Anda yang sedang berjalan dan menambah tunai seharusnya.</p>

        <q-input
            v-model.number="amount"
            type="number"
            min="0"
            step="any"
            outlined
            dense
            prefix="Rp"
            label="Jumlah diterima"
            :error="isAdvance && receivedAmount > 0 && !isWithinBill"
            error-message="Tidak boleh melebihi sisa tagihan."
        />
        <q-input v-if="method !== 'cash'" v-model="reference" outlined dense maxlength="255" label="Rekening atau nomor referensi (opsional)" />
        <q-input v-model="notes" outlined dense maxlength="255" label="Catatan (opsional)" placeholder="Mis. nama sopir yang menyerahkan uang" />

        <template v-if="isDifferent">
            <q-input v-model="differenceReason" outlined dense maxlength="255" label="Alasan selisih (wajib)" />
            <p class="cod-form__note">
                <template v-if="cod.can_approve">Jumlahnya berbeda dari yang seharusnya. Karena Anda boleh menyetujui, penerimaan ini langsung disetujui.</template>
                <template v-else>Jumlahnya berbeda dari yang seharusnya. Uangnya langsung dicatat, tetapi menunggu persetujuan Admin dan hari belum bisa ditutup sebelum itu.</template>
            </p>
        </template>

        <div><q-btn type="submit" color="primary" unelevated no-caps :label="isAdvance ? 'Catat Pembayaran di Muka' : 'Catat Uang Diterima'" :loading="isWorking" :disable="!canSave" /></div>
    </form>
</template>

<style scoped>
.cod-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cod-form__note {
    margin: 0;
    color: var(--app-mute);
    font-size: 13px;
}
</style>
