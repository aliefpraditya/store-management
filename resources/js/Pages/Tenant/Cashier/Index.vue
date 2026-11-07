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
import OrderListDialog from '../../../Components/Cashier/OrderListDialog.vue';
import ExpenseDialog from '../../../Components/Cashier/ExpenseDialog.vue';
import HeldCartsDialog from '../../../Components/Cashier/HeldCartsDialog.vue';
import ReceiptHistoryDialog from '../../../Components/Cashier/ReceiptHistoryDialog.vue';
import ShiftOpenPanel from '../../../Components/Cashier/ShiftOpenPanel.vue';
import { newUuid, useCart } from '../../../Composables/useCart';
import { useFlashMessages } from '../../../Composables/useFlashMessages';
import { useHeldCarts } from '../../../Composables/useHeldCarts';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity, formatStock } from '../../../Composables/useQuantityFormat';

const props = defineProps({
    shift: { type: Object, default: null },
    summary: { type: Object, default: null },
    registers: { type: Array, required: true },
    payment_methods: { type: Array, required: true },
    last_sale: { type: Object, default: null },
    /** The customer order made by the last checkout, alone or together with `last_sale`. */
    last_order: { type: Object, default: null },
    /** Open orders, orders ready to be collected, and waiting orders whose goods are already in stock (they only need marking). */
    order_counts: { type: Object, default: () => ({ open: 0, ready: 0, arrived: 0 }) },
    /** Returns of this shift that wait for an Admin's approval. */
    pending_return_count: { type: Number, default: 0 },
    /** The items this warehouse sold most lately, offered when nothing is searched. */
    best_sellers: { type: Array, default: () => [] },
    /** Whether the receipt opens for printing by itself once a sale is paid. */
    is_receipt_printed_automatically: { type: Boolean, default: false },
    closed_shift: { type: Object, default: null },
    can_leave_to_dashboard: { type: Boolean, required: true },
    urls: { type: Object, required: true },
});

const page = usePage();
const $q = useQuasar();

useFlashMessages();

const cart = useCart(toRef(() => props.shift?.id ?? null));
const heldCarts = useHeldCarts(toRef(() => props.shift?.id ?? null));

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
const isOrdersOpen = ref(false);
const isHeldOpen = ref(false);

/** The result the arrow keys stand on (-1 for none), and which of its units Enter adds. */
const highlightedResultIndex = ref(-1);
const highlightedUnitIndex = ref(0);

/** The cart line the arrow keys stand on while the search box is empty. */
const selectedLineKey = ref(null);

const isAnyDialogOpen = computed(
    () => [isPaymentOpen, isCloseOpen, isReceiptsOpen, isDeliveryOpen, isReturnLookupOpen, isReturnOpen, isExpenseOpen, isCodOpen, isOrdersOpen, isHeldOpen, isSaleDoneOpen].some((isOpen) => isOpen.value),
);

/** The customer order whose goods the customer is collecting; the payment dialog then pays what is left of it. */
const collectingOrder = ref(null);

let saleUuid = newUuid();
let orderUuid = newUuid();

/** What the payment dialog charges now: what is left of the order being collected, or the part of the cart that is in stock. */
const paymentTotal = computed(() => (collectingOrder.value ? Number(collectingOrder.value.remaining) : cart.split.value.saleTotal));

/** The cart divided into receipt and order, only when part of it is short of stock. */
const splitForPayment = computed(() => (collectingOrder.value || cart.split.value.orderLines.length === 0 ? null : cart.split.value));

const timeFormat = new Intl.DateTimeFormat('id-ID', { timeStyle: 'short' });

const shiftSince = computed(() => (props.shift ? timeFormat.format(new Date(props.shift.opened_at)) : ''));

/** The clock the running time of the shift is read from; it moves every half minute, which is enough for minutes. */
const now = ref(Date.now());
let clockTimer;

const shiftRunningFor = computed(() => {
    if (!props.shift) {
        return '';
    }

    const minutes = Math.max(0, Math.floor((now.value - new Date(props.shift.opened_at).getTime()) / 60000));
    const hours = Math.floor(minutes / 60);

    return hours > 0 ? `${hours} jam ${minutes % 60} menit` : `${minutes} menit`;
});

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
    highlightedResultIndex.value = -1;

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

/** The unit an item is sold in unless the cashier picks another: the one marked as the default for sale. */
const defaultUnitOf = (item) => item.units.find((unit) => unit.is_default_sale) ?? item.units[0];

/** Arrow up and down move through the results; the unit chosen on an item starts at its default. */
function moveHighlight(step) {
    const lastIndex = searchResults.value.length - 1;
    const nextIndex = Math.min(Math.max(highlightedResultIndex.value + step, 0), lastIndex);

    highlightedResultIndex.value = nextIndex;
    highlightedUnitIndex.value = Math.max(searchResults.value[nextIndex].units.indexOf(defaultUnitOf(searchResults.value[nextIndex])), 0);

    nextTick(() => document.querySelector('.item--highlight')?.scrollIntoView({ block: 'nearest' }));
}

/** Arrow left and right choose the unit of the highlighted result that Enter will add. */
function moveHighlightedUnit(step) {
    const unitCount = searchResults.value[highlightedResultIndex.value].units.length;

    highlightedUnitIndex.value = Math.min(Math.max(highlightedUnitIndex.value + step, 0), unitCount - 1);
}

function moveLineSelection(step) {
    const lines = cart.lines.value;

    if (lines.length === 0) {
        return;
    }

    const currentIndex = lines.findIndex((line) => line.key === selectedLineKey.value);
    const nextIndex = currentIndex === -1 ? (step > 0 ? 0 : lines.length - 1) : Math.min(Math.max(currentIndex + step, 0), lines.length - 1);

    selectedLineKey.value = lines[nextIndex].key;
    nextTick(() => document.querySelector('.line--selected')?.scrollIntoView({ block: 'nearest' }));
}

/** + and - change the quantity of the selected line by one; Delete takes it out. Only while the search box is empty. */
function changeSelectedLine(event) {
    const line = cart.lines.value.find((candidate) => candidate.key === selectedLineKey.value);

    if (!line) {
        return false;
    }

    if (event.key === '+' || event.key === '=') {
        line.quantity = cart.quantityOf(line) + 1;
    } else if (event.key === '-') {
        line.quantity = Math.max(1, cart.quantityOf(line) - 1);
    } else if (event.key === 'Delete') {
        const index = cart.lines.value.indexOf(line);

        cart.remove(line);
        selectedLineKey.value = cart.lines.value[Math.min(index, cart.lines.value.length - 1)]?.key ?? null;
    } else {
        return false;
    }

    event.preventDefault();

    return true;
}

/**
 * The keys of the search box. With results: up and down choose an item, left and right its unit, Enter adds it. With the
 * box empty: up and down choose a cart line, + and - change its quantity, Delete removes it. Escape lets go of both.
 */
function onSearchKeydown(event) {
    const isSearching = searchTerm.value.trim() !== '' && searchResults.value.length > 0;

    if (event.key === 'Escape') {
        highlightedResultIndex.value = -1;
        selectedLineKey.value = null;

        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();

        if (isSearching) {
            moveHighlight(event.key === 'ArrowDown' ? 1 : -1);
        } else if (searchTerm.value === '') {
            moveLineSelection(event.key === 'ArrowDown' ? 1 : -1);
        }

        return;
    }

    if (isSearching && highlightedResultIndex.value >= 0 && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
        event.preventDefault();
        moveHighlightedUnit(event.key === 'ArrowRight' ? 1 : -1);

        return;
    }

    if (searchTerm.value === '') {
        changeSelectedLine(event);
    }
}

/**
 * Enter: the highlighted result is added in its chosen unit; a scanned barcode adds the scanned unit at once; a single
 * result adds its default unit. Anything else leaves the results for the cashier to pick.
 */
async function submitSearch() {
    const term = searchTerm.value.trim();

    if (term === '') {
        return;
    }

    const highlightedItem = searchResults.value[highlightedResultIndex.value];

    if (highlightedItem && !isItemHeldBack(highlightedItem)) {
        addToCart(highlightedItem, highlightedItem.units[highlightedUnitIndex.value]);

        return;
    }

    clearTimeout(searchTimer);

    const foundItems = await fetchResults(term);

    if (foundItems === null) {
        return;
    }

    searchResults.value = foundItems;

    const scannedItem = foundItems.find((item) => item.matched_unit_id !== null);

    // A typed or scanned SKU that is exactly one item's SKU is that item, even when other items' SKUs contain it.
    const itemOfExactSku = foundItems.find((item) => item.sku.toLowerCase() === term.toLowerCase());

    if (scannedItem) {
        addAndShow(scannedItem, scannedItem.matched_unit_id);
    } else if (itemOfExactSku && !isItemHeldBack(itemOfExactSku)) {
        addAndShow(itemOfExactSku, defaultUnitOf(itemOfExactSku).id);
    } else if (foundItems.length === 1) {
        addAndShow(foundItems[0], defaultUnitOf(foundItems[0]).id);
    }
}

/**
 * Adds one of the item (the same item and unit already in the cart gets one more) and points at that line, so after a scan
 * the cashier sees which line grew and by how much.
 */
function addAndShow(item, unitId) {
    selectedLineKey.value = cart.add(item, unitId).key;
    resetSearch();
    nextTick(() => document.querySelector('.line--selected')?.scrollIntoView({ block: 'nearest' }));
}

function addToCart(item, unit) {
    addAndShow(item, unit.id);
}

/** The nth best seller of the empty till is added in its default unit (Alt and the number, or a click). */
function addBestSeller(item) {
    if (!isItemHeldBack(item)) {
        addToCart(item, defaultUnitOf(item));
    }
}

function setLineUnit(line, code) {
    line.unitId = line.product.units.find((unit) => unit.code === code).id;
}

function stockTextOf(line) {
    return formatStock(line.product.stock, line.product.units, line.product.base_unit, line.product.intact_packs);
}

/**
 * An item that is sold out can still be added: the part the shelf cannot serve becomes a customer order at the payment
 * screen. Only an item an opname is counting is held back, since it cannot be sold.
 * @param {object} item an item from the lookup
 */
function isItemHeldBack(item) {
    return item.is_being_counted;
}

function openPayment() {
    if (cart.isEmpty.value) {
        $q.notify({ type: 'warning', message: 'Keranjang masih kosong.', position: 'top' });

        return;
    }

    isPaymentOpen.value = true;
}

/** Collecting an order pays what is left of it; the cart is not touched. */
function startCollecting(order) {
    collectingOrder.value = order;
    isPaymentOpen.value = true;
}

watch(isPaymentOpen, (isOpen) => {
    if (!isOpen) {
        collectingOrder.value = null;
    }
});

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

/**
 * What "Bayar" sends: the order being collected, or the cart divided into the receipt for what is in stock and the order
 * for what is short. The server makes both in one transaction.
 */
function checkoutBody({ customer_name, payments, delivery_note, order, hold_delivery }) {
    const body = { client_uuid: saleUuid, customer_name, payments, delivery_note, hold_delivery };

    if (collectingOrder.value) {
        return { ...body, customer_order_id: collectingOrder.value.id };
    }

    const inStockItems = cart.inStockItemsPayload.value;

    return {
        ...body,
        ...(inStockItems.length > 0 ? { items: inStockItems } : {}),
        ...(order ? { order: { ...order, client_uuid: orderUuid, items: cart.shortItemsPayload.value } } : {}),
    };
}

function submitSale(checkout) {
    const isCollecting = collectingOrder.value !== null;

    router.post(props.urls.sales, checkoutBody(checkout), {
        preserveScroll: true,
        onStart: () => (isPaying.value = true),
        onFinish: () => {
            isPaying.value = false;

            if (!isPaymentOpen.value) {
                collectingOrder.value = null;
            }
        },
        onSuccess: (successPage) => {
            if (successPage.props.last_sale || successPage.props.last_order) {
                if (!isCollecting) {
                    cart.clear();
                }

                isPaymentOpen.value = false;
                saleUuid = newUuid();
                orderUuid = newUuid();
            }
        },
        onError: (errors) => $q.notify({ type: 'negative', message: Object.values(errors)[0], position: 'top' }),
    });
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
    () => [props.last_sale, props.last_order],
    ([sale, order]) => {
        if (sale || order) {
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
const refreshShiftSummary = () => router.reload({ only: ['summary', 'order_counts', 'pending_return_count'] });

/**
 * F4 puts the cart on hold, or, when the cart is empty, brings a held one back. The cart being worked on goes on hold
 * first when another is brought back, so nothing is lost.
 */
function holdOrRecallCart() {
    if (!cart.isEmpty.value) {
        heldCarts.hold(cart.lines.value);
        cart.clear();
        $q.notify({ type: 'info', position: 'top', message: 'Keranjang ditahan. Tekan F4 untuk memanggilnya lagi.' });
        nextTick(() => searchInput.value?.focus());

        return;
    }

    if (heldCarts.held.value.length === 0) {
        $q.notify({ type: 'info', position: 'top', message: 'Belum ada keranjang yang ditahan.' });

        return;
    }

    isHeldOpen.value = true;
}

function resumeHeldCart(id) {
    const heldLines = heldCarts.take(id);

    if (!cart.isEmpty.value) {
        heldCarts.hold(cart.lines.value);
    }

    cart.lines.value = heldLines;
    isHeldOpen.value = false;
    nextTick(() => searchInput.value?.focus());
    refreshCart();
}

/**
 * Collecting an order that is already paid in full needs no payment screen: the cashier confirms the goods are handed
 * over and the receipt is made with the deposit as its payment.
 */
function collectPaidOrder(order) {
    collectingOrder.value = order;
    submitSale({ customer_name: null, payments: [], delivery_note: null, order: null, hold_delivery: undefined });
}

/** F9 pays, F8 empties the cart, F4 holds or recalls a cart, F7 opens the orders, F2 goes to the search box, Alt and 1 to 9 add a best seller. */
function onKeydown(event) {
    if (!props.shift || isAnyDialogOpen.value) {
        return;
    }

    const bestSellerIndex = event.altKey && /^[1-9]$/.test(event.key) ? Number(event.key) - 1 : -1;

    if (bestSellerIndex >= 0 && props.best_sellers[bestSellerIndex]) {
        event.preventDefault();
        addBestSeller(props.best_sellers[bestSellerIndex]);

        return;
    }

    const shortcuts = { F9: openPayment, F8: clearCart, F4: holdOrRecallCart, F7: () => (isOrdersOpen.value = true), F2: () => searchInput.value?.focus() };

    if (shortcuts[event.key]) {
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

/**
 * Goods come into stock from another screen, so the badges are fetched again now and then; without it the badge of a
 * waiting order would stay as it was until the cashier reloads the page.
 */
const BADGE_REFRESH_MS = 60000;
let badgeTimer;

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    searchInput.value?.focus();
    clockTimer = setInterval(() => (now.value = Date.now()), 30000);

    if (props.shift) {
        refreshCart();
        badgeTimer = setInterval(() => router.reload({ only: ['order_counts', 'pending_return_count'] }), BADGE_REFRESH_MS);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    clearTimeout(searchTimer);
    clearInterval(badgeTimer);
    clearInterval(clockTimer);
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
                        <small v-if="shift">{{ shift.register.code }} · {{ shift.register.name }} · {{ shift.cashier_name }} · shift sejak {{ shiftSince }}, berjalan {{ shiftRunningFor }}</small>
                        <small v-else>Belum ada shift</small>
                    </div>
                    <div class="till__bar-actions">
                        <q-btn v-if="shift" flat no-caps dense icon="receipt_long" label="Riwayat Nota" @click="isReceiptsOpen = true" />
                        <q-btn v-if="shift" flat no-caps dense icon="undo" label="Retur" @click="isReturnLookupOpen = true">
                            <q-badge v-if="pending_return_count > 0" color="negative" floating :label="pending_return_count" />
                            <q-tooltip v-if="pending_return_count > 0">{{ pending_return_count }} retur shift ini menunggu persetujuan Admin. Shift belum bisa ditutup sebelum itu.</q-tooltip>
                        </q-btn>
                        <q-btn v-if="shift" flat no-caps dense icon="local_shipping" label="COD" @click="isCodOpen = true" />
                        <q-btn v-if="shift" flat no-caps dense icon="assignment" @click="isOrdersOpen = true">
                            <span>Pesanan</span><kbd class="till__key">F7</kbd>
                            <q-badge v-if="order_counts.arrived > 0" color="negative" floating :label="order_counts.arrived" />
                            <q-badge v-else-if="order_counts.ready > 0" color="positive" floating :label="order_counts.ready" />
                            <q-badge v-else-if="order_counts.open > 0" color="grey-6" floating :label="order_counts.open" />
                            <q-tooltip>
                                <div>Pesanan pelanggan: {{ order_counts.open }} terbuka</div>
                                <div v-if="order_counts.arrived > 0">{{ order_counts.arrived }} barangnya sudah ada di stok, tandai sudah datang</div>
                                <div v-if="order_counts.ready > 0">{{ order_counts.ready }} siap diambil pelanggan</div>
                            </q-tooltip>
                        </q-btn>
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
                            @keydown="onSearchKeydown"
                        >
                            <template #prepend><q-icon name="search" /></template>
                        </q-input>

                        <p v-if="searchTerm.trim() === ''" class="finder__hint">Ketik atau scan barang. F2 cari, F4 tahan keranjang, F7 pesanan, F8 kosongkan, F9 bayar. Panah atas/bawah memilih baris keranjang, + dan - mengubah jumlah, Delete menghapus.</p>
                        <p v-else-if="searchResults.length === 0" class="finder__hint">Tidak ada barang yang cocok.</p>
                        <p v-else class="finder__hint">Panah atas/bawah memilih barang, kiri/kanan memilih satuan, Enter menambahkan.</p>

                        <section v-if="searchTerm.trim() === '' && best_sellers.length > 0" class="best">
                            <h3>Barang sering dijual</h3>
                            <div class="best__grid">
                                <button v-for="(item, index) in best_sellers" :key="item.id" type="button" class="best__tile" :disabled="isItemHeldBack(item)" @click="addBestSeller(item)">
                                    <kbd v-if="index < 9" class="best__key">Alt+{{ index + 1 }}</kbd>
                                    <b>{{ item.name }}</b>
                                    <small>{{ formatRupiah(defaultUnitOf(item).price) }} / {{ defaultUnitOf(item).code }}</small>
                                    <small :class="{ item__empty: Number(item.stock) <= 0 }">{{ Number(item.stock) <= 0 ? 'stok habis, bisa dipesan' : `stok ${formatStock(item.stock, item.units, item.base_unit, item.intact_packs)}` }}</small>
                                </button>
                            </div>
                        </section>

                        <ul class="finder__results">
                            <li v-for="(item, itemIndex) in searchResults" :key="item.id" class="item" :class="{ 'item--highlight': itemIndex === highlightedResultIndex }">
                                <div class="item__info">
                                    <b>{{ item.name }}</b>
                                    <small :class="{ item__empty: Number(item.stock) <= 0 || item.is_being_counted }">{{ item.sku }} · {{ item.is_being_counted ? 'sedang opname, belum bisa dijual' : Number(item.stock) <= 0 ? 'stok habis, bisa dipesan' : `stok ${formatStock(item.stock, item.units, item.base_unit, item.intact_packs)}` }}<template v-if="Number(item.reserved) > 0"> · {{ formatQuantity(item.reserved) }} {{ item.base_unit }} disisihkan untuk pesanan</template></small>
                                </div>
                                <div class="item__units">
                                    <q-btn
                                        v-for="(unit, unitIndex) in item.units"
                                        :key="unit.id"
                                        :outline="!(itemIndex === highlightedResultIndex && unitIndex === highlightedUnitIndex)"
                                        :unelevated="itemIndex === highlightedResultIndex && unitIndex === highlightedUnitIndex"
                                        no-caps
                                        dense
                                        color="primary"
                                        class="item__unit"
                                        :disable="isItemHeldBack(item)"
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

                            <div v-for="line in cart.lines.value" :key="line.key" class="line" :class="{ 'line--over': cart.isOverStock(line), 'line--selected': line.key === selectedLineKey }">
                                <div class="line__name">
                                    <b>{{ line.product.name }}</b>
                                    <small v-if="cart.isOverStock(line)" class="line__warning">{{ cart.stockWarningOf(line) }} {{ stockTextOf(line) }}. Kekurangannya jadi pesanan.</small>
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
                                <q-btn flat no-caps color="grey-8" icon="pause_circle" :disable="cart.isEmpty.value && heldCarts.held.value.length === 0" @click="holdOrRecallCart">
                                    <span>{{ cart.isEmpty.value ? 'Panggil' : 'Tahan' }} (F4)</span>
                                    <q-badge v-if="heldCarts.held.value.length > 0" color="orange" floating :label="heldCarts.held.value.length" />
                                    <q-tooltip>{{ cart.isEmpty.value ? 'Panggil keranjang yang ditahan' : 'Tahan keranjang ini supaya antrean jalan, panggil lagi dengan F4 saat keranjang kosong' }}</q-tooltip>
                                </q-btn>
                                <q-btn color="primary" unelevated no-caps size="lg" class="cart__pay" :label="cart.hasOverStock.value ? 'Bayar & Pesan (F9)' : 'Bayar (F9)'" :disable="cart.isEmpty.value" @click="openPayment" />
                            </div>
                        </footer>
                    </section>
                </div>

                <AppFooter />
            </q-page>
        </q-page-container>

        <PaymentDialog v-model="isPaymentOpen" :total="paymentTotal" :payment-methods="payment_methods" :is-processing="isPaying" :split="splitForPayment" :collecting-order="collectingOrder" @confirm="submitSale" />
        <CloseShiftDialog v-if="summary" v-model="isCloseOpen" :summary="summary" :payment-methods="payment_methods.filter((method) => method.value !== 'cod')" :is-processing="isClosing" @confirm="closeShift" />
        <SaleDoneDialog v-model="isSaleDoneOpen" :sale="last_sale" :order="last_order" :is-printed-automatically="is_receipt_printed_automatically" @delivery="openDelivery" />
        <HeldCartsDialog v-model="isHeldOpen" :held-carts="heldCarts.held.value" @resume="resumeHeldCart" @discard="heldCarts.discard" />
        <ShiftClosedDialog v-model="isShiftClosedOpen" :shift-totals="closed_shift" />
        <ReceiptHistoryDialog v-if="shift" v-model="isReceiptsOpen" :receipts-url="urls.receipts" :history-url="urls.sales_history" :can-open-history="can_leave_to_dashboard" @delivery="openDelivery" @return="openReturn" />
        <DeliveryNoteDialog v-model="isDeliveryOpen" :receipt="deliveryReceipt" />
        <ReturnLookupDialog v-if="shift" v-model="isReturnLookupOpen" :lookup-url="urls.return_lookup" @found="openReturn" />
        <ReturnDialog v-model="isReturnOpen" :receipt="returnReceipt" :products-url="urls.products" @saved="refreshShiftSummary" />
        <CodDialog v-if="shift" v-model="isCodOpen" :list-url="urls.cod" @changed="refreshShiftSummary" />
        <OrderListDialog v-if="shift" v-model="isOrdersOpen" :list-url="urls.orders" @changed="refreshShiftSummary" @collect="startCollecting" @collect-paid="collectPaidOrder" />
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
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px 20px;
}

.till__bar-actions :deep(.q-btn) {
    padding: 4px 10px;
}

.till__key {
    margin-left: 6px;
    padding: 1px 5px;
    border: 1px solid var(--app-line);
    border-radius: 4px;
    background: #f3f4f6;
    color: var(--app-ink-soft);
    font-family: inherit;
    font-size: 11px;
}

.best h3 {
    margin: 0 0 8px;
    font-size: 14px;
    color: var(--app-ink-soft);
}

.best__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 8px;
}

.best__tile {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 12px;
    border: 1px solid var(--app-line);
    border-radius: 12px;
    background: var(--app-surface);
    text-align: left;
    cursor: pointer;
}

.best__tile:hover:not(:disabled),
.best__tile:focus-visible {
    border-color: var(--q-primary);
}

.best__tile:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.best__tile small {
    color: var(--app-mute);
    font-size: 12px;
}

.best__key {
    align-self: flex-start;
    margin-bottom: 4px;
    padding: 1px 5px;
    border: 1px solid var(--app-line);
    border-radius: 4px;
    background: #f3f4f6;
    color: var(--app-ink-soft);
    font-family: inherit;
    font-size: 10px;
}

.item--highlight {
    border-color: var(--q-primary);
    outline: 2px solid var(--q-primary);
    outline-offset: -1px;
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

.line--selected {
    outline: 2px solid var(--q-primary);
    outline-offset: -2px;
    background: #fff1f1;
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
