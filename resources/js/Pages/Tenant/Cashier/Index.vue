<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRef, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import AppFooter from '../../../Components/AppFooter.vue';
import CloseShiftDialog from '../../../Components/Cashier/CloseShiftDialog.vue';
import PaymentDialog from '../../../Components/Cashier/PaymentDialog.vue';
import SaleDoneDialog from '../../../Components/Cashier/SaleDoneDialog.vue';
import ShiftClosedDialog from '../../../Components/Cashier/ShiftClosedDialog.vue';
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

const searchInput = ref(null);
const query = ref('');
const results = ref([]);
let latestSearchId = 0;
let searchTimer;

const isPaymentOpen = ref(false);
const isPaying = ref(false);
const isCloseOpen = ref(false);
const isClosing = ref(false);
const isSaleDoneOpen = ref(false);
const isShiftClosedOpen = ref(false);
let saleUuid = newUuid();

const timeFormat = new Intl.DateTimeFormat('id-ID', { timeStyle: 'short' });

const shiftSince = computed(() => (props.shift ? timeFormat.format(new Date(props.shift.opened_at)) : ''));

/**
 * Looks items up on the server. An answer that arrives after a newer search was started is dropped, so slow
 * responses cannot replace fresher results.
 * @returns {Promise<object[]|null>} null when the answer is stale
 */
async function fetchResults(term) {
    const searchId = ++latestSearchId;

    try {
        const response = await fetch(`${props.urls.products}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        if (searchId !== latestSearchId) {
            return null;
        }

        return response.ok ? (await response.json()).data : [];
    } catch {
        return searchId === latestSearchId ? [] : null;
    }
}

watch(query, (term) => {
    clearTimeout(searchTimer);

    if (term.trim() === '') {
        latestSearchId++;
        results.value = [];

        return;
    }

    searchTimer = setTimeout(async () => {
        const found = await fetchResults(term.trim());

        if (found !== null) {
            results.value = found;
        }
    }, SEARCH_DELAY_MS);
});

function resetSearch() {
    query.value = '';
    results.value = [];
    nextTick(() => searchInput.value?.focus());
}

/**
 * Enter: a scanned barcode adds the scanned unit at once; a single result adds its first unit. Anything else
 * leaves the results for the cashier to pick a unit.
 */
async function submitSearch() {
    const term = query.value.trim();

    if (term === '') {
        return;
    }

    clearTimeout(searchTimer);

    const found = await fetchResults(term);

    if (found === null) {
        return;
    }

    results.value = found;

    const scanned = found.find((item) => item.matched_unit_id !== null);

    if (scanned) {
        cart.add(scanned, scanned.matched_unit_id);
        resetSearch();
    } else if (found.length === 1) {
        cart.add(found[0], found[0].units[0].id);
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

function submitSale({ customer_name, payments }) {
    router.post(
        props.urls.sales,
        { client_uuid: saleUuid, customer_name, items: cart.payload.value, payments },
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

function closeShift(payload) {
    router.post(props.shift.close_url, payload, {
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
    (result) => {
        if (result) {
            isShiftClosedOpen.value = true;
        }
    },
    { immediate: true },
);

/** F9 pays, F8 empties the cart, F2 goes to the search box. */
function onKeydown(event) {
    if (!props.shift) {
        return;
    }

    const shortcuts = { F9: openPayment, F8: clearCart, F2: () => searchInput.value?.focus() };

    if (shortcuts[event.key] && !isPaymentOpen.value && !isCloseOpen.value) {
        event.preventDefault();
        shortcuts[event.key]();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    searchInput.value?.focus();
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
                        <q-btn v-if="shift" flat no-caps dense color="negative" icon="lock_clock" label="Tutup Shift" @click="openClose">
                            <q-tooltip>Tutup shift dulu untuk keluar</q-tooltip>
                        </q-btn>
                        <q-btn v-if="can_leave_to_dashboard" flat no-caps dense icon="space_dashboard" label="Dashboard" @click="router.visit(urls.dashboard)" />
                        <q-btn v-if="!shift" flat no-caps dense icon="logout" label="Keluar" @click="router.post(page.props.urls.logout)" />
                    </div>
                </header>

                <ShiftOpenPanel v-if="!shift" :registers="registers" :open-url="urls.open_shift" :store-name="page.props.store.name" />

                <div v-else class="till__main">
                    <section class="finder">
                        <q-input
                            ref="searchInput"
                            v-model="query"
                            outlined
                            clearable
                            placeholder="Cari nama, SKU, atau scan barcode (Enter)"
                            class="finder__input"
                            @keydown.enter.prevent="submitSearch"
                        >
                            <template #prepend><q-icon name="search" /></template>
                        </q-input>

                        <p v-if="query.trim() === ''" class="finder__hint">Ketik atau scan barang. F2 fokus ke pencarian, F9 bayar, F8 kosongkan keranjang.</p>
                        <p v-else-if="results.length === 0" class="finder__hint">Tidak ada barang yang cocok.</p>

                        <ul class="finder__results">
                            <li v-for="item in results" :key="item.id" class="item">
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

        <PaymentDialog v-model="isPaymentOpen" :total="cart.total.value" :methods="payment_methods" :is-processing="isPaying" @confirm="submitSale" />
        <CloseShiftDialog v-if="summary" v-model="isCloseOpen" :summary="summary" :payment-methods="payment_methods" :is-processing="isClosing" @confirm="closeShift" />
        <SaleDoneDialog v-model="isSaleDoneOpen" :sale="last_sale" />
        <ShiftClosedDialog v-model="isShiftClosedOpen" :result="closed_shift" />
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
