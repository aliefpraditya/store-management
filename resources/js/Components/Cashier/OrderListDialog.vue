<script setup>
import { ref, watch } from 'vue';
import { useQuasar } from 'quasar';
import { postJson } from '../../Composables/postJson';
import { printInNewWindow } from '../../Composables/printInNewWindow';
import { formatQuantity } from '../../Composables/useQuantityFormat';
import { formatRupiah } from '../../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** Where the customer orders that are still open are fetched. */
    listUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue', 'changed', 'collect']);

const $q = useQuasar();

const isLoading = ref(false);
const isWorking = ref(false);

/** @type {import('vue').Ref<object[]>} */
const orders = ref([]);

/** How many orders are open in all; more than the list holds when only some are shown. */
const totalOpen = ref(0);

/** The order being cancelled, or null while the cancel window is closed. */
const cancelTarget = ref(null);
const cancelReason = ref('');
const refundAccount = ref('');

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });

function fail(error) {
    $q.notify({ type: 'negative', message: error.message, position: 'top' });
}

async function load() {
    isLoading.value = true;

    try {
        const response = await fetch(props.listUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error('Daftar pesanan tidak bisa dimuat.');
        }

        const body = await response.json();

        orders.value = body.data;
        totalOpen.value = body.meta.total;
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
            orders.value = [];
            load();
        }
    },
);

/** The goods came in (through a stock opname): the order is set aside so no other sale takes them. */
function confirmReady(order) {
    $q.dialog({
        title: 'Barang pesanan sudah datang?',
        message: `Pesanan ${order.number} ditandai siap diambil dan barangnya disisihkan dari stok. Pastikan barangnya sudah dimasukkan lewat stok opname.`,
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Sudah Datang', color: 'primary', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => act(order.ready_url, {}, `Pesanan ${order.number} siap diambil.`));
}

function openCancel(order) {
    cancelTarget.value = order;
    cancelReason.value = '';
    refundAccount.value = '';
}

const canCancel = () => cancelReason.value.trim() !== '' && (!cancelTarget.value?.has_non_cash_deposit || refundAccount.value.trim() !== '') && !isWorking.value;

/** The cancel window stays open when the server refuses, so the reason that was typed is not lost. */
async function submitCancel() {
    const order = cancelTarget.value;

    const isDone = await act(order.cancel_url, { reason: cancelReason.value.trim(), refund_account: refundAccount.value.trim() || null }, `Pesanan ${order.number} dibatalkan.`);

    if (isDone) {
        cancelTarget.value = null;
    }
}

/** Counts the print on the server and opens the order slip in a window of its own. */
async function printOrder(order) {
    try {
        await printInNewWindow(async () => (await postJson(order.print_url)).sheet_url);
    } catch (error) {
        fail(error);
    }
}

function collect(order) {
    emit('collect', order);
    emit('update:modelValue', false);
}

/**
 * Posts one action on an order and reloads the list.
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
        <q-card class="orders">
            <q-card-section class="orders__head">
                <div class="orders__title">Pesanan pelanggan yang belum selesai</div>
                <small>Dari semua hari. Tandai barang sudah datang, ambil saat pelanggan datang, atau batalkan (uang muka dikembalikan).</small>
            </q-card-section>

            <q-card-section class="orders__body">
                <q-inner-loading :showing="isLoading" />

                <p v-if="!isLoading && orders.length === 0" class="orders__empty">Tidak ada pesanan yang menunggu.</p>
                <p v-if="totalOpen > orders.length" class="orders__more">Menampilkan {{ orders.length }} pesanan dari {{ totalOpen }}. Yang lain muncul setelah yang di atas selesai atau dibatalkan.</p>

                <div v-for="order in orders" :key="order.id" class="order">
                    <div class="order__main">
                        <b>{{ order.number }} <q-badge :color="order.status === 'ready' ? 'positive' : 'orange'" :label="order.status_label" /></b>
                        <small>
                            {{ dateFormat.format(new Date(order.ordered_at)) }} · {{ order.customer_name }} · {{ order.customer_phone }}<template v-if="order.expected_date"> · perkiraan datang {{ dateFormat.format(new Date(order.expected_date)) }}</template>
                        </small>
                        <small v-if="order.source_note">Dibeli dari: {{ order.source_note }}</small>
                        <small>{{ order.items.map((item) => `${formatQuantity(item.quantity)} ${item.unit} ${item.name}`).join(', ') }}</small>
                        <small v-if="Number(order.deposit) > 0">Uang muka diterima {{ formatRupiah(order.deposit) }} ({{ order.deposit_methods.join(', ') }})</small>
                    </div>
                    <b class="order__amount">{{ formatRupiah(order.remaining) }}<small>sisa</small></b>
                    <div class="order__actions">
                        <q-btn v-if="order.can_mark_ready" outline dense no-caps color="primary" label="Barang sudah datang" :disable="isWorking" @click="confirmReady(order)" />
                        <q-btn v-if="order.can_collect" unelevated dense no-caps color="primary" label="Ambil pesanan" :disable="isWorking" @click="collect(order)" />
                        <q-btn outline dense no-caps color="grey-8" icon="print" label="Cetak" :disable="isWorking" @click="printOrder(order)" />
                        <q-btn v-if="order.can_cancel" outline dense no-caps color="negative" label="Batalkan" :disable="isWorking" @click="openCancel(order)" />
                    </div>
                </div>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Tutup" v-close-popup />
            </q-card-actions>
        </q-card>

        <q-dialog :model-value="cancelTarget !== null" persistent @update:model-value="cancelTarget = null">
            <q-card v-if="cancelTarget" class="cancel">
                <q-card-section>
                    <div class="orders__title">Batalkan pesanan {{ cancelTarget.number }}?</div>
                    <small class="cancel__hint">Pesanan ditutup. Barang yang sudah disisihkan kembali bisa dijual.</small>
                </q-card-section>

                <q-card-section class="cancel__fields">
                    <q-input v-model="cancelReason" outlined dense autofocus maxlength="255" label="Alasan pembatalan" />
                    <template v-if="Number(cancelTarget.deposit) > 0">
                        <p v-if="cancelTarget.has_cash_deposit" class="cancel__note">Uang muka tunai dikembalikan dari laci shift Anda dan mengurangi tunai seharusnya.</p>
                        <q-input v-if="cancelTarget.has_non_cash_deposit" v-model="refundAccount" outlined dense maxlength="255" label="Rekening penerima pengembalian uang muka" placeholder="Bank, nomor rekening, atas nama" />
                    </template>
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps label="Kembali" :disable="isWorking" @click="cancelTarget = null" />
                    <q-btn color="negative" unelevated no-caps label="Batalkan Pesanan" :loading="isWorking" :disable="!canCancel()" @click="submitCancel" />
                </q-card-actions>
            </q-card>
        </q-dialog>
    </q-dialog>
</template>

<style scoped>
.orders {
    display: flex;
    flex-direction: column;
    width: 600px;
    max-width: 94vw;
    max-height: 90vh;
}

.orders__title {
    font-size: 17px;
    font-weight: 700;
}

.orders__head small {
    color: var(--app-mute);
}

.orders__body {
    position: relative;
    flex: 1;
    min-height: 120px;
    overflow-y: auto;
}

.orders__empty {
    margin: 0;
    padding: 24px 0;
    color: var(--app-mute);
    text-align: center;
}

.orders__more {
    margin: 0 0 8px;
    color: var(--app-mute);
    font-size: 12px;
}

.order {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--app-line);
}

.order__main b,
.order__main small {
    display: block;
}

.order__main small {
    margin-top: 2px;
    color: var(--app-mute);
    font-size: 12px;
}

.order__amount {
    align-self: center;
    text-align: right;
}

.order__amount small {
    display: block;
    color: var(--app-mute);
    font-size: 11px;
    font-weight: 400;
}

.order__actions {
    display: flex;
    flex-wrap: wrap;
    grid-column: 1 / -1;
    gap: 8px;
}

.cancel {
    width: 440px;
    max-width: 94vw;
}

.cancel__hint {
    color: var(--app-mute);
}

.cancel__fields {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.cancel__note {
    margin: 0;
    color: var(--app-ink-soft);
    font-size: 13px;
}
</style>
