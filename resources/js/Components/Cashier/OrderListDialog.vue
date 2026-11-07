<script setup>
import { computed, ref, watch } from 'vue';
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

const emit = defineEmits(['update:modelValue', 'changed', 'collect', 'collect-paid']);

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

const search = ref('');
const tab = ref('ready');

const TABS = [
    { value: 'ready', label: 'Siap diambil' },
    { value: 'arrived', label: 'Perlu ditandai datang' },
    { value: 'waiting', label: 'Menunggu barang' },
];

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });

/**
 * Which list an order is in: ready for the customer, goods in stock that only need marking as arrived, or still waiting
 * for the goods.
 */
const tabOf = (order) => {
    if (order.status === 'ready') {
        return 'ready';
    }

    return order.stock_check?.is_covered ? 'arrived' : 'waiting';
};

const countOf = (value) => orders.value.filter((order) => tabOf(order) === value).length;

const isPaidInFull = (order) => Number(order.remaining) === 0 && Number(order.deposit) > 0;

const normalizedSearch = computed(() => search.value.trim().toLowerCase());

const isSearching = computed(() => normalizedSearch.value !== '');

/** Searching looks through every list at once, by number (what a scanned barcode types), name or phone. */
const visibleOrders = computed(() => {
    if (isSearching.value) {
        return orders.value.filter((order) => [order.number, order.customer_name, order.customer_phone].some((value) => value.toLowerCase().includes(normalizedSearch.value)));
    }

    return orders.value.filter((order) => tabOf(order) === tab.value);
});

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
        tab.value = TABS.find((candidate) => countOf(candidate.value) > 0)?.value ?? 'ready';
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
            search.value = '';
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

/**
 * Hands the goods to the customer. An order already paid in full needs no payment screen, only a confirmation that says
 * so; any other order goes to the payment screen for what is left.
 */
function collect(order) {
    if (order.remaining !== undefined && Number(order.remaining) === 0) {
        $q.dialog({
            title: 'Pesanan sudah lunas',
            message: `${order.number} atas nama ${order.customer_name} sudah dibayar lunas (uang muka ${formatRupiah(order.deposit)}). Serahkan barangnya ke pelanggan, tidak ada yang perlu dibayar lagi.`,
            cancel: { label: 'Kembali', flat: true, noCaps: true },
            ok: { label: 'Serahkan Barang', color: 'positive', unelevated: true, noCaps: true },
            persistent: true,
        }).onOk(() => {
            emit('collect-paid', order);
            emit('update:modelValue', false);
        });

        return;
    }

    emit('collect', order);
    emit('update:modelValue', false);
}

/** The one thing to do next with an order, by the list it is in. */
function proceed(order) {
    if (order.can_collect) {
        collect(order);
    } else if (order.can_mark_ready) {
        confirmReady(order);
    }
}

/** Enter in the search box acts on the order found, so scanning the number on a slip is enough to start. */
function onSearchEnter() {
    if (visibleOrders.value.length === 1) {
        proceed(visibleOrders.value[0]);
    }
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
                <div class="orders__title">Pesanan pelanggan</div>
                <q-input
                    v-model="search"
                    outlined
                    dense
                    clearable
                    autofocus
                    placeholder="Cari atau scan nomor pesanan, nama, telepon (Enter)"
                    class="orders__search"
                    @keydown.enter.prevent="onSearchEnter"
                >
                    <template #prepend><q-icon name="search" /></template>
                </q-input>
            </q-card-section>

            <q-tabs v-if="!isSearching" v-model="tab" dense no-caps align="left" active-color="primary" indicator-color="primary" class="orders__tabs">
                <q-tab v-for="candidate in TABS" :key="candidate.value" :name="candidate.value">
                    <span>{{ candidate.label }}</span>
                    <q-badge v-if="countOf(candidate.value) > 0" :color="candidate.value === 'arrived' ? 'negative' : candidate.value === 'ready' ? 'positive' : 'grey-6'" :label="countOf(candidate.value)" class="orders__tab-count" />
                </q-tab>
            </q-tabs>
            <small v-else class="orders__found">Hasil pencarian di semua daftar</small>

            <q-card-section class="orders__body">
                <q-inner-loading :showing="isLoading" />

                <p v-if="!isLoading && visibleOrders.length === 0" class="orders__empty">{{ isSearching ? 'Tidak ada pesanan yang cocok.' : 'Tidak ada pesanan di daftar ini.' }}</p>
                <p v-if="totalOpen > orders.length" class="orders__more">Menampilkan {{ orders.length }} pesanan dari {{ totalOpen }}. Yang lain muncul setelah yang di atas selesai atau dibatalkan.</p>

                <div v-for="order in visibleOrders" :key="order.id" class="order">
                    <div class="order__main">
                        <b>
                            {{ order.number }}
                            <q-badge :color="order.status === 'ready' ? 'positive' : 'orange'" :label="order.status_label" />
                            <q-badge v-if="isPaidInFull(order)" color="positive" outline label="LUNAS" />
                        </b>
                        <small>{{ dateFormat.format(new Date(order.ordered_at)) }} · {{ order.customer_name }} · {{ order.customer_phone }}</small>
                        <small>{{ order.items.map((item) => `${formatQuantity(item.quantity)} ${item.unit} ${item.name}`).join(', ') }}</small>
                        <small v-if="order.customer_address">Alamat: {{ order.customer_address }}</small>
                        <small v-if="order.origin_sale_number">
                            Dibuat bersama nota {{ order.origin_sale_number }}<template v-if="order.origin_sale_undelivered.length > 0">, belum diantar: {{ order.origin_sale_undelivered.map((line) => `${formatQuantity(line.remaining)} ${line.unit} ${line.name}`).join(', ') }} (bisa diantar sekalian)</template>
                        </small>
                        <small v-if="Number(order.deposit) > 0 && !isPaidInFull(order)">Uang muka diterima {{ formatRupiah(order.deposit) }} ({{ order.deposit_methods.join(', ') }})</small>
                        <small v-if="order.stock_check?.is_covered" class="order__covered">Stok sudah cukup.</small>
                        <small v-else-if="order.stock_check" class="order__short">Stok belum cukup, kurang: {{ order.stock_check.shortages.map((shortage) => `${shortage.name} ${shortage.text}`).join(', ') }}</small>
                    </div>
                    <b class="order__amount">{{ isPaidInFull(order) ? 'Lunas' : formatRupiah(order.remaining) }}<small>{{ isPaidInFull(order) ? 'tidak ada sisa' : 'sisa' }}</small></b>
                    <div class="order__actions">
                        <q-btn v-if="order.can_collect" unelevated dense no-caps color="primary" :label="isPaidInFull(order) ? 'Serahkan barang (lunas)' : 'Ambil / antar pesanan'" :disable="isWorking" @click="collect(order)" />
                        <q-btn v-else-if="order.can_mark_ready" unelevated dense no-caps color="primary" label="Barang sudah datang" :disable="isWorking || !order.stock_check?.is_covered" @click="confirmReady(order)" />
                        <q-btn flat dense round icon="more_vert" color="grey-8" :disable="isWorking" aria-label="Cetak atau batalkan pesanan">
                            <q-menu auto-close>
                                <q-list dense>
                                    <q-item clickable @click="printOrder(order)">
                                        <q-item-section avatar><q-icon name="print" /></q-item-section>
                                        <q-item-section>Cetak nota pesanan</q-item-section>
                                    </q-item>
                                    <q-item v-if="order.can_cancel" clickable class="text-negative" @click="openCancel(order)">
                                        <q-item-section avatar><q-icon name="cancel" /></q-item-section>
                                        <q-item-section>Batalkan pesanan</q-item-section>
                                    </q-item>
                                </q-list>
                            </q-menu>
                        </q-btn>
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
    width: 640px;
    max-width: 94vw;
    max-height: 90vh;
}

.orders__title {
    margin-bottom: 8px;
    font-size: 17px;
    font-weight: 700;
}

.orders__tabs {
    border-bottom: 1px solid var(--app-line);
}

.orders__tab-count {
    margin-left: 6px;
}

.orders__found {
    padding: 0 16px 8px;
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

.order__main b {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.order__main small {
    margin-top: 2px;
    color: var(--app-mute);
    font-size: 12px;
}

.order__main .order__covered {
    color: #047857;
    font-weight: 600;
}

.order__main .order__short {
    color: #b45309;
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
    align-items: center;
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
