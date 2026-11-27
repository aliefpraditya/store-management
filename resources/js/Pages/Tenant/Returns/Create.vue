<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ExchangeEditor from '../../../Components/ExchangeEditor.vue';
import { newUuid, roundMoney } from '../../../Composables/useCart';
import { refundOfLine } from '../../../Composables/useLineAmounts';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import { formatQuantity } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    /** The receipt chosen on the way in, or null. `cod_outstanding` is the COD still owed on it. @type {{id: number, number: string, customer_name: string|null, default_refund_method: string, cod_outstanding: string, lines: object[]}|null} */
    sale: { type: Object, default: null },
    refund_methods: { type: Array, required: true },
    resolutions: { type: Array, required: true },
    urls: { type: Object, required: true },
});

/** One uuid per opened form: a second tap or a retry of the same save gives back the first return, not another. */
const form = useForm({
    client_uuid: newUuid(),
    sale_id: null,
    resolution: 'exchange',
    refund_method: 'cash',
    refund_account: '',
    reason: '',
    items: [],
    exchange_items: [],
    exchange_payments: [],
});

const isExchange = computed(() => form.resolution === 'exchange');

/** What the replacement editor last reported: its lines, extra payment and whether it is complete. */
const exchangeDraft = ref(null);

function onExchangeChange(payload) {
    exchangeDraft.value = payload;
    form.exchange_items = payload.items;
    form.exchange_payments = payload.payments;
    form.refund_method = payload.refund_method;
    form.refund_account = payload.refund_account;
}

/**
 * The part of a refund that cuts the COD still owed on the receipt instead of paying money out: the customer has not
 * paid that part yet. An exchange is settled by the replacement receipt, so nothing is cut there.
 */
const codOffset = computed(() => (isExchange.value ? 0 : roundMoney(Math.min(refundTotal.value, Number(chosenSale.value?.cod_outstanding) || 0))));

/** What is really paid back after the COD cut. */
const moneyOut = computed(() => roundMoney(refundTotal.value - codOffset.value));

/** Money paid back by transfer, QRIS or EDC needs the account it goes to (an exchange says so itself); nothing is needed when no money goes out. */
const isAccountMissing = computed(() => !isExchange.value && moneyOut.value > 0 && form.refund_method !== 'cash' && form.refund_account.trim() === '');

/** Back to a plain refund: the money goes back the way the customer paid and no replacement is sent. */
watch(
    () => form.resolution,
    (resolution) => {
        if (resolution === 'refund') {
            form.refund_method = chosenSale.value?.default_refund_method ?? 'cash';
            form.refund_account = '';
            form.exchange_items = [];
            form.exchange_payments = [];
            exchangeDraft.value = null;
        }
    },
);

const chosenSale = ref(null);
const saleOptions = ref([]);
let latestSearchId = 0;

/**
 * Picks the receipt: the money goes back the way the customer paid, and no line is returned until a quantity is typed.
 */
function chooseSale(sale) {
    chosenSale.value = sale;
    form.sale_id = sale?.id ?? null;
    form.refund_method = sale?.default_refund_method ?? 'cash';
    form.items = (sale?.lines ?? []).map((line) => ({ sale_item_id: line.id, quantity: 0 }));
}

if (props.sale) {
    chooseSale(props.sale);
}

/**
 * Looks completed receipts up as the user types; an answer that arrives after a newer search was started is dropped.
 */
function searchSales(typed, update, abort) {
    const searchId = ++latestSearchId;

    fetch(`${props.urls.sales}?q=${encodeURIComponent(typed.trim())}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((response) => (response.ok ? response.json() : { data: [] }))
        .catch(() => ({ data: [] }))
        .then(({ data }) => (searchId === latestSearchId ? update(() => (saleOptions.value = data)) : abort()));
}

const lines = computed(() => chosenSale.value?.lines ?? []);

const hasAnythingToReturn = computed(() => form.items.some((item) => Number(item.quantity) > 0));

/** What the customer gets back: the receipt's price for each returned quantity. */
const refundTotal = computed(() => roundMoney(form.items.reduce((sum, item, index) => sum + refundOfLine(lines.value[index], item.quantity), 0)));

watch(
    () => form.items,
    () => form.clearErrors('items'),
    { deep: true },
);

function submit() {
    form.post(props.urls.store);
}
</script>

<template>
    <StoreLayout title="Retur Baru">
        <template #actions>
            <q-btn flat no-caps label="Batal" class="q-mr-sm" @click="router.visit(urls.index)" />
            <q-btn color="primary" unelevated no-caps label="Simpan Retur" :loading="form.processing" :disable="!chosenSale || !hasAnythingToReturn || isAccountMissing || (isExchange && !exchangeDraft?.is_ready)" @click="submit" />
        </template>

        <q-form class="return-form" @submit.prevent="submit">
            <section class="card">
                <h2>Nota</h2>
                <q-select
                    :model-value="chosenSale"
                    :options="saleOptions"
                    option-label="number"
                    option-value="id"
                    label="Cari nomor nota atau nama pelanggan"
                    outlined
                    use-input
                    hide-selected
                    fill-input
                    input-debounce="250"
                    behavior="menu"
                    :error="!!form.errors.sale_id"
                    :error-message="form.errors.sale_id"
                    @filter="searchSales"
                    @update:model-value="chooseSale"
                >
                    <template #prepend><q-icon name="receipt_long" /></template>
                    <template #option="{ itemProps, opt }">
                        <q-item v-bind="itemProps">
                            <q-item-section>
                                <q-item-label>{{ opt.number }}</q-item-label>
                                <q-item-label caption>{{ opt.customer_name ?? 'Tanpa nama pelanggan' }} · {{ opt.lines.length }} barang</q-item-label>
                            </q-item-section>
                        </q-item>
                    </template>
                    <template #no-option>
                        <q-item><q-item-section class="text-grey">Ketik untuk mencari nota yang masih punya barang untuk diretur</q-item-section></q-item>
                    </template>
                </q-select>
            </section>

            <template v-if="chosenSale">
                <section class="card">
                    <h2>Barang yang diretur</h2>
                    <p class="hint">Isi jumlah yang dikembalikan pelanggan. Biarkan 0 untuk barang yang tidak diretur. Barangnya masuk ke gudang retur dan baru bisa dijual atau jadi dead stock setelah diperiksa lewat opname.</p>
                    <p v-if="form.errors.items" class="error">{{ form.errors.items }}</p>

                    <table class="lines">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th class="lines__right">Di nota</th>
                                <th class="lines__right">Bisa diretur</th>
                                <th class="lines__qty">Diretur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, index) in lines" :key="line.id">
                                <td>{{ line.name }}<small>{{ formatRupiah(line.unit_price) }} / {{ line.unit }}</small></td>
                                <td class="lines__right">{{ formatQuantity(line.quantity) }} {{ line.unit }}</td>
                                <td class="lines__right">{{ formatQuantity(line.remaining) }} {{ line.unit }}</td>
                                <td class="lines__qty">
                                    <q-input
                                        v-model.number="form.items[index].quantity"
                                        type="number"
                                        min="0"
                                        step="any"
                                        outlined
                                        dense
                                        hide-bottom-space
                                        :suffix="line.unit"
                                        :disable="Number(line.remaining) <= 0"
                                        :error="!!form.errors[`items.${index}.quantity`]"
                                        :aria-label="`Jumlah retur ${line.name}`"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="card">
                    <h2>Penyelesaian</h2>
                    <q-btn-toggle v-model="form.resolution" :options="props.resolutions" no-caps unelevated toggle-color="primary" color="grey-3" text-color="grey-8" class="return-form__toggle" />

                    <div v-if="!isExchange" class="refund">
                        <template v-if="codOffset === 0 || moneyOut > 0">
                            <q-select
                                v-model="form.refund_method"
                                :options="refund_methods"
                                option-value="value"
                                option-label="label"
                                emit-value
                                map-options
                                behavior="menu"
                                label="Dikembalikan lewat"
                                outlined
                                :error="!!form.errors.refund_method"
                                :error-message="form.errors.refund_method"
                            />
                            <q-input v-if="form.refund_method !== 'cash'" v-model="form.refund_account" label="Rekening penerima" placeholder="Bank, nomor rekening, atas nama" outlined :error="!!form.errors.refund_account" :error-message="form.errors.refund_account" />
                        </template>
                        <p v-else class="hint refund__note">Tidak ada uang yang dikembalikan.</p>
                        <p v-if="codOffset > 0" class="hint refund__note">Nota ini COD yang belum lunas: {{ formatRupiah(codOffset) }} dipotong dari tagihan COD, jadi pelanggan membayar lebih sedikit saat uangnya diterima.</p>
                        <div v-if="codOffset > 0" class="refund__total"><span>Nilai retur</span><b>{{ formatRupiah(refundTotal) }}</b></div>
                        <div class="refund__total"><span>{{ codOffset > 0 ? 'Uang dikembalikan' : 'Total dikembalikan' }}</span><b>{{ formatRupiah(moneyOut) }}</b></div>
                    </div>

                    <template v-else>
                        <p class="hint">Pilih barang yang dibawa pelanggan sebagai gantinya. Nilai barang yang diretur dipakai lebih dulu; pelanggan menambah bila kurang, atau menerima sisanya.</p>
                        <p v-if="form.errors.exchange_items" class="error">{{ form.errors.exchange_items }}</p>
                        <ExchangeEditor :products-url="urls.products" :credit="refundTotal" :payment-methods="refund_methods" :default-method="chosenSale.default_refund_method" @change="onExchangeChange" />
                    </template>

                    <q-input v-model="form.reason" label="Alasan retur (opsional)" outlined :error="!!form.errors.reason" :error-message="form.errors.reason" class="return-form__reason" />
                    <p class="hint">
                        Uang tunai yang masuk atau keluar dicatat di laci kas shift Anda yang sedang berjalan. Harga barang yang diretur mengikuti nota asli; harga barang pengganti mengikuti harga hari ini.
                    </p>
                </section>
            </template>
        </q-form>
    </StoreLayout>
</template>

<style scoped>
.return-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 920px;
}

.card {
    padding: 20px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0 0 14px;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.4;
}

.hint {
    margin: 0 0 10px;
    color: var(--app-mute);
    font-size: 12.5px;
}

.error {
    margin: 0 0 10px;
    color: #be123c;
    font-size: 13px;
}

.lines {
    width: 100%;
    border-collapse: collapse;
}

.lines th {
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
}

.lines td {
    padding: 8px 0;
    border-bottom: 1px solid var(--app-line);
}

.lines small {
    display: block;
    color: var(--app-mute);
}

.lines .lines__right {
    text-align: right;
    white-space: nowrap;
}

.lines .lines__qty {
    width: 150px;
    padding-left: 14px;
}

.refund {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: 14px;
}

.refund__note {
    grid-column: 1 / -1;
    margin: 0;
}

.refund__total {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    color: var(--app-mute);
}

.refund__total b {
    color: var(--app-ink);
    font-size: 22px;
}

.return-form__toggle {
    margin-bottom: 14px;
}

.return-form__reason {
    margin: 14px 0 10px;
}

@media screen and (max-width: 640px) {
    .refund {
        grid-template-columns: 1fr;
    }
}
</style>
