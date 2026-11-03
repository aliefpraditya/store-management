<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRef, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import AppFooter from '../../../Components/AppFooter.vue';
import CloseShiftDialog from '../../../Components/Cashier/CloseShiftDialog.vue';
import PaymentDialog from '../../../Components/Cashier/PaymentDialog.vue';
import SaleDoneDialog from '../../../Components/Cashier/SaleDoneDialog.vue';
import ShiftClosedDialog from '../../../Components/Cashier/ShiftClosedDialog.vue';
import DeliveryNoteDialog from '../../../Components/Cashier/DeliveryNoteDialog.vue';
import ReturnDialog from '../../../Components/Cashier/ReturnDialog.vue';
import ReturnLookupDialog from '../../../Components/Cashier/ReturnLookupDialog.vue';
import CodDialog from '../../../Components/Cashier/CodDialog.vue';
import ExpenseDialog from '../../../Components/Cashier/ExpenseDialog.vue';
import ReceiptHistoryDialog from '../../../Components/Cashier/ReceiptHistoryDialog.vue';
import ShiftOpenPanel from '../../../Components/Cashier/ShiftOpenPanel.vue';
import { newUuid, useCart } from '../../../Composables/useCart';
import { useFlashMessages } from '../../../Composables/useFlashMessages';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity, formatStock } from '../../../Composables/useQuantityFormat';

const props = defineProps({
    shift: { type: Object, default: null },
    summary: { type: Object, default: null },
    registers: { type: Array, required: true },
    payment_methods: { type: Array, required: true },
    last_sale: { type: Object, default: null },
    closed_shift: { type: Object, default: null },
    can_leave_to_dashboard: { type: Boolean, required: true },
    urls: { type: Object, required: true },
});

const page = usePage();
const $q = useQuasar();

useFlashMessages();

const cart = useCart(toRef(() => props.shift?.id ?? null));

const SEARCH_DELAY_MS = 200;
const LOOKUP_FAILED_MESSAGE = 'Pencarian barang gagal. Periksa koneksi, lalu coba lagi.';
const LOOKUP_FAILED_NOTIFY_GROUP = 'lookup-failed';

const searchInput = ref(null);
const searchTerm = ref('');
const searchResults = ref([]);
let latestSearchId = 0;
let searchTimer;

const isPaymentOpen = ref(false);
const isPaying = ref(false);
const isCloseOpen = ref(false);
const isClosing = ref(false);
const isSaleDoneOpen = ref(false);
const isShiftClosedOpen = ref(false);
const isReceiptsOpen = ref(false);
const isDeliveryOpen = ref(false);
const deliveryReceipt = ref(null);
const isReturnLookupOpen = ref(false);
const isReturnOpen = ref(false);
const returnReceipt = ref(null);
const isExpenseOpen = ref(false);
const isCodOpen = ref(false);
let saleUuid = newUuid();

const timeFormat = new Intl.DateTimeFormat('id-ID', { timeStyle: 'short' });

const shiftSince = computed(() => (props.shift ? timeFormat.format(new Date(props.shift.opened_at)) : ''));

/**
 * Looks items up on the server. An answer that arrives after a newer search was started is dropped, so slow
 * responses cannot replace fresher results. A failed lookup is told to the cashier and leaves the results as they
 * were, so it is never mistaken for "no such item".
 * @returns {Promise<object[]|null>} null when the answer is stale or the lookup failed
 */
async function fetchResults(term) {
    const searchId = ++latestSearchId;

    try {
        const response = await fetch(`${props.urls.products}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        if (searchId !== latestSearchId) {
            return null;
        }

        if (!response.ok) {
            const failure = await response.json().catch(() => ({}));

            if (searchId === latestSearchId) {
                notifyLookupFailed(failure.code ? failure.message : LOOKUP_FAILED_MESSAGE);
            }

            return null;
        }

        return (await response.json()).data;
    } catch {
        if (searchId === latestSearchId) {
            notifyLookupFailed(LOOKUP_FAILED_MESSAGE);
        }

        return null;
    }
}

/**
 * One toast for a failing lookup, however many searches fail in a row while the cashier keeps typing.
 * @param {string} message
 */
function notifyLookupFailed(message) {
    $q.notify({ type: 'negative', message, position: 'top', group: LOOKUP_FAILED_NOTIFY_GROUP });
}

watch(searchTerm, (term) => {
    clearTimeout(searchTimer);

    if (term.trim() === '') {
        latestSearchId++;
        searchResults.value = [];

        return;
    }

    searchTimer = setTimeout(async () => {
        const foundItems = await fetchResults(term.trim());

        if (foundItems !== null) {
            searchResults.value = foundItems;
        }
    }, SEARCH_DELAY_MS);
});

function resetSearch() {
    searchTerm.value = '';
    searchResults.value = [];
    nextTick(() => searchInput.value?.focus());
}

/**
 * Enter: a scanned barcode adds the scanned unit at once; a single result adds its first unit. Anything else
 * leaves the results for the cashier to pick a unit.
 */
async function submitSearch() {
    const term = searchTerm.value.trim();

    if (term === '') {
        return;
    }

    clearTimeout(searchTimer);

    const foundItems = await fetchResults(term);

    if (foundItems === null) {
        return;
    }

    searchResults.value = foundItems;

    const scannedItem = foundItems.find((item) => item.matched_unit_id !== null);

    if (scannedItem) {
        cart.add(scannedItem, scannedItem.matched_unit_id);
        resetSearch();
    } else if (foundItems.length === 1) {
        cart.add(foundItems[0], foundItems[0].units[0].id);
        resetSearch();
    }
}

function addToCart(item, unit) {
    cart.add(item, unit.id);
    resetSearch();
}

function setLineUnit(line, code) {
    line.unitId = line.product.units.find((unit) => unit.code === code).id;
}

function stockTextOf(line) {
    return formatStock(line.product.stock, line.product.units, line.product.base_unit);
}

function openPayment() {
    if (cart.isEmpty.value) {
        $q.notify({ type: 'warning', message: 'Keranjang masih kosong.', position: 'top' });

        return;
    }

    if (cart.hasOverStock.value) {
        $q.notify({ type: 'negative', message: 'Ada barang yang melebihi stok. Kurangi jumlahnya.', position: 'top' });

        return;
    }

    isPaymentOpen.value = true;
}

function clearCart() {
    if (cart.isEmpty.value) {
        return;
    }

    $q.dialog({
        title: 'Kosongkan keranjang?',
        message: 'Semua barang di keranjang dibuang.',
        cancel: { label: 'Batal', flat: true, noCaps: true },
        ok: { label: 'Kosongkan', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => {
        cart.clear();
        nextTick(() => searchInput.value?.focus());
    });
}

function submitSale({ customer_name, payments, delivery_note }) {
    router.post(
        props.urls.sales,
        { client_uuid: saleUuid, customer_name, items: cart.saleItemsPayload.value, payments, delivery_note },
        {
            preserveScroll: true,
            onStart: () => (isPaying.value = true),
            onFinish: () => (isPaying.value = false),
            onSuccess: (successPage) => {
                if (successPage.props.last_sale) {
                    cart.clear();
                    isPaymentOpen.value = false;
                    saleUuid = newUuid();
                }
            },
            onError: (errors) => $q.notify({ type: 'negative', message: Object.values(errors)[0], position: 'top' }),
        },
    );
}

function openClose() {
    if (!cart.isEmpty.value) {
        $q.notify({ type: 'warning', message: 'Selesaikan atau kosongkan keranjang dulu.', position: 'top' });

        return;
    }

    isCloseOpen.value = true;
}

function closeShift(closingForm) {
    router.post(props.shift.close_url, closingForm, {
        onStart: () => (isClosing.value = true),
        onFinish: () => (isClosing.value = false),
        onSuccess: () => (isCloseOpen.value = false),
        onError: (errors) => $q.notify({ type: 'negative', message: Object.values(errors)[0], position: 'top' }),
    });
}

watch(
    () => props.last_sale,
    (sale) => {
        if (sale) {
            isSaleDoneOpen.value = true;
            nextTick(() => searchInput.value?.focus());
        }
    },
    { immediate: true },
);

watch(
    () => props.closed_shift,
    (closedShiftTotals) => {
        if (closedShiftTotals) {
            isShiftClosedOpen.value = true;
        }
    },
    { immediate: true },
);

/**
 * Opens the delivery note dialog for a receipt, from the receipt list or from the sale that was just paid; the
 * dialog that sent it makes way for it.
 */
function openDelivery(receipt) {
    deliveryReceipt.value = receipt;
    isSaleDoneOpen.value = false;
    isDeliveryOpen.value = true;
}

function confirmSignOut() {
    $q.dialog({
        title: 'Keluar?',
        message: 'Anda akan keluar dari akun ini.',
        cancel: { label: 'Batal', flat: true, noCaps: true },
        ok: { label: 'Keluar', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(() => router.post(page.props.app_urls.logout));
}

/** Opens the return dialog for a receipt of the receipt list. */
function openReturn(receipt) {
    returnReceipt.value = receipt;
    isReturnOpen.value = true;
}

/** A finished return or an expense changes the drawer, so the figures of the close-shift dialog are fetched again. */
const refreshShiftSummary = () => router.reload({ only: ['summary'] });

/** F9 pays, F8 empties the cart, F2 goes to the search box. */
function onKeydown(event) {
    if (!props.shift) {
        return;
    }

    const shortcuts = { F9: openPayment, F8: clearCart, F2: () => searchInput.value?.focus() };

    if (shortcuts[event.key] && !isPaymentOpen.value && !isCloseOpen.value && !isReceiptsOpen.value && !isDeliveryOpen.value && !isReturnLookupOpen.value && !isReturnOpen.value && !isExpenseOpen.value && !isCodOpen.value) {
        event.preventDefault();
        shortcuts[event.key]();
    }
}

/**
 * The cart survives a refresh in the browser, with the prices and stock it had then. They are fetched again, so the
 * total on the screen is the one the server will charge; what changed is told to the cashier.
 */
async function refreshCart() {
    try {
        const { changed, removed } = await cart.refresh(async (unitIds) => {
            const unitsQuery = unitIds.map((unitId) => `units[]=${unitId}`).join('&');
            const response = await fetch(`${props.urls.products}?${unitsQuery}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (!response.ok) {
                throw new Error('Cart lookup failed');
            }

            return (await response.json()).data;
        });

        if (removed.length > 0) {
            $q.notify({ type: 'warning', position: 'top', message: `${removed.map((line) => line.product.name).join(', ')} tidak bisa dijual lagi, dihapus dari keranjang.` });
        }

        if (changed.length > 0) {
            $q.notify({
                type: 'warning',
                position: 'top',
                message: `Harga berubah: ${changed.map(({ line, priceBefore }) => `${line.product.name} ${formatRupiah(priceBefore)} → ${formatRupiah(cart.priceOf(line))}`).join(', ')}.`,
            });
        }
    } catch {
        // The server prices the sale again anyway; a failed refresh only leaves the screen's figures as they were.
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    searchInput.value?.focus();

    if (props.shift) {
        refreshCart();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    clearTimeout(searchTimer);
});
</script>

<template>
    <Head title="Kasir" />

    <q-layout view="hHh lpR fFf">
        <q-page-container>
            <q-page class="till">
                <header class="till__bar">
                    <div class="till__who">
                        <b>{{ page.props.store.name }}</b>
                        <small v-if="shift">{{ shift.register.code }} · {{ shift.register.name }} · {{ shift.cashier_name }} · shift sejak {{ shiftSince }}</small>
                        <small v-else>Belum ada shift</small>
                    </div>
                    <div class="till__bar-actions">
                        <q-btn v-if="shift" flat no-caps dense icon="receipt_long" label="Riwayat Nota" @click="isReceiptsOpen = true" />
                        <q-btn v-if="shift" flat no-caps dense icon="undo" label="Retur" @click="isReturnLookupOpen = true" />
                        <q-btn v-if="shift" flat no-caps dense icon="local_shipping" label="COD" @click="isCodOpen = true" />
                        <q-btn v-if="shift" flat no-caps dense icon="remove_circle_outline" label="Pengeluaran" @click="isExpenseOpen = true" />
                        <q-btn v-if="shift" flat no-caps dense color="negative" icon="lock_clock" label="Tutup Shift" @click="openClose">
                            <q-tooltip>Tutup shift dulu untuk keluar</q-tooltip>
                        </q-btn>
                        <q-btn v-if="can_leave_to_dashboard" flat no-caps dense icon="space_dashboard" label="Dashboard" @click="router.visit(urls.dashboard)" />
                        <q-btn v-if="!shift" flat no-caps dense icon="logout" label="Keluar" @click="confirmSignOut" />
                    </div>
                </header>

                <ShiftOpenPanel v-if="!shift" :registers="registers" :open-url="urls.open_shift" :store-name="page.props.store.name" />

                <div v-else class="till__main">
                    <section class="finder">
                        <q-input
                            ref="searchInput"
                            v-model="searchTerm"
                            outlined
                            clearable
                            placeholder="Cari nama, SKU, atau scan barcode (Enter)"
                            class="finder__input"
                            @keydown.enter.prevent="submitSearch"
                        >
                            <template #prepend><q-icon name="search" /></template>
                        </q-input>

                        <p v-if="searchTerm.trim() === ''" class="finder__hint">Ketik atau scan barang. F2 fokus ke pencarian, F9 bayar, F8 kosongkan keranjang.</p>
                        <p v-else-if="searchResults.length === 0" class="finder__hint">Tidak ada barang yang cocok.</p>

                        <ul class="finder__results">
                            <li v-for="item in searchResults" :key="item.id" class="item">
                                <div class="item__info">
                                    <b>{{ item.name }}</b>
                                    <small :class="{ item__empty: Number(item.stock) <= 0 }">{{ item.sku }} · {{ Number(item.stock) <= 0 ? 'stok habis' : `stok ${formatStock(item.stock, item.units, item.base_unit)}` }}</small>
                                </div>
                                <div class="item__units">
                                    <q-btn
                                        v-for="unit in item.units"
                                        :key="unit.id"
                                        outline
                                        no-caps
                                        dense
                                        color="primary"
                                        class="item__unit"
                                        :disable="Number(item.stock) <= 0"
                                        :aria-label="`Tambah ${item.name} ${unit.code}`"
                                        @click="addToCart(item, unit)"
                                    >
                                        <span>{{ unit.code }}</span>
                                        <small>{{ formatRupiah(unit.price) }}</small>
                                    </q-btn>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <section class="cart">
                        <div class="cart__lines">
                            <p v-if="cart.isEmpty.value" class="cart__empty">Keranjang kosong</p>

                            <div v-for="line in cart.lines.value" :key="line.key" class="line" :class="{ 'line--over': cart.isOverStock(line) }">
                                <div class="line__name">
                                    <b>{{ line.product.name }}</b>
                                    <small v-if="cart.isOverStock(line)" class="line__warning">Stok tersisa {{ stockTextOf(line) }}</small>
                                    <small v-else>{{ formatRupiah(cart.priceOf(line)) }} / {{ cart.unitOf(line).code }}</small>
                                </div>
                                <q-input
                                    v-model="line.quantity"
                                    type="number"
                                    min="0"
                                    step="any"
                                    outlined
                                    dense
                                    hide-bottom-space
                                    class="line__quantity"
                                    :aria-label="`Jumlah ${line.product.name}`"
                                />
                                <q-select
                                    :model-value="cart.unitOf(line).code"
                                    :options="line.product.units.map((unit) => unit.code)"
                                    outlined
                                    dense
                                    hide-bottom-space
                                    class="line__unit"
                                    :aria-label="`Satuan ${line.product.name}`"
                                    @update:model-value="setLineUnit(line, $event)"
                                />
                                <b class="line__subtotal">{{ formatRupiah(cart.subtotalOf(line)) }}</b>
                                <q-btn flat round dense icon="close" color="grey-7" :aria-label="`Hapus ${line.product.name}`" @click="cart.remove(line)" />
                            </div>
                        </div>

                        <footer class="cart__foot">
                            <div class="cart__total">
                                <span>Total <small>({{ cart.lines.value.length }} baris, {{ formatQuantity(cart.lines.value.reduce((sum, line) => sum + cart.quantityOf(line), 0)) }} item)</small></span>
                                <b>{{ formatRupiah(cart.total.value) }}</b>
                            </div>
                            <div class="cart__buttons">
                                <q-btn flat no-caps color="grey-8" label="Batal (F8)" :disable="cart.isEmpty.value" @click="clearCart" />
                                <q-btn color="primary" unelevated no-caps size="lg" class="cart__pay" label="Bayar (F9)" :disable="cart.isEmpty.value" @click="openPayment" />
                            </div>
                        </footer>
                    </section>
                </div>

                <AppFooter />
            </q-page>
        </q-page-container>

        <PaymentDialog v-model="isPaymentOpen" :total="cart.total.value" :payment-methods="payment_methods" :is-processing="isPaying" @confirm="submitSale" />
        <CloseShiftDialog v-if="summary" v-model="isCloseOpen" :summary="summary" :payment-methods="payment_methods.filter((method) => method.value !== 'cod')" :is-processing="isClosing" @confirm="closeShift" />
        <SaleDoneDialog v-model="isSaleDoneOpen" :sale="last_sale" @delivery="openDelivery" />
        <ShiftClosedDialog v-model="isShiftClosedOpen" :shift-totals="closed_shift" />
        <ReceiptHistoryDialog v-if="shift" v-model="isReceiptsOpen" :receipts-url="urls.receipts" :history-url="urls.sales_history" :can-open-history="can_leave_to_dashboard" @delivery="openDelivery" @return="openReturn" />
        <DeliveryNoteDialog v-model="isDeliveryOpen" :receipt="deliveryReceipt" />
        <ReturnLookupDialog v-if="shift" v-model="isReturnLookupOpen" :lookup-url="urls.return_lookup" @found="openReturn" />
        <ReturnDialog v-model="isReturnOpen" :receipt="returnReceipt" :products-url="urls.products" @saved="refreshShiftSummary" />
        <CodDialog v-if="shift" v-model="isCodOpen" :list-url="urls.cod" @changed="refreshShiftSummary" />
        <ExpenseDialog v-if="shift" v-model="isExpenseOpen" :list-url="urls.expenses" :store-url="urls.expenses_store" :expected-cash="summary?.expected_cash ?? null" @saved="refreshShiftSummary" />
    </q-layout>
</template>

<style scoped>
.till {
    display: flex;
    flex-direction: column;
    height: 100vh;
    background: var(--app-bg);
}

.till__bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 20px;
    border-bottom: 1px solid var(--app-line);
    background: var(--app-surface);
}

.till__who b,
.till__who small {
    display: block;
}

.till__who small {
    color: var(--app-mute);
    font-size: 12px;
}

.till__bar-actions {
    display: flex;
    gap: 4px;
}

.till__main {
    display: grid;
    flex: 1;
    grid-template-columns: minmax(0, 1.3fr) minmax(360px, 1fr);
    gap: 16px;
    min-height: 0;
    padding: 16px 20px 0;
}

.finder {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-height: 0;
}

.finder__input {
    background: var(--app-surface);
}

.finder__hint {
    margin: 0;
    color: var(--app-mute);
    font-size: 13px;
}

.finder__results {
    flex: 1;
    margin: 0;
    padding: 0;
    overflow-y: auto;
    list-style: none;
}

.item {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    margin-bottom: 8px;
    padding: 10px 14px;
    border: 1px solid var(--app-line);
    border-radius: 12px;
    background: var(--app-surface);
}

.item__info b,
.item__info small {
    display: block;
}

.item__info small {
    color: var(--app-mute);
    font-size: 12px;
}

.item__info .item__empty {
    color: #be123c;
    font-weight: 600;
}

.item__units {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.item__unit :deep(.q-btn__content) {
    flex-direction: column;
    line-height: 1.2;
}

.item__unit small {
    font-size: 11px;
}

.cart {
    display: flex;
    flex-direction: column;
    min-height: 0;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.cart__lines {
    flex: 1;
    overflow-y: auto;
}

.cart__empty {
    margin: 0;
    padding: 36px 16px;
    color: var(--app-mute);
    text-align: center;
}

.line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 84px 92px auto auto;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--app-line);
}

.line--over {
    background: #fff5f6;
}

.line__name b,
.line__name small {
    display: block;
}

.line__name small {
    color: var(--app-mute);
    font-size: 12px;
}

.line__name .line__warning {
    color: #be123c;
    font-weight: 600;
}

.line__subtotal {
    min-width: 96px;
    text-align: right;
}

.cart__foot {
    padding: 14px;
    border-top: 1px solid var(--app-line);
}

.cart__total {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 10px;
}

.cart__total small {
    color: var(--app-mute);
}

.cart__total b {
    font-size: 26px;
}

.cart__buttons {
    display: flex;
    gap: 8px;
}

.cart__pay {
    flex: 1;
}

@media screen and (max-width: 900px) {
    .till {
        height: auto;
        min-height: 100vh;
    }

    .till__main {
        grid-template-columns: 1fr;
    }
}
</style>
