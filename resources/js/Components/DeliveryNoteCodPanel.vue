<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { newUuid, roundMoney } from '../Composables/useCart';
import { formatDeduction, formatRupiah } from '../Composables/useMoneyFormat';

const props = defineProps({
    /** The money of the note's COD receipt, as DeliveryNoteCodResource gives it. @type {object} */
    cod: { type: Object, required: true },
    /** Receipts that have goods back on the note, each with the page to make the return on. @type {{number: string, new_return_url: string}[]} */
    returnSales: { type: Array, default: () => [] },
});

const $q = useQuasar();

const STATE_CHIPS = {
    unsettled: { label: 'Uang belum diterima', chipClass: 'chip--warn' },
    awaiting_approval: { label: 'Menunggu persetujuan', chipClass: 'chip--bad' },
    settled: { label: 'Uang diterima', chipClass: 'chip--ok' },
};

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const isWorking = ref(false);
const method = ref('cash');
const amount = ref(Number(props.cod.amount_due));
const reference = ref('');
const notes = ref('');
const differenceReason = ref('');

/**
 * Sent with every save of one receipt of money. A retry of the same attempt (a lost answer, a second tap) carries the
 * same uuid, so the server does not record the money twice; it is renewed once the money is recorded.
 */
let receiveUuid = newUuid();

const stateChip = computed(() => STATE_CHIPS[props.cod.state]);

const isNothingToReceive = computed(() => props.cod.state === 'unsettled' && Number(props.cod.amount_due) <= 0);

const isReceivable = computed(() => props.cod.state === 'unsettled' && props.cod.is_goods_delivered && !isNothingToReceive.value);

const receivedAmount = computed(() => roundMoney(Number(amount.value) || 0));

/** Whether the money received is not what is due, which asks for a reason (and, without the approval permission, for an admin). */
const isDifferent = computed(() => receivedAmount.value !== roundMoney(Number(props.cod.amount_due)));

const canSave = computed(() => receivedAmount.value > 0 && (!isDifferent.value || differenceReason.value.trim() !== '') && !isWorking.value);

/** A return made on this page changes what is due, so the amount offered follows it. */
watch(
    () => props.cod.amount_due,
    (due) => (amount.value = Number(due)),
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
                reference.value = '';
                notes.value = '';
                differenceReason.value = '';
            },
            onError: (errors) => $q.notify({ type: 'negative', message: Object.values(errors)[0], position: 'top' }),
        },
    );
}

/** An admin accepts a receipt of money that differed from what was due, after confirming. */
function confirmApprove() {
    const { settlement } = props.cod;

    $q.dialog({
        title: 'Setujui penerimaan uang?',
        message: `Diterima ${formatRupiah(settlement.amount)} dari seharusnya ${formatRupiah(settlement.expected_amount)}. Alasan: ${settlement.difference_reason}`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Setujui', color: 'primary', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.post(props.cod.approve_url, {}, { preserveScroll: true }));
}
</script>

<template>
    <section class="cod" :class="{ 'cod--attention': isReceivable || cod.state === 'awaiting_approval' }">
        <header class="cod__head">
            <b>Uang COD · nota {{ cod.sale_number }}</b>
            <span class="chip" :class="stateChip.chipClass">{{ stateChip.label }}</span>
        </header>

        <p v-if="isReceivable" class="cod__alert">
            <q-icon name="notification_important" size="18px" />
            Barang sudah diantar, tetapi uang {{ formatRupiah(cod.amount_due) }} belum diterima.
        </p>
        <p v-else-if="cod.state === 'awaiting_approval'" class="cod__alert">
            <q-icon name="notification_important" size="18px" />
            Uang sudah dicatat, tetapi selisihnya belum disetujui.
        </p>

        <template v-if="cod.settlement">
            <dl class="cod__facts">
                <div><dt>Diterima</dt><dd>{{ formatRupiah(cod.settlement.amount) }} lewat {{ cod.settlement.method_label }}</dd></div>
                <div><dt>Oleh</dt><dd>{{ cod.settlement.received_by_name }} · {{ dateTimeFormat.format(new Date(cod.settlement.received_at)) }}</dd></div>
                <div v-if="cod.settlement.reference"><dt>Referensi</dt><dd>{{ cod.settlement.reference }}</dd></div>
                <div v-if="cod.settlement.difference_reason"><dt>Selisih</dt><dd>Seharusnya {{ formatRupiah(cod.settlement.expected_amount) }}. Alasan: {{ cod.settlement.difference_reason }}</dd></div>
                <div v-if="cod.settlement.approved_by_name"><dt>Disetujui</dt><dd>{{ cod.settlement.approved_by_name }}</dd></div>
            </dl>

            <q-btn v-if="cod.state === 'awaiting_approval' && cod.can_approve" color="primary" unelevated no-caps dense label="Setujui Selisih" @click="confirmApprove" />
            <p v-else-if="cod.state === 'awaiting_approval'" class="cod__note">Uangnya sudah dicatat. Selisihnya menunggu persetujuan Admin, dan hari belum bisa ditutup sebelum itu.</p>
        </template>

        <p v-else-if="!cod.is_goods_delivered" class="cod__note">Uang bisa diterima setelah barang diantar. Selesaikan pengiriman dulu, lalu isi barang yang kembali.</p>

        <p v-else-if="isNothingToReceive" class="cod__note">Seluruh tagihan sudah dipotong retur, jadi tidak ada uang yang perlu diterima.</p>

        <template v-else>
            <dl class="cod__facts">
                <div><dt>Tagihan COD</dt><dd>{{ formatRupiah(cod.cod_amount) }}</dd></div>
                <div v-if="Number(cod.offset_amount) > 0"><dt>Retur</dt><dd>{{ formatDeduction(cod.offset_amount) }}</dd></div>
                <div><dt>Diterima</dt><dd><b>{{ formatRupiah(cod.amount_due) }}</b></dd></div>
            </dl>

            <div v-if="cod.has_unreturned_goods" class="cod__warning">
                Ada barang kembali di surat jalan yang belum diretur. Buat retur dulu agar jumlah yang ditagih tepat.
                <q-btn
                    v-for="returnSale in returnSales"
                    :key="returnSale.number"
                    flat
                    dense
                    no-caps
                    color="primary"
                    :label="returnSales.length > 1 ? `Retur nota ${returnSale.number}` : 'Buat Retur'"
                    @click="router.visit(returnSale.new_return_url)"
                />
            </div>

            <form v-if="cod.can_receive" class="cod__form" @submit.prevent="receive">
                <q-option-group v-model="method" :options="cod.methods" type="radio" inline color="primary" aria-label="Cara uang diterima" />
                <p v-if="method === 'cash'" class="cod__note">Uang tunai masuk ke laci shift Anda yang sedang berjalan dan menambah tunai seharusnya.</p>

                <q-input v-model.number="amount" type="number" min="0" step="any" outlined dense prefix="Rp" label="Jumlah diterima" />
                <q-input v-if="method !== 'cash'" v-model="reference" outlined dense maxlength="255" label="Rekening atau nomor referensi (opsional)" />
                <q-input v-model="notes" outlined dense maxlength="255" label="Catatan (opsional)" placeholder="Mis. nama sopir yang menyerahkan uang" />

                <template v-if="isDifferent">
                    <q-input v-model="differenceReason" outlined dense maxlength="255" label="Alasan selisih (wajib)" />
                    <p class="cod__note">
                        <template v-if="cod.can_approve">Jumlahnya berbeda dari yang seharusnya. Karena Anda boleh menyetujui, penerimaan ini langsung disetujui.</template>
                        <template v-else>Jumlahnya berbeda dari yang seharusnya. Uangnya langsung dicatat, tetapi menunggu persetujuan Admin dan hari belum bisa ditutup sebelum itu.</template>
                    </p>
                </template>

                <div><q-btn type="submit" color="primary" unelevated no-caps label="Catat Uang Diterima" :loading="isWorking" :disable="!canSave" /></div>
            </form>
            <p v-else class="cod__note">Anda tidak punya izin menerima uang COD. Minta Admin mencatatnya.</p>
        </template>
    </section>
</template>

<style scoped>
.cod {
    max-width: 720px;
    margin-top: 14px;
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.cod--attention {
    border-color: #c2710c;
    border-left-width: 4px;
}

.cod__alert {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0 0 12px;
    padding: 8px 12px;
    border-radius: 6px;
    background: #fdf1e0;
    color: #c2710c;
    font-size: 13.5px;
    font-weight: 600;
}

.cod__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.cod__facts {
    margin: 0 0 12px;
}

.cod__facts div {
    display: flex;
    gap: 12px;
    padding: 3px 0;
}

.cod__facts dt {
    flex: 0 0 100px;
    color: var(--app-mute);
}

.cod__facts dd {
    margin: 0;
}

.cod__form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cod__note {
    margin: 0;
    color: var(--app-mute);
    font-size: 13px;
}

.cod__warning {
    margin: 0 0 12px;
    padding: 8px 12px;
    border-radius: 6px;
    background: #fdf1e0;
    color: #c2710c;
    font-size: 13px;
}
</style>
