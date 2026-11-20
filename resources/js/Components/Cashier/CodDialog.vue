<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import { postJson } from '../../Composables/postJson';
import { newUuid, roundMoney } from '../../Composables/useCart';
import { formatRupiah } from '../../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** Where the COD sales still waiting for their delivery are fetched. */
    listUrl: { type: String, required: true },
    /** Where the delivered COD sales whose money is still to be received (or approved) are fetched. */
    settlementsUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue', 'changed']);

const $q = useQuasar();
const page = usePage();

const DELIVERY_TAB = 'delivery';
const SETTLEMENT_TAB = 'settlement';

const METHOD_OPTIONS = [
    { value: 'cash', label: 'Tunai' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'qris', label: 'QRIS' },
    { value: 'edc', label: 'EDC' },
];

const isLoading = ref(false);
const isWorking = ref(false);
const tab = ref(DELIVERY_TAB);

const permissions = computed(() => page.props.auth.permissions);
const canReceive = computed(() => permissions.value.includes('cod.receive'));
const canApprove = computed(() => permissions.value.includes('cod.approve'));
const isSettlementTabShown = computed(() => canReceive.value || canApprove.value);

/** @type {import('vue').Ref<object[]>} */
const sales = ref([]);

/** How many COD sales wait in all; more than the list holds when only the newest are shown. */
const totalWaiting = ref(0);

/** @type {import('vue').Ref<object[]>} */
const settlements = ref([]);

/** How many delivered COD sales wait for their money, and how many of those wait for an admin's approval. */
const totalUnsettled = ref(0);
const totalAwaitingApproval = ref(0);

/** The sale being voided, or null while the void window is closed. */
const voidTarget = ref(null);
const voidReason = ref('');
const refundAccount = ref('');

/** The sale whose money is being received, or null while the receive window is closed. */
const receiveTarget = ref(null);
const receiveMethod = ref('cash');
const receiveAmount = ref('');
const receiveReference = ref('');
const receiveNotes = ref('');
const differenceReason = ref('');

/**
 * Sent with every save of one receipt of money. A retry of the same attempt (a lost answer, a second tap) carries the
 * same uuid, so the server does not record the money twice; it is renewed whenever the window is opened.
 */
let receiveUuid = newUuid();

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

function fail(error) {
    $q.notify({ type: 'negative', message: error.message, position: 'top' });
}

/**
 * @param {string} url
 * @param {string} failureMessage
 * @returns {Promise<{data: object[], meta: object}>}
 */
async function fetchList(url, failureMessage) {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

    if (!response.ok) {
        throw new Error(failureMessage);
    }

    return response.json();
}

async function loadDeliveries() {
    const body = await fetchList(props.listUrl, 'Daftar COD tidak bisa dimuat.');

    sales.value = body.data;
    totalWaiting.value = body.meta.total;
}

async function loadSettlements() {
    if (!isSettlementTabShown.value) {
        return;
    }

    const body = await fetchList(props.settlementsUrl, 'Daftar uang COD tidak bisa dimuat.');

    settlements.value = body.data;
    totalUnsettled.value = body.meta.total;
    totalAwaitingApproval.value = body.meta.awaiting_approval_total;
}

async function load() {
    isLoading.value = true;

    try {
        await Promise.all([loadDeliveries(), loadSettlements()]);
    } catch (error) {
        fail(error);
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            sales.value = [];
            settlements.value = [];
            tab.value = DELIVERY_TAB;
            load();
        }
    },
);

/** The customer took the goods: the delivery note is finished as delivered, after the cashier confirms. */
function confirmDelivered(sale) {
    $q.dialog({
        title: 'Barang sudah diantar?',
        message: `Nota ${sale.number} ditandai sudah diantar dan diterima pelanggan. Setelah itu nota tidak bisa di-void. Uangnya dicatat terpisah di tab Uang belum diterima.`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Sudah Diantar', color: 'primary', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => act(sale.delivered_url, {}, `Nota ${sale.number} ditandai sudah diantar.`));
}

function openVoid(sale) {
    voidTarget.value = sale;
    voidReason.value = '';
    refundAccount.value = '';
}

const canVoid = () => voidReason.value.trim() !== '' && (!voidTarget.value?.has_non_cash_deposit || refundAccount.value.trim() !== '') && !isWorking.value;

/** The void window stays open when the server refuses, so the reason that was typed is not lost. */
async function submitVoid() {
    const sale = voidTarget.value;

    const isDone = await act(sale.void_url, { reason: voidReason.value.trim(), refund_account: refundAccount.value.trim() || null }, `Nota ${sale.number} di-void. Barangnya kembali ke stok.`);

    if (isDone) {
        voidTarget.value = null;
    }
}

/** Opens the window for the money of a delivered sale; the amount starts at what is due, the way it usually comes in. */
function openReceive(settlement) {
    receiveTarget.value = settlement;
    receiveMethod.value = 'cash';
    receiveAmount.value = Number(settlement.amount_due);
    receiveReference.value = '';
    receiveNotes.value = '';
    differenceReason.value = '';
    receiveUuid = newUuid();
}

const receivedAmount = computed(() => roundMoney(Number(receiveAmount.value) || 0));

/** Whether the money received is not what is due, which asks for a reason (and, without the approval permission, for an admin). */
const isDifferent = computed(() => receiveTarget.value !== null && receivedAmount.value !== roundMoney(Number(receiveTarget.value.amount_due)));

const canReceiveNow = computed(() => receivedAmount.value > 0 && (!isDifferent.value || differenceReason.value.trim() !== '') && !isWorking.value);

/** The receive window stays open when the server refuses, so what was typed is not lost. */
async function submitReceive() {
    const settlement = receiveTarget.value;

    const body = {
        client_uuid: receiveUuid,
        method: receiveMethod.value,
        amount: receivedAmount.value,
        reference: receiveMethod.value === 'cash' ? null : receiveReference.value.trim() || null,
        notes: receiveNotes.value.trim() || null,
        difference_reason: isDifferent.value ? differenceReason.value.trim() : null,
    };

    const isDone = await act(settlement.settle_url, body, `Uang nota ${settlement.number} dicatat.`);

    if (isDone) {
        receiveTarget.value = null;
    }
}

/** An admin accepts a receipt of money that differed from what was due, after confirming. */
function confirmApprove(settlement) {
    $q.dialog({
        title: 'Setujui penerimaan uang?',
        message: `Nota ${settlement.number}: diterima ${formatRupiah(settlement.settlement.amount)} dari seharusnya ${formatRupiah(settlement.settlement.expected_amount)}. Alasan: ${settlement.settlement.difference_reason}`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Setujui', color: 'primary', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => act(settlement.approve_url, {}, `Penerimaan uang nota ${settlement.number} disetujui.`));
}

/**
 * Posts one action on a COD sale and reloads the lists.
 *
 * @returns {Promise<boolean>} whether the server accepted it
 */
async function act(url, body, successMessage) {
    isWorking.value = true;

    try {
        await postJson(url, body);
        $q.notify({ type: 'positive', position: 'top', message: successMessage });
        emit('changed');
        await load();

        return true;
    } catch (error) {
        fail(error);

        return false;
    } finally {
        isWorking.value = false;
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="cod">
            <q-card-section class="cod__head">
                <div class="cod__title">COD: pengantaran dan penerimaan uang</div>
                <small>Dari semua hari. Tandai sudah diantar, lalu catat uangnya saat diterima. Void bila pelanggan tidak mau menerima atau membayar.</small>
            </q-card-section>

            <q-tabs v-if="isSettlementTabShown" v-model="tab" dense align="left" active-color="primary" indicator-color="primary" class="cod__tabs">
                <q-tab :name="DELIVERY_TAB" no-caps label="Belum diantar" />
                <q-tab :name="SETTLEMENT_TAB" no-caps>
                    <div class="cod__tab">
                        Uang belum diterima
                        <q-badge v-if="totalUnsettled > 0" color="orange" :label="totalUnsettled" />
                    </div>
                </q-tab>
            </q-tabs>

            <q-card-section class="cod__body">
                <q-inner-loading :showing="isLoading" />

                <template v-if="tab === DELIVERY_TAB">
                    <p v-if="!isLoading && sales.length === 0" class="cod__empty">Tidak ada nota COD yang menunggu.</p>
                    <p v-if="totalWaiting > sales.length" class="cod__more">Menampilkan {{ sales.length }} nota terbaru dari {{ totalWaiting }}. Yang lebih lama muncul setelah yang di atas selesai atau di-void.</p>

                    <div v-for="sale in sales" :key="sale.id" class="sale">
                        <div class="sale__main">
                            <b>{{ sale.number }}</b>
                            <small>
                                {{ dateFormat.format(new Date(sale.sold_at)) }} · {{ sale.recipient_name || sale.customer_name || 'tanpa nama' }} · {{ sale.delivery_status_label }}
                            </small>
                            <small v-if="Number(sale.deposit_amount) > 0">DP sudah diterima {{ formatRupiah(sale.deposit_amount) }}</small>
                        </div>
                        <b class="sale__amount">{{ formatRupiah(sale.cod_amount) }}</b>
                        <div class="sale__actions">
                            <q-btn v-if="sale.can_mark_delivered" outline dense no-caps color="primary" label="Sudah diantar" :disable="isWorking" @click="confirmDelivered(sale)" />
                            <q-btn outline dense no-caps color="negative" label="Void" :disable="isWorking" @click="openVoid(sale)" />
                        </div>
                    </div>
                </template>

                <template v-else>
                    <p v-if="!isLoading && settlements.length === 0" class="cod__empty">Semua uang COD yang sudah diantar sudah diterima.</p>
                    <p v-if="totalUnsettled > settlements.length" class="cod__more">Menampilkan {{ settlements.length }} nota terbaru dari {{ totalUnsettled }}. Yang lebih lama muncul setelah yang di atas selesai.</p>

                    <div v-for="settlement in settlements" :key="settlement.id" class="sale">
                        <div class="sale__main">
                            <b>{{ settlement.number }}</b>
                            <small>
                                {{ dateFormat.format(new Date(settlement.sold_at)) }} · {{ settlement.recipient_name || settlement.customer_name || 'tanpa nama' }}
                                <q-badge v-if="settlement.state === 'awaiting_approval'" color="orange" label="Menunggu persetujuan" class="sale__badge" />
                            </small>
                            <small v-if="Number(settlement.offset_amount) > 0">Tagihan COD {{ formatRupiah(settlement.cod_amount) }}, dipotong retur {{ formatRupiah(settlement.offset_amount) }}</small>
                            <small v-if="settlement.has_unreturned_goods" class="sale__warning">Ada barang kembali di surat jalan yang belum diretur. Buat retur dulu agar jumlah yang ditagih tepat.</small>
                            <template v-if="settlement.settlement">
                                <small>
                                    Diterima {{ formatRupiah(settlement.settlement.amount) }} lewat {{ settlement.settlement.method_label }}
                                    oleh {{ settlement.settlement.received_by_name }}, {{ dateTimeFormat.format(new Date(settlement.settlement.received_at)) }}
                                </small>
                                <small>Seharusnya {{ formatRupiah(settlement.settlement.expected_amount) }}. Alasan selisih: {{ settlement.settlement.difference_reason }}</small>
                            </template>
                        </div>
                        <b class="sale__amount">{{ formatRupiah(settlement.amount_due) }}</b>
                        <div class="sale__actions">
                            <q-btn v-if="settlement.state === 'unsettled' && canReceive" outline dense no-caps color="primary" label="Terima uang" :disable="isWorking" @click="openReceive(settlement)" />
                            <q-btn v-if="settlement.state === 'awaiting_approval' && canApprove" outline dense no-caps color="primary" label="Setujui" :disable="isWorking" @click="confirmApprove(settlement)" />
                        </div>
                    </div>
                </template>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Tutup" v-close-popup />
            </q-card-actions>
        </q-card>

        <q-dialog :model-value="voidTarget !== null" persistent @update:model-value="voidTarget = null">
            <q-card v-if="voidTarget" class="void">
                <q-card-section>
                    <div class="cod__title">Void nota {{ voidTarget.number }}?</div>
                    <small class="void__hint">Barangnya kembali ke stok dan surat jalan yang masih berjalan dibatalkan.</small>
                </q-card-section>

                <q-card-section class="void__fields">
                    <q-input v-model="voidReason" outlined dense autofocus maxlength="255" label="Alasan void" />
                    <template v-if="Number(voidTarget.deposit_amount) > 0">
                        <p v-if="voidTarget.has_cash_deposit" class="void__note">DP tunai dikembalikan dari laci shift Anda dan mengurangi tunai seharusnya.</p>
                        <q-input v-if="voidTarget.has_non_cash_deposit" v-model="refundAccount" outlined dense maxlength="255" label="Rekening penerima pengembalian DP" placeholder="Bank, nomor rekening, atas nama" />
                    </template>
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Kembali" :disable="isWorking" @click="voidTarget = null" />
                    <q-btn color="negative" unelevated no-caps label="Void Nota" :loading="isWorking" :disable="!canVoid()" @click="submitVoid" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <q-dialog :model-value="receiveTarget !== null" persistent @update:model-value="receiveTarget = null">
            <q-card v-if="receiveTarget" class="void">
                <q-card-section>
                    <div class="cod__title">Terima uang nota {{ receiveTarget.number }}</div>
                    <small class="void__hint">Uang yang harus diterima {{ formatRupiah(receiveTarget.amount_due) }}.</small>
                </q-card-section>

                <q-card-section class="void__fields">
                    <p v-if="receiveTarget.has_unreturned_goods" class="void__warning">Ada barang kembali di surat jalan yang belum diretur. Buat retur dulu agar jumlah yang ditagih tepat.</p>

                    <q-btn-toggle v-model="receiveMethod" :options="METHOD_OPTIONS" no-caps unelevated toggle-color="primary" color="grey-3" text-color="grey-8" />
                    <p v-if="receiveMethod === 'cash'" class="void__note">Uang tunai masuk ke laci shift Anda yang sedang berjalan dan menambah tunai seharusnya.</p>

                    <q-input v-model.number="receiveAmount" type="number" min="0" step="any" outlined dense autofocus prefix="Rp" label="Jumlah diterima" />
                    <q-input v-if="receiveMethod !== 'cash'" v-model="receiveReference" outlined dense maxlength="255" label="Rekening atau nomor referensi (opsional)" />
                    <q-input v-model="receiveNotes" outlined dense maxlength="255" label="Catatan (opsional)" placeholder="Mis. nama sopir yang menyerahkan uang" />

                    <template v-if="isDifferent">
                        <q-input v-model="differenceReason" outlined dense maxlength="255" label="Alasan selisih (wajib)" />
                        <p class="void__note">
                            <template v-if="canApprove">Jumlahnya berbeda dari yang seharusnya. Karena Anda boleh menyetujui, penerimaan ini langsung disetujui.</template>
                            <template v-else>Jumlahnya berbeda dari yang seharusnya. Uangnya langsung dicatat, tetapi nota menunggu persetujuan Admin dan hari belum bisa ditutup sebelum itu.</template>
                        </p>
                    </template>
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Kembali" :disable="isWorking" @click="receiveTarget = null" />
                    <q-btn color="primary" unelevated no-caps label="Catat Uang Diterima" :loading="isWorking" :disable="!canReceiveNow" @click="submitReceive" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </q-dialog>
</template>

<style scoped>
.cod {
    display: flex;
    flex-direction: column;
    width: 560px;
    max-width: 94vw;
    max-height: 90vh;
}

.cod__title {
    font-size: 17px;
    font-weight: 700;
}

.cod__head small {
    color: var(--app-mute);
}

.cod__tabs {
    border-bottom: 1px solid var(--app-line);
}

.cod__tab {
    display: flex;
    align-items: center;
    gap: 6px;
}

.cod__body {
    position: relative;
    flex: 1;
    min-height: 120px;
    overflow-y: auto;
}

.cod__empty {
    margin: 0;
    padding: 24px 0;
    color: var(--app-mute);
    text-align: center;
}

.cod__more {
    margin: 0 0 8px;
    color: var(--app-mute);
    font-size: 12px;
}

.sale {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 6px 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--app-line);
}

.sale__main b,
.sale__main small {
    display: block;
}

.sale__main small {
    color: var(--app-mute);
    font-size: 12px;
}

.sale__main .sale__warning {
    color: #b45309;
}

.sale__badge {
    margin-left: 6px;
}

.sale__amount {
    align-self: center;
}

.sale__actions {
    display: flex;
    grid-column: 1 / -1;
    gap: 6px;
}

.void {
    width: 440px;
    max-width: 94vw;
}

.void__hint {
    color: var(--app-mute);
}

.void__fields {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.void__note {
    margin: 0;
    color: var(--app-ink-soft);
    font-size: 13px;
}

.void__warning {
    margin: 0;
    padding: 8px 10px;
    border-radius: 8px;
    background: #fff4e0;
    color: #b45309;
    font-size: 13px;
}
</style>
